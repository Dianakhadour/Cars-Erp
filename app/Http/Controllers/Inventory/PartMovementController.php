<?php
 
namespace App\Http\Controllers\Inventory;

use App\Models\Tenant\PartStockValuation;
use App\Models\Tenant\Part;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Tenant\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\PartMovementLine;
use App\Models\Tenant\PartStockMovement;

class PartMovementController extends Controller
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
            $movements = PartStockMovement::with([
                'part', 
                'warehouse', 
                'sourceWarehouse', 
                'destinationWarehouse',
                'creator',
                'lines' => function($query) {
                    $query->with(['part', 'warehouse']);
                }
            ])
            ->byCompany($this->getCompanyId())
            ->when($request->has('movement_type'), function($query) use ($request) {
                return $query->where('movement_type', $request->movement_type);
            })
            ->when($request->has('status'), function($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->has('warehouse_id'), function($query) use ($request) {
                return $query->where('warehouse_id', $request->warehouse_id);
            })
            ->when($request->has('part_id'), function($query) use ($request) {
                return $query->where('part_id', $request->part_id);
            })
            ->when($request->has('date_from') && $request->has('date_to'), function($query) use ($request) {
                return $query->whereBetween('movement_date', [
                    $request->date_from,
                    $request->date_to
                ]);
            })
            ->orderBy('movement_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $movements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch part movements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    { 
        try {
            $movement = PartStockMovement::with([
                'part', 
                'warehouse', 
                'sourceWarehouse', 
                'destinationWarehouse',
                'creator',
                'lines' => function($query) {
                    $query->with(['part', 'part.category', 'warehouse']);
                }
            ]) 
            ->byCompany($this->getCompanyId())
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $movement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Movement not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function receiveParts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'reference_type' => 'required|in:purchase_order,manual,return',
                'reference_id' => 'nullable|string',
                'document_number' => 'required|string|max:100',
                'movement_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.part_id' => 'required|exists:parts,id',
                'lines.*.quantity' => 'required|numeric|min:0.001',
                'lines.*.unit_cost' => 'required|numeric|min:0',
                'lines.*.batch_number' => 'nullable|string|max:100',
                'lines.*.serial_number' => 'nullable|string|max:100',
                'lines.*.expiry_date' => 'nullable|date',
            ]);

            DB::beginTransaction();

            $movement = PartStockMovement::create([
                'company_id' => $this->getCompanyId(),
                'part_id' => $validated['lines'][0]['part_id'],  
                'warehouse_id' => $validated['warehouse_id'],
                'movement_type' => 'receipt',
                'quantity' => collect($validated['lines'])->sum('quantity'),
                'unit_cost' => collect($validated['lines'])->avg('unit_cost'),
                'total_cost' => collect($validated['lines'])->sum(function($line) {
                    return $line['quantity'] * $line['unit_cost'];
                }),
                'reference_type' => $validated['reference_type'],
                'reference_id' => $validated['reference_id'],
                'document_number' => $validated['document_number'],
                'status' => 'completed',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
                'movement_date' => $validated['movement_date'] ?? now(),
            ]);

            // Create movement lines
            foreach ($validated['lines'] as $lineData) {
                PartMovementLine::create([
                    'company_id' => $this->getCompanyId(),
                    'movement_id' => $movement->id,
                    'part_id' => $lineData['part_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_cost' => $lineData['unit_cost'],
                    'total_cost' => $lineData['quantity'] * $lineData['unit_cost'],
                    'batch_number' => $lineData['batch_number'] ?? null,
                    'serial_number' => $lineData['serial_number'] ?? null,
                    'expiry_date' => $lineData['expiry_date'] ?? null,
                    'warehouse_id' => $validated['warehouse_id'],
                    'status' => 'completed',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parts received successfully',
                'data' => $movement->load(['lines.part', 'warehouse'])
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
                'message' => 'Failed to receive parts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function issueParts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'reference_type' => 'required|in:job_card,sales_order,workshop,manual',
                'reference_id' => 'nullable|string',
                'document_number' => 'required|string|max:100',
                'movement_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.part_id' => 'required|exists:parts,id',
                'lines.*.quantity' => 'required|numeric|min:0.001',
                'lines.*.unit_cost' => 'nullable|numeric|min:0',
            ]);

            DB::beginTransaction();

           
            foreach ($validated['lines'] as $lineData) {
                $stock = PartStockValuation::where([
                    'part_id' => $lineData['part_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'company_id' => $this->getCompanyId(),
                ])->first();

                if (!$stock || $stock->quantity_available < $lineData['quantity']) {
                    $part = Part::find($lineData['part_id']);
                    throw new \Exception("Insufficient stock for part: {$part->sku}. Available: " . ($stock->quantity_available ?? 0));
                }
            }

            $movement = PartStockMovement::create([
                'company_id' => $this->getCompanyId(),
                'part_id' => $validated['lines'][0]['part_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'movement_type' => 'issue',
                'quantity' => collect($validated['lines'])->sum('quantity'),
                'unit_cost' => collect($validated['lines'])->avg('unit_cost'),
                'total_cost' => collect($validated['lines'])->sum(function($line) {
                    return $line['quantity'] * ($line['unit_cost'] ?? 0);
                }),
                'reference_type' => $validated['reference_type'],
                'reference_id' => $validated['reference_id'],
                'document_number' => $validated['document_number'],
                'status' => 'completed',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
                'movement_date' => $validated['movement_date'] ?? now(),
            ]);

            // Create movement lines
            foreach ($validated['lines'] as $lineData) {
                 
                $unitCost = $lineData['unit_cost'] ?? $this->getPartCurrentCost($lineData['part_id'], $validated['warehouse_id']);

                PartMovementLine::create([
                    'company_id' => $this->getCompanyId(),
                    'movement_id' => $movement->id,
                    'part_id' => $lineData['part_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineData['quantity'] * $unitCost,
                    'warehouse_id' => $validated['warehouse_id'],
                    'status' => 'completed',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parts issued successfully',
                'data' => $movement->load(['lines.part', 'warehouse'])
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
                'message' => 'Failed to issue parts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function transferParts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'source_warehouse_id' => 'required|exists:warehouses,id',
                'destination_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
                'reference_type' => 'nullable|in:internal_transfer,stock_replenishment',
                'reference_id' => 'nullable|string',
                'document_number' => 'required|string|max:100',
                'movement_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.part_id' => 'required|exists:parts,id',
                'lines.*.quantity' => 'required|numeric|min:0.001',
            ]);

            DB::beginTransaction();

            
            foreach ($validated['lines'] as $lineData) {
                $stock = PartStockValuation::where([
                    'part_id' => $lineData['part_id'],
                    'warehouse_id' => $validated['source_warehouse_id'],
                    'company_id' => $this->getCompanyId(),
                ])->first();

                if (!$stock || $stock->quantity_available < $lineData['quantity']) {
                    $part = Part::find($lineData['part_id']);
                    throw new \Exception("Insufficient stock in source warehouse for part: {$part->sku}. Available: " . ($stock->quantity_available ?? 0));
                }
            }

            $movement = PartStockMovement::create([
                'company_id' => $this->getCompanyId(),
                'part_id' => $validated['lines'][0]['part_id'],
                'warehouse_id' => $validated['source_warehouse_id'],  
                'movement_type' => 'transfer',
                'quantity' => collect($validated['lines'])->sum('quantity'),
                'unit_cost' => collect($validated['lines'])->avg(function($line) use ($validated) {
                    return $this->getPartCurrentCost($line['part_id'], $validated['source_warehouse_id']);
                }),
                'total_cost' => collect($validated['lines'])->sum(function($line) use ($validated) {
                    $cost = $this->getPartCurrentCost($line['part_id'], $validated['source_warehouse_id']);
                    return $line['quantity'] * $cost;
                }),
                'reference_type' => $validated['reference_type'],
                'reference_id' => $validated['reference_id'],
                'document_number' => $validated['document_number'],
                'source_location' => $validated['source_warehouse_id'],
                'destination_location' => $validated['destination_warehouse_id'],
                'status' => 'completed',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
                'movement_date' => $validated['movement_date'] ?? now(),
            ]);

            
            foreach ($validated['lines'] as $lineData) {
                $unitCost = $this->getPartCurrentCost($lineData['part_id'], $validated['source_warehouse_id']);

                
                PartMovementLine::create([
                    'company_id' => $this->getCompanyId(),
                    'movement_id' => $movement->id,
                    'part_id' => $lineData['part_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineData['quantity'] * $unitCost,
                    'warehouse_id' => $validated['source_warehouse_id'],
                    'status' => 'completed',
                    'notes' => 'Transfer out to ' . Warehouse::find($validated['destination_warehouse_id'])->name,
                ]);

                
                PartMovementLine::create([
                    'company_id' => $this->getCompanyId(),
                    'movement_id' => $movement->id,
                    'part_id' => $lineData['part_id'],
                    'quantity' => $lineData['quantity'],
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineData['quantity'] * $unitCost,
                    'warehouse_id' => $validated['destination_warehouse_id'],
                    'status' => 'completed',
                    'notes' => 'Transfer in from ' . Warehouse::find($validated['source_warehouse_id'])->name,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parts transferred successfully',
                'data' => $movement->load(['lines.part', 'sourceWarehouse', 'destinationWarehouse'])
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
                'message' => 'Failed to transfer parts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adjustStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'reference_type' => 'nullable|in:physical_count,quality_adjustment,write_off',
                'reference_id' => 'nullable|string',
                'document_number' => 'required|string|max:100',
                'movement_date' => 'nullable|date',
                'notes' => 'nullable|string',
                'lines' => 'required|array|min:1',
                'lines.*.part_id' => 'required|exists:parts,id',
                'lines.*.quantity' => 'required|numeric|min:0',
                'lines.*.adjustment_type' => 'required|in:set,add,subtract',
                'lines.*.reason' => 'required|string|max:500',
            ]);

            DB::beginTransaction();

            $movement = PartStockMovement::create([
                'company_id' => $this->getCompanyId(),
                'part_id' => $validated['lines'][0]['part_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'movement_type' => 'adjustment',
                'quantity' => collect($validated['lines'])->sum('quantity'),
                'unit_cost' => 0,  
                'total_cost' => 0,
                'reference_type' => $validated['reference_type'],
                'reference_id' => $validated['reference_id'],
                'document_number' => $validated['document_number'],
                'status' => 'completed',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
                'movement_date' => $validated['movement_date'] ?? now(),
            ]);

            foreach ($validated['lines'] as $lineData) {
                $currentStock = PartStockValuation::firstOrCreate([
                    'part_id' => $lineData['part_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'company_id' => $this->getCompanyId(),
                ], [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'average_cost' => $this->getPartCurrentCost($lineData['part_id'], $validated['warehouse_id']),
                ]);

                $oldQuantity = $currentStock->quantity_on_hand;
                
                
                switch ($lineData['adjustment_type']) {
                    case 'set':
                        $newQuantity = $lineData['quantity'];
                        break;
                    case 'add':
                        $newQuantity = $oldQuantity + $lineData['quantity'];
                        break;
                    case 'subtract':
                        $newQuantity = max(0, $oldQuantity - $lineData['quantity']);
                        break;
                }

                PartMovementLine::create([
                    'company_id' => $this->getCompanyId(),
                    'movement_id' => $movement->id,
                    'part_id' => $lineData['part_id'],
                    'quantity' => $newQuantity,  
                    'unit_cost' => $currentStock->average_cost,
                    'total_cost' => $newQuantity * $currentStock->average_cost,
                    'warehouse_id' => $validated['warehouse_id'],
                    'status' => 'completed',
                    'notes' => "Stock adjustment: {$lineData['reason']}. Old: {$oldQuantity}, New: {$newQuantity}",
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => $movement->load(['lines.part', 'warehouse'])
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

     
    private function getPartCurrentCost($partId, $warehouseId)
    {
        $valuation = PartStockValuation::where([
            'part_id' => $partId,
            'warehouse_id' => $warehouseId,
            'company_id' => $this->getCompanyId(),
        ])->first();

        if ($valuation && $valuation->average_cost > 0) {
            return $valuation->average_cost;
        }

        $part = Part::find($partId);
        return $part->cost_price;
    }
}