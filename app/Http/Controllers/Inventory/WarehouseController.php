<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Warehouse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
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
     * List all warehouses/lots
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $warehouses = Warehouse::with(['manager', 'bins'])
                ->where('company_id', $this->getCompanyId())
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $warehouses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create warehouse/vehicle lot
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address_line_1' => 'required|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'type' => 'required|in:main,regional,branch,virtual,vehicle_lot',
                'total_area' => 'nullable|numeric|min:0',
                'usable_area' => 'nullable|numeric|min:0',
                'capacity_units' => 'nullable|integer|min:0',
                'manager_id' => 'nullable|exists:users,id',
            ]);
 
      
            $existingWarehouse = Warehouse::where('company_id', $this->getCompanyId())
                ->where('code', $validated['code'])
                ->first(); 

            if ($existingWarehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Warehouse code already exists for this company'
                ], 422);
            }

            DB::beginTransaction();

            $warehouse = Warehouse::create([
                'company_id' => $this->getCompanyId(),
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postal_code' => $validated['postal_code'],
                'country' => $validated['country'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'type' => $validated['type'],
                'total_area' => $validated['total_area'] ?? null,
                'usable_area' => $validated['usable_area'] ?? null,
                'capacity_units' => $validated['capacity_units'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'is_active' => true,
                'is_default' => false, 
            ]);

            // If this is the first warehouse, set it as default
            $warehouseCount = Warehouse::where('company_id', $this->getCompanyId())->count();
            if ($warehouseCount === 1) {
                $warehouse->update(['is_default' => true]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => $warehouse->load(['manager'])
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get warehouse details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::with([
                'manager', 
                'bins',
                'sourceStockMovements.item',
                'destinationStockMovements.item'
            ])
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

            // Add stock summary
            $stockSummary = DB::table('stock_valuations')
                ->where('warehouse_id', $id)
                ->select(
                    DB::raw('COUNT(*) as total_items'),
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(total_value) as total_value')
                )
                ->first();

            $warehouse->stock_summary = $stockSummary;

            return response()->json([
                'success' => true,
                'data' => $warehouse
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set default warehouse
     */
    public function setDefault(string $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::where('company_id', $this->getCompanyId())
                ->findOrFail($id);

            DB::beginTransaction();

            // Remove default from all warehouses
            Warehouse::where('company_id', $this->getCompanyId())
                ->update(['is_default' => false]);

            // Set this warehouse as default
            $warehouse->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse set as default successfully',
                'data' => $warehouse
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}