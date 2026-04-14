<?php
 
namespace App\Http\Controllers\Inventory;

use App\Models\Tenant\Part;
use Illuminate\Http\Request;
use App\Models\Tenant\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\PartStockMovement;
use App\Models\Tenant\PartStockValuation;
use Illuminate\Support\Facades\Validator;

class PartStockController extends Controller
{
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
    public function getStockLevels(Request $request): JsonResponse
    {
        try {
            $stockLevels = PartStockValuation::with(['part', 'part.category', 'warehouse'])
                ->byCompany($this->getCompanyId())
                ->when($request->has('warehouse_id'), function($query) use ($request) {
                    return $query->byWarehouse($request->warehouse_id);
                })
                ->when($request->has('low_stock_only'), function($query) {
                    return $query->lowStock();
                })
                ->when($request->has('out_of_stock_only'), function($query) {
                    return $query->outOfStock();
                })
                ->when($request->has('part_id'), function($query) use ($request) {
                    return $query->byPart($request->part_id);
                })
                ->orderBy('part_id')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $stockLevels
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock levels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStockHistory($id): JsonResponse
    {
        try {
            $part = Part::byCompany($this->getCompanyId())->findOrFail($id);

            $movements = PartStockMovement::with(['warehouse', 'creator'])
                ->byCompany($this->getCompanyId())
                ->byPart($id)
                ->completed()
                ->orderBy('movement_date', 'desc')
                ->paginate(20);

            $stockSummary = PartStockValuation::byCompany($this->getCompanyId())
                ->byPart($id)
                ->get()
                ->map(function($valuation) {
                    return [
                        'warehouse_name' => $valuation->warehouse->name,
                        'quantity_on_hand' => $valuation->quantity_on_hand,
                        'quantity_reserved' => $valuation->quantity_reserved,
                        'quantity_available' => $valuation->quantity_available,
                        'average_cost' => $valuation->average_cost,
                        'total_value' => $valuation->total_value,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'part' => $part->load(['category']),
                    'movements' => $movements,
                    'stock_summary' => $stockSummary,
                    'total_movements' => $movements->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLowStockAlerts(Request $request): JsonResponse
    {
        try {
            $lowStockItems = PartStockValuation::with(['part', 'part.category', 'warehouse'])
                ->byCompany($this->getCompanyId())
                ->lowStock()
                ->when($request->has('warehouse_id'), function($query) use ($request) {
                    return $query->byWarehouse($request->warehouse_id);
                })
                ->orderBy('quantity_available')
                ->get()
                ->map(function($valuation) {
                    $part = $valuation->part;
                    return [
                        'part_id' => $part->id,
                        'part_sku' => $part->sku,
                        'part_name' => $part->name,
                        'category' => $part->category->name ?? 'Uncategorized',
                        'warehouse' => $valuation->warehouse->name,
                        'current_stock' => $valuation->quantity_available,
                        'reorder_point' => $part->reorder_point,
                        'min_stock_level' => $part->min_stock_level,
                        'needed_quantity' => max(0, $part->min_stock_level - $valuation->quantity_available),
                        'last_movement' => $valuation->last_movement_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'low_stock_items' => $lowStockItems,
                    'total_count' => $lowStockItems->count(),
                    'summary' => [
                        'critical' => $lowStockItems->where('current_stock', 0)->count(),
                        'warning' => $lowStockItems->where('current_stock', '>', 0)->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low stock alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOutOfStockItems(Request $request): JsonResponse
    {
        try {
            $outOfStockItems = PartStockValuation::with(['part', 'part.category', 'warehouse'])
                ->byCompany($this->getCompanyId())
                ->outOfStock()
                ->when($request->has('warehouse_id'), function($query) use ($request) {
                    return $query->byWarehouse($request->warehouse_id);
                })
                ->get()
                ->map(function($valuation) {
                    $part = $valuation->part;
                    return [
                        'part_id' => $part->id,
                        'part_sku' => $part->sku,
                        'part_name' => $part->name,
                        'category' => $part->category->name ?? 'Uncategorized',
                        'warehouse' => $valuation->warehouse->name,
                        'last_movement' => $valuation->last_movement_at,
                        'reorder_quantity' => $part->max_stock_level - $part->min_stock_level,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'out_of_stock_items' => $outOfStockItems,
                    'total_count' => $outOfStockItems->count(),
                    'warehouse_summary' => $outOfStockItems->groupBy('warehouse')->map->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch out of stock items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adjustStock(Request $request, $id)  : JsonResponse
    {
        try {
            $part = Part::byCompany($this->getCompanyId())->findOrFail($id);
             
 
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'quantity' => 'required|numeric|min:0',
                'adjustment_type' => 'required|in:set,add,subtract',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string',
                'adjustment_date' => 'nullable|date',
            ]);

            DB::beginTransaction();

            // Get current stock valuation
            $valuation = PartStockValuation::firstOrCreate([
                'part_id' => $part->id,
                'warehouse_id' => $validated['warehouse_id'],
                'company_id' => $this->getCompanyId(),
            ], [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'average_cost' => $part->cost_price,
            ]);

            $oldQuantity = $valuation->quantity_on_hand;
            
            // Calculate new quantity based on adjustment type
            switch ($validated['adjustment_type']) {
                case 'set':
                    $newQuantity = $validated['quantity'];
                    break;
                case 'add':
                    $newQuantity = $oldQuantity + $validated['quantity'];
                    break;
                case 'subtract':
                    $newQuantity = max(0, $oldQuantity - $validated['quantity']);
                    break;
            }

            // Create stock movement
            $movement = PartStockMovement::create([
                'company_id' => $this->getCompanyId(),
                'part_id' => $part->id,
                'warehouse_id' => $validated['warehouse_id'],
                'movement_type' => 'adjustment',
                'quantity' => $newQuantity,  
                'unit_cost' => $valuation->average_cost,
                'total_cost' => $newQuantity * $valuation->average_cost,
                'reference_type' => 'stock_adjustment',
                'document_number' => 'ADJ-' . date('Ymd-His'),
                'status' => 'completed',
                'notes' => "Stock adjustment: {$validated['reason']}. " . 
                          "Old: {$oldQuantity}, New: {$newQuantity}. " . 
                          ($validated['notes'] ?? ''),
                'created_by' => auth()->id(),
                'movement_date' => $validated['adjustment_date'] ?? now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'part' => $part,
                    'adjustment' => $movement,
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'difference' => $newQuantity - $oldQuantity,
                ]
            ]);

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
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}