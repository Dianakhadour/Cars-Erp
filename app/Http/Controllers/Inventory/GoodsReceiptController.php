<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\GoodsReceiptLine;
use App\Models\Tenant\PurchaseOrderLine;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\StockValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
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
     * Receive vehicles against PO
     */
    public function receiveGoods(Request $request, string $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->whereIn('status', ['approved', 'partially_received'])  
                ->findOrFail($id);

            $validated = $request->validate([
                'receipt_date' => 'required|date',
                'warehouse_id' => 'required|exists:warehouses,id',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.purchase_order_line_id' => 'required|exists:purchase_order_lines,id',
                'lines.*.quantity_received' => 'required|numeric|min:0.001',
                'lines.*.unit_cost' => 'required|numeric|min:0',
                'lines.*.bin_id' => 'nullable|exists:bins,id',
                'lines.*.notes' => 'nullable|string',
                'lines.*.vehicle_attributes' => 'nullable|array',
            ]);

            DB::beginTransaction();

            // Generate GRN number
            $grnNumber = 'GRN-' . date('Ymd') . '-' . str_pad(GoodsReceipt::where('company_id', $this->getCompanyId())->count() + 1, 4, '0', STR_PAD_LEFT);

            // Create goods receipt
            $goodsReceipt = GoodsReceipt::create([
                'company_id' => $this->getCompanyId(),
                'purchase_order_id' => $purchaseOrder->id,
                'grn_number' => $grnNumber,
                'receipt_date' => $validated['receipt_date'],
                'warehouse_id' => $validated['warehouse_id'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',  
                'total_received_value' => 0, 
                'received_by' => Auth::guard('tenant')->user()->id,
            ]);

            $totalReceivedValue = 0;

            
            foreach ($validated['lines'] as $lineData) {
                $poLine = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)
                    ->findOrFail($lineData['purchase_order_line_id']);

                 
                $remainingQuantity = $poLine->quantity_ordered - $poLine->quantity_received;
                if ($lineData['quantity_received'] > $remainingQuantity) {
                    throw new \Exception("Received quantity exceeds remaining quantity for item: {$poLine->item->name}. Remaining: {$remainingQuantity}, Received: {$lineData['quantity_received']}");
                }

                $lineTotal = $lineData['quantity_received'] * $lineData['unit_cost'];
                $totalReceivedValue += $lineTotal;

                // Create goods receipt line
                $grLine = GoodsReceiptLine::create([
                    'goods_receipt_id' => $goodsReceipt->id,
                    'purchase_order_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'quantity_received' => $lineData['quantity_received'],
                    'unit_cost' => $lineData['unit_cost'],
                    'line_total' => $lineTotal,
                    'bin_id' => $lineData['bin_id'] ?? null,
                    'notes' => $lineData['notes'] ?? null,
                    'vehicle_attributes' => $lineData['vehicle_attributes'] ?? null,
                ]);

         
                $poLine->increment('quantity_received', $lineData['quantity_received']);

                // Create stock movement
                $this->createStockMovement($grLine, $validated['warehouse_id'], $lineData['bin_id'] ?? null);

                // Update stock valuation
                $this->updateStockValuation($grLine, $validated['warehouse_id'], $lineData['bin_id'] ?? null);
            }

            // Update goods receipt total value
            $goodsReceipt->update(['total_received_value' => $totalReceivedValue]);

            // Update purchase order status and received amount
            $this->updatePurchaseOrderStatus($purchaseOrder);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Goods received successfully',
                'data' => $goodsReceipt->load(['lines.purchaseOrderLine.item', 'warehouse', 'receiver'])
            ], 201);

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
                'message' => 'Purchase order not found or not approved'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive goods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List goods receipts for PO
     */
    public function listReceipts(string $id, Request $request): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->findOrFail($id);

            $query = GoodsReceipt::with(['warehouse', 'receiver', 'lines.purchaseOrderLine.item'])
                ->where('purchase_order_id', $purchaseOrder->id)
                ->orderBy('created_at', 'desc');

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('receipt_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('receipt_date', '<=', $request->end_date);
            }

            $receipts = $query->paginate($request->get('per_page', 20));

            
            $receiptSummary = [
                'total_receipts' => $receipts->total(),
                'total_received_value' => GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)
                    ->sum('total_received_value'),
                'total_vehicles_received' => GoodsReceiptLine::whereIn('goods_receipt_id', 
                    GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->pluck('id'))
                    ->sum('quantity_received')
            ];

            return response()->json([
                'success' => true,
                'data' => $receipts->items(),
                'summary' => $receiptSummary,
                'meta' => [
                    'current_page' => $receipts->currentPage(),
                    'per_page' => $receipts->perPage(),
                    'total' => $receipts->total(),
                    'last_page' => $receipts->lastPage(),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goods receipts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get receipt details
     */
    public function getReceiptDetails(string $id, string $receiptId): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->findOrFail($id);

            $goodsReceipt = GoodsReceipt::with([
                'warehouse',
                'receiver',
                'approver',
                'lines.purchaseOrderLine.item',
                'lines.bin'
            ])
            ->where('purchase_order_id', $purchaseOrder->id)
            ->findOrFail($receiptId);

            // Get related stock movements
            $stockMovements = StockMovement::where('referenceable_type', GoodsReceiptLine::class)
                ->whereIn('referenceable_id', $goodsReceipt->lines->pluck('id'))
                ->with(['item', 'sourceWarehouse', 'destinationWarehouse'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'goods_receipt' => $goodsReceipt,
                    'stock_movements' => $stockMovements
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Goods receipt not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goods receipt details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create stock movement for goods receipt
     */
    private function createStockMovement(GoodsReceiptLine $grLine, $warehouseId, $binId = null): void
    {
        StockMovement::create([
            'company_id' => $this->getCompanyId(),
            'item_id' => $grLine->item_id,
            'movement_type' => 'purchase_receipt',
            'quantity' => $grLine->quantity_received,
            'unit_cost' => $grLine->unit_cost,
            'total_value' => $grLine->line_total,
            'destination_warehouse_id' => $warehouseId,
            'destination_bin_id' => $binId,
            'referenceable_id' => $grLine->id,
            'referenceable_type' => GoodsReceiptLine::class,
            'reference_number' => $grLine->goodsReceipt->grn_number,
            'notes' => "Goods receipt from purchase order",
            'vehicle_attributes' => $grLine->vehicle_attributes,
            'status' => 'completed',
            'created_by' => Auth::guard('tenant')->user()->id,
            'movement_date' => $grLine->goodsReceipt->receipt_date,
        ]);
    }

    /**
     * Update stock valuation
     */
    private function updateStockValuation(GoodsReceiptLine $grLine, $warehouseId, $binId = null): void
    {
        $stockValuation = StockValuation::where('item_id', $grLine->item_id)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->first();

        if ($stockValuation) {
            // Update existing stock (weighted average cost)
            $currentValue = $stockValuation->total_value;
            $newValue = $currentValue + $grLine->line_total;
            $newQuantity = $stockValuation->quantity_on_hand + $grLine->quantity_received;
            $newAverageCost = $newQuantity > 0 ? $newValue / $newQuantity : $grLine->unit_cost;

            $stockValuation->update([
                'quantity_on_hand' => $newQuantity,
                'average_cost' => $newAverageCost,
                'total_value' => $newValue,
                'last_purchase_cost' => $grLine->unit_cost,
                'last_receipt_date' => $grLine->goodsReceipt->receipt_date,
                'last_receipt_quantity' => $grLine->quantity_received,
            ]);
        } else {
            // Create new stock valuation
            StockValuation::create([
                'company_id' => $this->getCompanyId(),
                'item_id' => $grLine->item_id,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
                'quantity_on_hand' => $grLine->quantity_received,
                'quantity_reserved' => 0,
                'average_cost' => $grLine->unit_cost,
                'total_value' => $grLine->line_total,
                'last_purchase_cost' => $grLine->unit_cost,
                'last_receipt_date' => $grLine->goodsReceipt->receipt_date,
                'last_receipt_quantity' => $grLine->quantity_received,
            ]);
        }
    }

    /**
     * Update purchase order status
     */
    private function updatePurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $totalOrdered = $purchaseOrder->lines()->sum('quantity_ordered');
        $totalReceived = $purchaseOrder->lines()->sum('quantity_received');

        if ($totalReceived >= $totalOrdered) {
            $status = 'completed';
        } elseif ($totalReceived > 0) {
            $status = 'partially_received';
        } else {
            $status = 'approved';
        }

        $purchaseOrder->update([
            'status' => $status,
            'received_amount' => $purchaseOrder->goodsReceipts()->sum('total_received_value')
        ]);
    }
}