<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Item;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\Bin;
use App\Models\Tenant\StockValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
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
     * List all stock movements
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = StockMovement::with([
                'item', 
                'sourceWarehouse', 
                'destinationWarehouse',
                'creator'
            ])
            ->where('company_id', $this->getCompanyId())
            ->orderBy('created_at', 'desc');

            // Filter by movement type
            if ($request->has('movement_type')) {
                $query->where('movement_type', $request->movement_type);
            }

            // Filter by item
            if ($request->has('item_id')) {
                $query->where('item_id', $request->item_id);
            }

            // Filter by warehouse
            if ($request->has('warehouse_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('source_warehouse_id', $request->warehouse_id)
                      ->orWhere('destination_warehouse_id', $request->warehouse_id);
                });
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('movement_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('movement_date', '<=', $request->end_date);
            }

            $movements = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $movements->items(),
                'meta' => [
                    'current_page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'last_page' => $movements->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock movements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Receive vehicles into stock
     */
    public function receiveStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_id' => 'required|exists:items,id',
                'quantity' => 'required|numeric|min:0.001',
                'unit_cost' => 'required|numeric|min:0',
                'destination_warehouse_id' => 'required|exists:warehouses,id',
                'destination_bin_id' => 'nullable|exists:bins,id',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'vehicle_attributes' => 'nullable|array',
            ]);

            DB::beginTransaction();

            // Get item and validate it's a vehicle
            $item = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($validated['item_id']);

            // Get warehouse
            $warehouse = Warehouse::where('company_id', $this->getCompanyId())
                ->findOrFail($validated['destination_warehouse_id']);

            // Create stock movement
            $movement = StockMovement::create([
                'company_id' => $this->getCompanyId(),
                'item_id' => $validated['item_id'],
                'movement_type' => 'purchase_receipt',
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'],
                'total_value' => $validated['quantity'] * $validated['unit_cost'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'destination_bin_id' => $validated['destination_bin_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'vehicle_attributes' => $validated['vehicle_attributes'] ?? null,
                'status' => 'completed',
                'created_by' => Auth::guard('tenant')->user()->id,
                'movement_date' => now(),
            ]);

         
            $this->updateStockValuation(
                $validated['item_id'],
                $validated['destination_warehouse_id'],
                $validated['destination_bin_id'] ?? null,
                $validated['quantity'],
                $validated['unit_cost'],
                'in'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock received successfully',
                'data' => $movement->load(['item', 'destinationWarehouse', 'destinationBin', 'creator'])
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
                'message' => 'Failed to receive stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Issue/sell vehicles out of stock
     */
    public function issueStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_id' => 'required|exists:items,id',
                'quantity' => 'required|numeric|min:0.001',
                'unit_cost' => 'required|numeric|min:0',
                'source_warehouse_id' => 'required|exists:warehouses,id',
                'source_bin_id' => 'nullable|exists:bins,id',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
                'reason' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Get item and validate it's a vehicle
            $item = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($validated['item_id']);

            // Check stock availability
            $stock = StockValuation::where('item_id', $validated['item_id'])
                ->where('warehouse_id', $validated['source_warehouse_id'])
                ->when($validated['source_bin_id'], function($query, $binId) {
                    return $query->where('bin_id', $binId);
                })
                ->first();

            if (!$stock || $stock->quantity_available < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock available',
                    'available_stock' => $stock ? $stock->quantity_available : 0
                ], 422);
            }

            // Create stock movement
            $movement = StockMovement::create([
                'company_id' => $this->getCompanyId(),
                'item_id' => $validated['item_id'],
                'movement_type' => 'sale_issue',
                'quantity' => -$validated['quantity'],  
                'unit_cost' => $validated['unit_cost'],
                'total_value' => $validated['quantity'] * $validated['unit_cost'],
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'source_bin_id' => $validated['source_bin_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'status' => 'completed',
                'created_by' => Auth::guard('tenant')->user()->id,
                'movement_date' => now(),
            ]);

            // Update stock valuation
            $this->updateStockValuation(
                $validated['item_id'],
                $validated['source_warehouse_id'],
                $validated['source_bin_id'] ?? null,
                -$validated['quantity'], // Negative for outbound
                $validated['unit_cost'],
                'out'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock issued successfully',
                'data' => $movement->load(['item', 'sourceWarehouse', 'sourceBin', 'creator'])
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
                'message' => 'Failed to issue stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer vehicles between lots
     */
    public function transferStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_id' => 'required|exists:items,id',
                'quantity' => 'required|numeric|min:0.001',
                'source_warehouse_id' => 'required|exists:warehouses,id',
                'source_bin_id' => 'nullable|exists:bins,id',
                'destination_warehouse_id' => 'required|exists:warehouses,id',
                'destination_bin_id' => 'nullable|exists:bins,id',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Get item and validate it's a vehicle
            $item = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($validated['item_id']);
                
            // Check source stock availability
            $sourceStock = StockValuation::where('item_id', $validated['item_id'])
                ->where('warehouse_id', $validated['source_warehouse_id'])
                ->when($validated['source_bin_id'], function($query, $binId) {
                    return $query->where('bin_id', $binId);
                })
                ->first();

            if (!$sourceStock || $sourceStock->quantity_available < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in source location',
                    'available_stock' => $sourceStock ? $sourceStock->quantity_available : 0
                ], 422);
            }

            // Get average cost for valuation
            $unitCost = $sourceStock->average_cost;

            // Create transfer out movement
            $transferOut = StockMovement::create([
                'company_id' => $this->getCompanyId(),
                'item_id' => $validated['item_id'],
                'movement_type' => 'transfer_out',
                'quantity' => -$validated['quantity'],
                'unit_cost' => $unitCost,
                'total_value' => $validated['quantity'] * $unitCost,
                'source_warehouse_id' => $validated['source_warehouse_id'],
                'source_bin_id' => $validated['source_bin_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? 'TR-' . now()->format('YmdHis'),
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'created_by' => Auth::guard('tenant')->user()->id,
                'movement_date' => now(),
            ]);

            // Create transfer in movement
            $transferIn = StockMovement::create([
                'company_id' => $this->getCompanyId(),
                'item_id' => $validated['item_id'],
                'movement_type' => 'transfer_in',
                'quantity' => $validated['quantity'],
                'unit_cost' => $unitCost,
                'total_value' => $validated['quantity'] * $unitCost,
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'destination_bin_id' => $validated['destination_bin_id'] ?? null,
                'reference_number' => $validated['reference_number'] ?? 'TR-' . now()->format('YmdHis'),
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'created_by' => Auth::guard('tenant')->user()->id,
                'movement_date' => now(),
            ]);

            // Update source stock valuation
            $this->updateStockValuation(
                $validated['item_id'],
                $validated['source_warehouse_id'],
                $validated['source_bin_id'] ?? null,
                -$validated['quantity'],
                $unitCost,
                'out'
            );

            // Update destination stock valuation
            $this->updateStockValuation(
                $validated['item_id'],
                $validated['destination_warehouse_id'],
                $validated['destination_bin_id'] ?? null,
                $validated['quantity'],
                $unitCost,
                'in'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transferred successfully',
                'data' => [
                    'transfer_out' => $transferOut->load(['item', 'sourceWarehouse', 'sourceBin']),
                    'transfer_in' => $transferIn->load(['item', 'destinationWarehouse', 'destinationBin'])
                ]
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
                'message' => 'Failed to transfer stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust stock counts
     */
    public function adjustStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'item_id' => 'required|exists:items,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'bin_id' => 'nullable|exists:bins,id',
                'new_quantity' => 'required|numeric|min:0',
                'reason' => 'required|string|max:255',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Get item and validate it's a vehicle
            $item = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($validated['item_id']);

            // Get current stock
            $currentStock = StockValuation::where('item_id', $validated['item_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->when($validated['bin_id'], function($query, $binId) {
                    return $query->where('bin_id', $binId);
                })
                ->first();

            $currentQuantity = $currentStock ? $currentStock->quantity_on_hand : 0;
            $quantityDifference = $validated['new_quantity'] - $currentQuantity;
            $unitCost = $currentStock ? $currentStock->average_cost : $item->standard_cost;

       
            $movementType = $quantityDifference >= 0 ? 'adjustment_in' : 'adjustment_out';

            // Create adjustment movement
            $movement = StockMovement::create([
                'company_id' => $this->getCompanyId(),
                'item_id' => $validated['item_id'],
                'movement_type' => $movementType,
                'quantity' => $quantityDifference,
                'unit_cost' => $unitCost,
                'total_value' => abs($quantityDifference) * $unitCost,
                'source_warehouse_id' => $quantityDifference < 0 ? $validated['warehouse_id'] : null,
                'destination_warehouse_id' => $quantityDifference >= 0 ? $validated['warehouse_id'] : null,
                'source_bin_id' => $quantityDifference < 0 ? $validated['bin_id'] : null,
                'destination_bin_id' => $quantityDifference >= 0 ? $validated['bin_id'] : null,
                'reference_number' => 'ADJ-' . now()->format('YmdHis'),
                'notes' => $validated['notes'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'completed',
                'created_by' => Auth::guard('tenant')->user()->id,
                'movement_date' => now(),
            ]);

            // Update stock valuation
            if ($currentStock) {
                // Update existing stock
                $currentStock->update([
                    'quantity_on_hand' => $validated['new_quantity'],
                    'total_value' => $validated['new_quantity'] * $unitCost,
                    'last_issue_date' => now(),
                ]);
            } else {
                // Create new stock valuation
                StockValuation::create([
                    'company_id' => $this->getCompanyId(),
                    'item_id' => $validated['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'bin_id' => $validated['bin_id'] ?? null,
                    'quantity_on_hand' => $validated['new_quantity'],
                    'quantity_reserved' => 0,
                    'average_cost' => $unitCost,
                    'total_value' => $validated['new_quantity'] * $unitCost,
                    'last_purchase_cost' => $unitCost,
                    'last_receipt_date' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => [
                    'movement' => $movement->load(['item', 'sourceWarehouse', 'destinationWarehouse']),
                    'previous_quantity' => $currentQuantity,
                    'new_quantity' => $validated['new_quantity'],
                    'difference' => $quantityDifference
                ]
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
                'message' => 'Failed to adjust stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update stock valuation
     */
    private function updateStockValuation($itemId, $warehouseId, $binId, $quantity, $unitCost, $direction)
    {
        $stockValuation = StockValuation::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->first();

        if ($stockValuation) {
            // Update existing stock
            $newQuantity = $stockValuation->quantity_on_hand + $quantity;
            
            // Calculate new average cost  
            $currentValue = $stockValuation->total_value;
            $movementValue = abs($quantity) * $unitCost;
            
            if ($direction === 'in') {
                $newAverageCost = ($currentValue + $movementValue) / ($stockValuation->quantity_on_hand + $quantity);
                $newTotalValue = $currentValue + $movementValue;
                $lastReceiptDate = now();
                $lastIssueDate = $stockValuation->last_issue_date;
            } else {
                $newAverageCost = $stockValuation->average_cost;  
                $newTotalValue = $newQuantity * $newAverageCost;
                $lastReceiptDate = $stockValuation->last_receipt_date;
                $lastIssueDate = now();
            }

            $stockValuation->update([
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
                'total_value' => $newTotalValue,
                'last_purchase_cost' => $direction === 'in' ? $unitCost : $stockValuation->last_purchase_cost,
                'last_receipt_date' => $lastReceiptDate,
                'last_issue_date' => $lastIssueDate,
                'last_receipt_quantity' => $direction === 'in' ? $quantity : $stockValuation->last_receipt_quantity,
                'last_issue_quantity' => $direction === 'out' ? abs($quantity) : $stockValuation->last_issue_quantity,
            ]);
        } else {
           
            if ($direction === 'in') {
                StockValuation::create([
                    'company_id' => $this->getCompanyId(),
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'bin_id' => $binId,
                    'quantity_on_hand' => $quantity,
                    'quantity_reserved' => 0,
                    'average_cost' => $unitCost,
                    'total_value' => $quantity * $unitCost,
                    'last_purchase_cost' => $unitCost,
                    'last_receipt_date' => now(),
                    'last_receipt_quantity' => $quantity,
                ]);
            }
        }
    }
}