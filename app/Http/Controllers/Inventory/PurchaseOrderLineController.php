<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderLine;
use App\Models\Tenant\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderLineController extends Controller
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
     * Add vehicle to purchase order
     */
    public function store(Request $request, string $id): JsonResponse
    { 
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->where('status', 'draft')     
                ->findOrFail($id);
 
            $validated = $request->validate([
                'item_id' => 'required|exists:items,id',
                'quantity_ordered' => 'required|numeric|min:0.001',
                'unit_price' => 'required|numeric|min:0',
                'description' => 'nullable|string',
            ]);
 
            // Verify item is a vehicle
            $item = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($validated['item_id']);

            DB::beginTransaction();

            // Check if item already exists in PO lines
            $existingLine = $purchaseOrder->lines()
                ->where('item_id', $validated['item_id'])
                ->first();
 
            if ($existingLine) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle already exists in purchase order',
                    'data' => $existingLine
                ], 422);
            }

            $lineTotal = $validated['quantity_ordered'] * $validated['unit_price'];

            // Create purchase order line
            $line = PurchaseOrderLine::create([
                'purchase_order_id' => $purchaseOrder->id,
                'item_id' => $validated['item_id'],
                'quantity_ordered' => $validated['quantity_ordered'],
                'quantity_received' => 0,
                'unit_price' => $validated['unit_price'],
                'line_total' => $lineTotal,
                'description' => $validated['description'] ?? null,
            ]);

            // Update purchase order total amount
            $this->updatePurchaseOrderTotal($purchaseOrder);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle added to purchase order successfully',
                'data' => $line->load(['item'])
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
                'message' => 'Purchase order not found or not in draft status'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add vehicle to purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update PO line
     */
    public function update(Request $request, string $id, string $lineId): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->where('status', 'draft') // Only allow updating lines in draft POs
                ->findOrFail($id);

            $line = $purchaseOrder->lines()->findOrFail($lineId);

            $validated = $request->validate([
                'quantity_ordered' => 'sometimes|numeric|min:0.001',
                'unit_price' => 'sometimes|numeric|min:0',
                'description' => 'nullable|string',
            ]);

            // Check if quantity received is less than or equal to new quantity ordered
            if (isset($validated['quantity_ordered']) && $validated['quantity_ordered'] < $line->quantity_received) {
                return response()->json([
                    'success' => false,
                    'message' => 'New quantity cannot be less than already received quantity',
                    'received_quantity' => $line->quantity_received
                ], 422);
            }

            DB::beginTransaction();

            // Update line
            $updateData = [];
            if (isset($validated['quantity_ordered'])) {
                $updateData['quantity_ordered'] = $validated['quantity_ordered'];
            }
            if (isset($validated['unit_price'])) {
                $updateData['unit_price'] = $validated['unit_price'];
            }
            if (isset($validated['description'])) {
                $updateData['description'] = $validated['description'];
            }

            // Recalculate line total if quantity or price changed
            if (isset($validated['quantity_ordered']) || isset($validated['unit_price'])) {
                $newQuantity = $validated['quantity_ordered'] ?? $line->quantity_ordered;
                $newUnitPrice = $validated['unit_price'] ?? $line->unit_price;
                $updateData['line_total'] = $newQuantity * $newUnitPrice;
            }

            $line->update($updateData);

            // Update purchase order total amount
            $this->updatePurchaseOrderTotal($purchaseOrder);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order line updated successfully',
                'data' => $line->load(['item'])
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
                'message' => 'Purchase order line not found or purchase order not in draft status'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order line',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove vehicle from PO
     */
    public function destroy(string $id, string $lineId): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->where('status', 'draft') // Only allow deleting lines from draft POs
                ->findOrFail($id);

            $line = $purchaseOrder->lines()->findOrFail($lineId);

            // Check if line has received quantity
            if ($line->quantity_received > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove line with received quantity',
                    'received_quantity' => $line->quantity_received
                ], 422);
            }

            DB::beginTransaction();

            // Delete the line
            $line->delete();

            // Update purchase order total amount
            $this->updatePurchaseOrderTotal($purchaseOrder);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle removed from purchase order successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order line not found or purchase order not in draft status'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove vehicle from purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update purchase order total amount
     */
    private function updatePurchaseOrderTotal(PurchaseOrder $purchaseOrder): void
    {
        $totalAmount = $purchaseOrder->lines()->sum('line_total');
        $purchaseOrder->update(['total_amount' => $totalAmount]);
    }

    /**
     * Bulk update PO lines (optional - for future use)
     */
    public function bulkUpdate(Request $request, string $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->where('status', 'draft')
                ->findOrFail($id);

            $validated = $request->validate([
                'lines' => 'required|array|min:1',
                'lines.*.id' => 'required|exists:purchase_order_lines,id',
                'lines.*.quantity_ordered' => 'sometimes|numeric|min:0.001',
                'lines.*.unit_price' => 'sometimes|numeric|min:0',
                'lines.*.description' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $updatedLines = [];

            foreach ($validated['lines'] as $lineData) {
                $line = $purchaseOrder->lines()->findOrFail($lineData['id']);

                $updateData = [];
                if (isset($lineData['quantity_ordered'])) {
                    // Check if new quantity is not less than received quantity
                    if ($lineData['quantity_ordered'] < $line->quantity_received) {
                        throw new \Exception("Quantity cannot be less than received quantity for line ID: {$line->id}");
                    }
                    $updateData['quantity_ordered'] = $lineData['quantity_ordered'];
                }
                if (isset($lineData['unit_price'])) {
                    $updateData['unit_price'] = $lineData['unit_price'];
                }
                if (isset($lineData['description'])) {
                    $updateData['description'] = $lineData['description'];
                }

                // Recalculate line total if quantity or price changed
                if (isset($lineData['quantity_ordered']) || isset($lineData['unit_price'])) {
                    $newQuantity = $lineData['quantity_ordered'] ?? $line->quantity_ordered;
                    $newUnitPrice = $lineData['unit_price'] ?? $line->unit_price;
                    $updateData['line_total'] = $newQuantity * $newUnitPrice;
                }

                $line->update($updateData);
                $updatedLines[] = $line->load(['item']);
            }

            // Update purchase order total amount
            $this->updatePurchaseOrderTotal($purchaseOrder);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order lines updated successfully',
                'data' => $updatedLines
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
                'message' => 'Purchase order or line not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order lines',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}