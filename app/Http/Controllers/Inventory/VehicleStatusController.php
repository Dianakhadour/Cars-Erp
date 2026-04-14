<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VehicleStatusController extends Controller
{
    /**
     * Get current company ID
     */
    protected function getCompanyId()
    {
        $user = Auth::guard('tenant')->user();
        
        if ($user && $user->company_id) {
            return $user->company_id;
        }
        
        if (function_exists('tenant') && tenant('id')) {
            return tenant('id');
        }
        
        if (request()->header('X-Company-ID')) {
            return request()->header('X-Company-ID');
        }
        
        if (session('company_id')) {
            return session('company_id');
        }
        
        throw new \Exception('Company context not found');
    }

    /**
     * Update vehicle status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    { 
        try {
             
            $validated = $request->validate([
                'vehicle_status' => 'required|in:new,used,demo,reconditioned',  
                'status_reason' => 'nullable|string|max:500',
                'status_date' => 'nullable|date',
                'update_stock' => 'sometimes|boolean'
            ]);

            
            $updateStock = $validated['update_stock'] ?? true;
            $statusDate = $validated['status_date'] ?? now();

            DB::beginTransaction();

             
            $vehicle = Item::with(['stockValuations'])
                ->where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($id);

            $oldStatus = $vehicle->vehicle_status;
            $newStatus = $validated['vehicle_status'];

           
            DB::table('items')
                ->where('id', $vehicle->id)
                ->update([
                    'vehicle_status' => $newStatus,
                    'updated_at' => now()
                ]);

            
            $vehicle->refresh();

            // Handle stock movements based on status change
            if ($updateStock && $this->shouldUpdateStock($oldStatus, $newStatus)) {
                $this->handleStatusStockMovement($vehicle, $oldStatus, $newStatus, $validated['status_reason'] ?? null, $statusDate);
            }

            // Log status change
            $this->logStatusChange($vehicle, $oldStatus, $newStatus, $validated['status_reason'] ?? null);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle status updated successfully',
                'data' => [
                    'vehicle' => $vehicle->load(['category', 'stockValuations.warehouse']),
                    'status_change' => [
                        'from' => $oldStatus,
                        'to' => $newStatus,
                        'reason' => $validated['status_reason'] ?? null,
                        'date' => $statusDate
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle status',
                'error' => $e->getMessage(),
                'debug' => [
                    'vehicle_id' => $id,
                    'new_status' => $validated['vehicle_status'] ?? 'not provided',
                    'old_status' => $oldStatus ?? 'unknown'
                ]
            ], 500);
        }
    }

    /**
     * List vehicles by status
     */
    public function getVehiclesByStatus(string $status, Request $request): JsonResponse
    {
        try {
            // Use only the ENUM values that exist in your database
            $validStatuses = ['new', 'used', 'demo', 'reconditioned']; 
            
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid vehicle status',
                    'valid_statuses' => $validStatuses
                ], 422);
            }

            $query = Item::with([
                'category',
                'stockValuations.warehouse',
                'stockValuations.bin'
            ])
            ->where('company_id', $this->getCompanyId())
            ->where('item_type', 'vehicle')
            ->where('vehicle_status', $status);

            // Apply filters...
            if ($request->has('make')) {
                $query->where('make', 'like', "%{$request->make}%");
            }

            if ($request->has('model')) {
                $query->where('model', 'like', "%{$request->model}%");
            }

            if ($request->has('year')) {
                $query->where('year', $request->year);
            }

            if ($request->has('color')) {
                $query->where('color', 'like', "%{$request->color}%");
            }

            if ($request->has('min_price')) {
                $query->where('selling_price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('selling_price', '<=', $request->max_price);
            }

            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('sku', 'like', "%{$searchTerm}%")
                      ->orWhere('vin', 'like', "%{$searchTerm}%")
                      ->orWhere('make', 'like', "%{$searchTerm}%")
                      ->orWhere('model', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }

            if ($request->has('in_stock_only') && $request->in_stock_only) {
                $query->whereHas('stockValuations', function($q) {
                    $q->where('quantity_on_hand', '>', 0);
                });
            }

            $orderBy = $request->get('order_by', 'name');
            $orderDirection = $request->get('order_direction', 'asc');

            $allowedOrderBy = ['name', 'make', 'model', 'year', 'selling_price', 'created_at', 'mileage'];
            $orderBy = in_array($orderBy, $allowedOrderBy) ? $orderBy : 'name';

            $query->orderBy($orderBy, $orderDirection);

            $vehicles = $query->paginate($request->get('per_page', 20));

            // Get status summary
            $statusSummary = $this->getStatusSummary();

            return response()->json([
                'success' => true,
                'data' => $vehicles->items(),
                'summary' => [
                    'current_status' => $status,
                    'count' => $vehicles->total(),
                    'overall_summary' => $statusSummary
                ],
                'meta' => [
                    'current_page' => $vehicles->currentPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'last_page' => $vehicles->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicles by status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all vehicles status summary
     */
    
    public function getStatusSummary(): JsonResponse
{ 
    try { 
        $companyId = $this->getCompanyId();
        
       
        $allStatuses = ['new', 'used', 'demo', 'sold', 'reserved'];
        
        $summary = Item::where('company_id', $companyId)
            ->where('item_type', 'vehicle')
            ->select(
                'vehicle_status',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(selling_price), 0) as total_value')
            )
            ->groupBy('vehicle_status')
            ->get()
            ->keyBy('vehicle_status');

         
        $mergedSummary = [];
        foreach ($allStatuses as $status) {
            $statusData = $summary->get($status);
            
            
            $mergedSummary[$status] = [
                'count' => $statusData ? $statusData->count : 0,
                'total_value' => $statusData ? (float) $statusData->total_value : 0,
                'in_stock_count' => $statusData ? $statusData->count : 0,  
                'total_quantity' => $statusData ? $statusData->count : 0,  
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $mergedSummary,
            'total_vehicles' => $summary->sum('count'),
            'total_value' => $summary->sum('total_value')
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch status summary',
            'error' => $e->getMessage()
        ], 500);
    }
}

   

    /**
     * Log status change for audit purposes
     */
    private function logStatusChange(Item $vehicle, string $oldStatus, string $newStatus, ?string $reason): void
    {
        \Log::info('Vehicle status changed', [
            'vehicle_id' => $vehicle->id,
            'vehicle_name' => $vehicle->name,
            'vin' => $vehicle->vin,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => Auth::guard('tenant')->user()->id,
            'changed_at' => now(),
        ]);
    }
}