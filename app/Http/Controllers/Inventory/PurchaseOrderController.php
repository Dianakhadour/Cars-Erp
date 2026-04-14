<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\PurchaseOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
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
     * List all purchase orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PurchaseOrder::with(['supplier', 'creator', 'approver', 'lines.item'])
                ->where('company_id', $this->getCompanyId())
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by supplier
            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('po_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('po_date', '<=', $request->end_date);
            }

            // Search by PO number
            if ($request->has('search')) {
                $query->where('po_number', 'like', "%{$request->search}%");
            }

            $purchaseOrders = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $purchaseOrders->items(),
                'meta' => [
                    'current_page' => $purchaseOrders->currentPage(),
                    'per_page' => $purchaseOrders->perPage(),
                    'total' => $purchaseOrders->total(),
                    'last_page' => $purchaseOrders->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new purchase order
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,id',
                'po_date' => 'required|date',
                'expected_delivery_date' => 'required|date|after_or_equal:po_date',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.item_id' => 'required|exists:items,id',
                'lines.*.quantity_ordered' => 'required|numeric|min:0.001',
                'lines.*.unit_price' => 'required|numeric|min:0',
                'lines.*.description' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Generate PO number
            $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(PurchaseOrder::where('company_id', $this->getCompanyId())->count() + 1, 4, '0', STR_PAD_LEFT);

            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'company_id' => $this->getCompanyId(),
                'po_number' => $poNumber,
                'supplier_id' => $validated['supplier_id'],
                'po_date' => $validated['po_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::guard('tenant')->user()->id,
                'total_amount' => 0, // Will be calculated from lines
            ]);

            $totalAmount = 0;

            // Create purchase order lines
            foreach ($validated['lines'] as $line) {
                $lineTotal = $line['quantity_ordered'] * $line['unit_price'];
                $totalAmount += $lineTotal;

                PurchaseOrderLine::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'item_id' => $line['item_id'],
                    'quantity_ordered' => $line['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                    'description' => $line['description'] ?? null,
                ]);
            }

            // Update total amount
            $purchaseOrder->update(['total_amount' => $totalAmount]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder->load(['supplier', 'lines.item'])
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

    /**
     * Get purchase order details
     */
    public function show(string $id): JsonResponse
    { 
        try {
            $purchaseOrder = PurchaseOrder::with([
                'supplier', 
                'creator', 
                'approver', 
                'lines.item',
                //'goodsReceipts'
            ])
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update purchase order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->where('status', 'draft') 
                ->findOrFail($id);

            $validated = $request->validate([
                'supplier_id' => 'sometimes|exists:suppliers,id',
                'po_date' => 'sometimes|date',
                'expected_delivery_date' => 'sometimes|date|after_or_equal:po_date',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $purchaseOrder->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order updated successfully',
                'data' => $purchaseOrder->load(['supplier', 'lines.item'])
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
                'message' => 'Purchase order not found or not in draft status'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update PO status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:approved,cancelled',
                'notes' => 'nullable|string',
            ]);

            $purchaseOrder = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->findOrFail($id);

            DB::beginTransaction();

            $oldStatus = $purchaseOrder->status;
            $newStatus = $validated['status'];

            // Validate status transition
            if ($oldStatus !== 'draft' && $newStatus === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft purchase orders can be approved'
                ], 422);
            }

            $updateData = ['status' => $newStatus];
            
            if ($newStatus === 'approved') {
                $updateData['approved_by'] = Auth::guard('tenant')->user()->id;
                $updateData['approved_at'] = now();
            }

            $purchaseOrder->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase order status updated successfully',
                'data' => [
                    'purchase_order' => $purchaseOrder->load(['supplier', 'approver']),
                    'status_change' => [
                        'from' => $oldStatus,
                        'to' => $newStatus,
                        'approved_by' => $newStatus === 'approved' ? Auth::guard('tenant')->user()->name : null,
                        'approved_at' => $newStatus === 'approved' ? now() : null,
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
                'message' => 'Purchase order not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}