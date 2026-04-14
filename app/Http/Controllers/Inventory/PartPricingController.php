<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Part;
use App\Models\Tenant\PartPricing;
use App\Services\Inventory\CostCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PartPricingController extends Controller
{
    protected $costCalculationService;

    public function __construct(CostCalculationService $costCalculationService)
    {
        $this->costCalculationService = $costCalculationService;
    }

    /**
     * Get part pricing
     * 
     * GET /api/inventory/parts/{id}/pricing
     */
    public function getPricing($id): JsonResponse
    {
        try {
            $part = Part::with([
                'stockValuations',
                'purchaseOrderLines' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(5);
                }
            ])->findOrFail($id);

            $pricingData = [
                'part_id' => $part->id,
                'current_pricing' => [
                    'cost_price' => (float) $part->cost_price,
                    'selling_price' => (float) $part->selling_price,
                    'retail_price' => (float) $part->retail_price,
                ],
                'stock_valuation' => $part->stockValuations->map(function($valuation) {
                    return [
                        'warehouse_id' => $valuation->warehouse_id,
                        'quantity_on_hand' => (float) $valuation->quantity_on_hand,
                        'average_cost' => (float) $valuation->average_cost,
                        'total_value' => (float) $valuation->total_value,
                    ];
                }),
                'recent_purchase_costs' => $part->purchaseOrderLines->map(function($line) {
                    return [
                        'purchase_order_id' => $line->purchase_order_id,
                        'unit_price' => (float) $line->unit_price,
                        'order_date' => $line->purchaseOrder->order_date,
                    ];
                }),
                'pricing_history' => PartPricing::where('part_id', $part->id)
                    ->orderBy('effective_from', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function($pricing) {
                        return [
                            'pricing_type' => $pricing->pricing_type,
                            'price' => (float) $pricing->price,
                            'effective_from' => $pricing->effective_from,
                            'effective_to' => $pricing->effective_to,
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $pricingData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve part pricing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update part pricing
     * 
     * PUT /api/inventory/parts/{id}/pricing
     */
    public function updatePricing(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cost_price' => 'sometimes|numeric|min:0',
                'selling_price' => 'sometimes|numeric|min:0',
                'retail_price' => 'sometimes|numeric|min:0',
                'update_reason' => 'required|string|max:255',
                'effective_date' => 'sometimes|date',
            ]);

            $part = Part::findOrFail($id);

            DB::transaction(function() use ($part, $validated) {
                // Archive current pricing
                if ($part->selling_price > 0) {
                    PartPricing::create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'company_id' => $part->company_id,
                        'part_id' => $part->id,
                        'pricing_type' => 'selling_price',
                        'price' => $part->selling_price,
                        'currency' => 'SAR',
                        'effective_from' => now()->subDays(30),
                        'effective_to' => now(),
                        'is_active' => false,
                    ]);
                }

                // Update part pricing
                $updateData = [];
                if (isset($validated['cost_price'])) {
                    $updateData['cost_price'] = $validated['cost_price'];
                }
                if (isset($validated['selling_price'])) {
                    $updateData['selling_price'] = $validated['selling_price'];
                }
                if (isset($validated['retail_price'])) {
                    $updateData['retail_price'] = $validated['retail_price'];
                }

                $part->update($updateData);

                // Create new pricing record
                if (isset($validated['selling_price'])) {
                    PartPricing::create([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'company_id' => $part->company_id,
                        'part_id' => $part->id,
                        'pricing_type' => 'selling_price',
                        'price' => $validated['selling_price'],
                        'currency' => 'SAR',
                        'effective_from' => $validated['effective_date'] ?? now(),
                        'is_active' => true,
                    ]);
                }

                // Log the pricing change
                // \App\Models\Tenant\AuditLog::create([
                //     'id' => \Illuminate\Support\Str::uuid(),
                //     'actor_user_id' => auth()->id(),
                //     'company_id' => $part->company_id,
                //     'action' => 'part_pricing_updated',
                //     'payload' => json_encode([
                //         'part_id' => $part->id,
                //         'part_sku' => $part->sku,
                //         'old_pricing' => [
                //             'cost_price' => $part->getOriginal('cost_price'),
                //             'selling_price' => $part->getOriginal('selling_price'),
                //             'retail_price' => $part->getOriginal('retail_price'),
                //         ],
                //         'new_pricing' => $updateData,
                //         'reason' => $validated['update_reason'],
                //     ]),
                // ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Part pricing updated successfully',
                'data' => $part->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update part pricing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update part cost (landed cost)
     * 
     * POST /api/inventory/parts/{id}/update-cost
     */
    public function updateCost(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'landed_cost' => 'required|numeric|min:0',
                'cost_components' => 'required|array',
                'cost_components.*.type' => 'required|string',
                'cost_components.*.amount' => 'required|numeric|min:0',
                'purchase_order_id' => 'sometimes|exists:parts_purchase_orders,id',
                'notes' => 'sometimes|string|max:500',
            ]);

            $part = Part::findOrFail($id);

            DB::transaction(function() use ($part, $validated) {
                $oldCost = $part->cost_price;
                
                // Update part cost
                $part->update([
                    'cost_price' => $validated['landed_cost'],
                    'last_purchase_cost' => $validated['landed_cost'],
                ]);

                // Update stock valuations with new cost
                foreach ($part->stockValuations as $valuation) {
                    $valuation->update([
                        'average_cost' => $validated['landed_cost'],
                        'total_value' => $valuation->quantity_on_hand * $validated['landed_cost'],
                    ]);
                }

                // Log cost update
                // \App\Models\Tenant\AuditLog::create([
                //     'id' => \Illuminate\Support\Str::uuid(),
                //     'actor_user_id' => auth()->id(),
                //     'company_id' => $part->company_id,
                //     'action' => 'part_cost_updated',
                //     'payload' => json_encode([
                //         'part_id' => $part->id,
                //         'part_sku' => $part->sku,
                //         'old_cost' => $oldCost,
                //         'new_cost' => $validated['landed_cost'],
                //         'cost_components' => $validated['cost_components'],
                //         'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                //         'notes' => $validated['notes'] ?? null,
                //     ]),
                // ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Part cost updated successfully',
                'data' => [
                    'part_id' => $part->id,
                    'sku' => $part->sku,
                    'new_cost_price' => $part->cost_price,
                    'stock_valuations_updated' => $part->stockValuations->count(),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update part cost: ' . $e->getMessage()
            ], 500);
        }
    }
}