<?php
 namespace App\Http\Controllers\Inventory;

use App\Models\Tenant\Part;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Tenant\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\PartsPurchaseOrder;
use App\Models\Tenant\PartsPurchaseOrderLine;

class PartsPurchaseOrderController extends Controller
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
    public function index(Request $request): JsonResponse
    {
        try {
            $purchaseOrders = PartsPurchaseOrder::with(['supplier', 'warehouse', 'lines.part'])
                ->byCompany($this->getCompanyId())
                ->when($request->has('status'), function($query) use ($request) {
                    return $query->where('status', $request->status);
                })
                ->when($request->has('supplier_id'), function($query) use ($request) {
                    return $query->where('supplier_id', $request->supplier_id);
                })
                ->when($request->has('date_from') && $request->has('date_to'), function($query) use ($request) {
                    return $query->whereBetween('order_date', [
                        $request->date_from,
                        $request->date_to
                    ]);
                })
                ->orderBy('order_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $purchaseOrders
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'order_date' => 'required|date',
                'expected_delivery_date' => 'required|date|after_or_equal:order_date',
                'shipping_cost' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'shipping_address' => 'nullable|string',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.part_id' => 'required|exists:parts,id',
                'lines.*.quantity_ordered' => 'required|numeric|min:0.001',
                'lines.*.unit_price' => 'required|numeric|min:0',
            ]);
    
            DB::beginTransaction();
    
            // Create purchase order - FIXED: Use null coalescing for optional fields
            $purchaseOrder = PartsPurchaseOrder::create([
                'company_id' => $this->getCompanyId(),
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'],
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'shipping_address' => $validated['shipping_address'] ?? null, // ✅ Fixed
                'notes' => $validated['notes'] ?? null, // ✅ Fixed
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);
    
            // Create order lines
            foreach ($validated['lines'] as $lineData) {
                PartsPurchaseOrderLine::create([
                    'company_id' => $this->getCompanyId(),
                    'purchase_order_id' => $purchaseOrder->id,
                    'part_id' => $lineData['part_id'],
                    'quantity_ordered' => $lineData['quantity_ordered'],
                    'unit_price' => $lineData['unit_price'],
                    'line_total' => $lineData['quantity_ordered'] * $lineData['unit_price'],
                ]);
            }
    
            // Update totals
            $purchaseOrder->updateTotals();
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder->load(['supplier', 'warehouse', 'lines.part'])
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
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function show($id): JsonResponse
    {
        try {
            $purchaseOrder = PartsPurchaseOrder::with([
                'supplier', 
                'warehouse', 
                'lines.part',
                'creator'
            ])
            ->byCompany($this->getCompanyId())
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function receiveOrder(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'receipts' => 'required|array|min:1',
                'receipts.*.line_id' => 'required|exists:parts_purchase_order_lines,id',
                'receipts.*.quantity_received' => 'required|numeric|min:0.001',
                'receipts.*.batch_number' => 'nullable|string|max:100',
                'receipts.*.expiry_date' => 'nullable|date',
                'document_number' => 'required|string|max:100',
                'receipt_date' => 'required|date',
                'notes' => 'nullable|string',
            ]);

            $purchaseOrder = PartsPurchaseOrder::with(['lines'])
                ->byCompany($this->getCompanyId())
                ->findOrFail($id);

            // Check if PO can be received
            if (!in_array($purchaseOrder->status, ['ordered', 'partially_received'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot receive against PO with status: ' . $purchaseOrder->status
                ], 422);
            }

            DB::beginTransaction();

            $receivedLines = [];

            // Process each receipt line
            foreach ($validated['receipts'] as $receiptData) {
                $line = $purchaseOrder->lines->find($receiptData['line_id']);
                
                if (!$line) {
                    throw new \Exception("Line not found in this purchase order");
                }

                $pendingQuantity = $line->getPendingQuantity();
                
                if ($receiptData['quantity_received'] > $pendingQuantity) {
                    throw new \Exception("Cannot receive more than pending quantity for part: " . 
                        $line->part->name . ". Pending: " . $pendingQuantity);
                }

                // Update PO line
                $line->receiveQuantity($receiptData['quantity_received']);
                
                // Create stock movement via parts-movements API
                $movementData = [
                    'warehouse_id' => $purchaseOrder->warehouse_id,
                    'reference_type' => 'purchase_order',
                    'reference_id' => $purchaseOrder->id,
                    'document_number' => $validated['document_number'],
                    'movement_date' => $validated['receipt_date'],
                    'notes' => $validated['notes'] . " | PO: " . $purchaseOrder->po_number,
                    'lines' => [
                        [
                            'part_id' => $line->part_id,
                            'quantity' => $receiptData['quantity_received'],
                            'unit_cost' => $line->unit_price,
                            'batch_number' => $receiptData['batch_number'] ?? null,
                            'expiry_date' => $receiptData['expiry_date'] ?? null,
                        ]
                    ]
                ];

                // Call your existing parts-movements receipt API
                $receivedLines[] = [
                    'line' => $line,
                    'quantity_received' => $receiptData['quantity_received'],
                    'movement_data' => $movementData
                ];
            }

            // Update PO status
            $purchaseOrder->updateStatus();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order received successfully',
                'data' => [
                    'purchase_order' => $purchaseOrder->fresh(['lines.part']),
                    'received_lines' => $receivedLines
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
                'message' => 'Failed to receive purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}