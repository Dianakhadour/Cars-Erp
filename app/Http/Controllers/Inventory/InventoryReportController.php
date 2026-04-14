<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Part;
use App\Models\Tenant\PartStockValuation;
use App\Models\Tenant\PartStockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    /**
     * Parts inventory valuation report
     * 
     * GET /api/inventory/reports/parts-valuation
     */
    public function partsValuation(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'category_id' => 'sometimes|exists:part_categories,id',
                'as_of_date' => 'sometimes|date',
            ]);

            $query = PartStockValuation::with(['part', 'warehouse'])
                ->select([
                    'part_id',
                    'warehouse_id',
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(total_value) as total_value'),
                    DB::raw('AVG(average_cost) as avg_cost'),
                ])
                ->groupBy('part_id', 'warehouse_id');

            if (isset($validated['warehouse_id'])) {
                $query->where('warehouse_id', $validated['warehouse_id']);
            }

            if (isset($validated['as_of_date'])) {
                $query->where('updated_at', '<=', $validated['as_of_date']);
            }

            $valuations = $query->get()->map(function($valuation) {
                return [
                    'part_id' => $valuation->part_id,
                    'part_sku' => $valuation->part->sku,
                    'part_name' => $valuation->part->name,
                    'warehouse_id' => $valuation->warehouse_id,
                    'warehouse_name' => $valuation->warehouse->name,
                    'quantity_on_hand' => (float) $valuation->total_quantity,
                    'average_cost' => (float) $valuation->avg_cost,
                    'total_value' => (float) $valuation->total_value,
                    'selling_price' => (float) $valuation->part->selling_price,
                    'potential_revenue' => (float) $valuation->total_quantity * $valuation->part->selling_price,
                ];
            });

            $summary = [
                'total_items' => $valuations->count(),
                'total_quantity' => $valuations->sum('quantity_on_hand'),
                'total_inventory_value' => $valuations->sum('total_value'),
                'total_potential_revenue' => $valuations->sum('potential_revenue'),
                'average_cost' => $valuations->avg('average_cost'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'valuations' => $valuations,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate valuation report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parts reorder suggestions
     * 
     * GET /api/inventory/reports/parts-reorder-suggestions
     */
    public function partsReorderSuggestions(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'urgency' => 'sometimes|in:critical,high,medium,low',
            ]);

            // First, get all parts with their stock valuations
            $parts = Part::with(['stockValuations' => function($query) use ($validated) {
                    if (isset($validated['warehouse_id'])) {
                        $query->where('warehouse_id', $validated['warehouse_id']);
                    }
                }])
                ->where('is_active', true)
                ->get();

            $suggestions = collect();

            foreach ($parts as $part) {
                foreach ($part->stockValuations as $valuation) {
                    // Calculate urgency level
                    if ($valuation->quantity_on_hand <= $part->min_stock_level) {
                        $urgencyLevel = 'critical';
                    } elseif ($valuation->quantity_on_hand <= $part->reorder_point) {
                        $urgencyLevel = 'high';
                    } elseif ($valuation->quantity_on_hand <= ($part->reorder_point + (($part->max_stock_level - $part->reorder_point) * 0.3))) {
                        $urgencyLevel = 'medium';
                    } else {
                        $urgencyLevel = 'low';
                    }

                    // Skip if we're filtering by urgency and this doesn't match
                    if (isset($validated['urgency']) && $urgencyLevel !== $validated['urgency']) {
                        continue;
                    }

                    // Only suggest reorder if stock is below reorder point
                    if ($valuation->quantity_on_hand <= $part->reorder_point) {
                        $suggestedOrderQuantity = max(0, $part->max_stock_level - $valuation->quantity_on_hand);
                        
                        if ($suggestedOrderQuantity > 0) {
                            $suggestions->push([
                                'part_id' => $part->id,
                                'part_sku' => $part->sku,
                                'part_name' => $part->name,
                                'warehouse_id' => $valuation->warehouse_id,
                                'current_stock' => (float) $valuation->quantity_on_hand,
                                'min_stock_level' => (float) $part->min_stock_level,
                                'reorder_point' => (float) $part->reorder_point,
                                'max_stock_level' => (float) $part->max_stock_level,
                                'suggested_order_quantity' => (float) $suggestedOrderQuantity,
                                'urgency_level' => $urgencyLevel,
                                'estimated_cost' => (float) $suggestedOrderQuantity * $part->cost_price,
                                'last_movement_date' => $valuation->last_movement_at,
                                'is_low_stock' => $valuation->is_low_stock,
                                'is_out_of_stock' => $valuation->is_out_of_stock,
                            ]);
                        }
                    }
                }
            }

            // Sort by urgency (critical first, then high, then medium)
            $suggestions = $suggestions->sortBy(function($suggestion) {
                $urgencyOrder = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
                return $urgencyOrder[$suggestion['urgency_level']];
            })->values();

            $summary = [
                'total_suggestions' => $suggestions->count(),
                'critical_items' => $suggestions->where('urgency_level', 'critical')->count(),
                'high_priority_items' => $suggestions->where('urgency_level', 'high')->count(),
                'medium_priority_items' => $suggestions->where('urgency_level', 'medium')->count(),
                'total_estimated_cost' => $suggestions->sum('estimated_cost'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'suggestions' => $suggestions,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate reorder suggestions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parts movement summary
     * 
     * GET /api/inventory/reports/parts-movement-summary
     */
    public function partsMovementSummary(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'warehouse_id' => 'sometimes|exists:warehouses,id',
                'movement_type' => 'sometimes|in:receipt,issue,transfer,adjustment,reservation,allocation,return',
            ]);

            $query = PartStockMovement::with(['part', 'warehouse'])
                ->whereBetween('movement_date', [$validated['start_date'], $validated['end_date']])
                ->where('status', 'completed');

            if (isset($validated['warehouse_id'])) {
                $query->where('warehouse_id', $validated['warehouse_id']);
            }

            if (isset($validated['movement_type'])) {
                $query->where('movement_type', $validated['movement_type']);
            }

            $movements = $query->get();

            // Group by movement type and calculate totals
            $summaryByType = $movements->groupBy('movement_type')->map(function($typeMovements, $type) {
                return [
                    'movement_type' => $type,
                    'movement_count' => $typeMovements->count(),
                    'total_quantity' => (float) $typeMovements->sum('quantity'),
                    'total_value' => (float) $typeMovements->sum('total_cost'),
                ];
            })->values();

            // Get top moving parts
            $topMovingParts = $movements->groupBy('part_id')->map(function($partMovements, $partId) {
                $part = $partMovements->first()->part;
                return [
                    'part_id' => $partId,
                    'part_sku' => $part->sku,
                    'part_name' => $part->name,
                    'total_movements' => $partMovements->count(),
                    'total_quantity' => (float) $partMovements->sum('quantity'),
                    'total_value' => (float) $partMovements->sum('total_cost'),
                    'movement_types' => $partMovements->groupBy('movement_type')->map(function($typeMovements) {
                        return [
                            'count' => $typeMovements->count(),
                            'quantity' => (float) $typeMovements->sum('quantity'),
                        ];
                    }),
                ];
            })->sortByDesc('total_quantity')->take(10)->values();

            $overallSummary = [
                'period' => [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                ],
                'total_movements' => $movements->count(),
                'total_quantity_moved' => (float) $movements->sum('quantity'),
                'total_value_moved' => (float) $movements->sum('total_cost'),
                'movements_by_type' => $summaryByType,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_summary' => $overallSummary,
                    'top_moving_parts' => $topMovingParts,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate movement summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Low stock alerts
     * 
     * GET /api/inventory/parts/low-stock
     */
    public function lowStockAlerts(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'sometimes|exists:warehouses,id',
            ]);

            $query = PartStockValuation::with(['part', 'warehouse'])
                ->whereHas('part', function($q) {
                    $q->where('is_active', true);
                })
                ->where(function($q) {
                    $q->whereColumn('quantity_on_hand', '<=', DB::raw('parts.reorder_point'))
                      ->orWhere('is_low_stock', true);
                })
                ->join('parts', 'part_stock_valuations.part_id', '=', 'parts.id');

            if (isset($validated['warehouse_id'])) {
                $query->where('part_stock_valuations.warehouse_id', $validated['warehouse_id']);
            }

            $lowStockItems = $query->get()->map(function($valuation) {
                $part = $valuation->part;
                $urgency = $valuation->quantity_on_hand <= $part->min_stock_level ? 'critical' : 'warning';
                
                return [
                    'part_id' => $part->id,
                    'part_sku' => $part->sku,
                    'part_name' => $part->name,
                    'warehouse_id' => $valuation->warehouse_id,
                    'warehouse_name' => $valuation->warehouse->name,
                    'current_stock' => (float) $valuation->quantity_on_hand,
                    'reorder_point' => (float) $part->reorder_point,
                    'min_stock_level' => (float) $part->min_stock_level,
                    'urgency' => $urgency,
                    'suggested_order_quantity' => max(0, $part->max_stock_level - $valuation->quantity_on_hand),
                    'last_movement_date' => $valuation->last_movement_at,
                ];
            });

            $summary = [
                'total_alerts' => $lowStockItems->count(),
                'critical_alerts' => $lowStockItems->where('urgency', 'critical')->count(),
                'warning_alerts' => $lowStockItems->where('urgency', 'warning')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'alerts' => $lowStockItems,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve low stock alerts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Out of stock items
     * 
     * GET /api/inventory/parts/out-of-stock
     */
    public function outOfStockItems(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'warehouse_id' => 'sometimes|exists:warehouses,id',
            ]);

            $query = PartStockValuation::with(['part', 'warehouse'])
                ->whereHas('part', function($q) {
                    $q->where('is_active', true);
                })
                ->where(function($q) {
                    $q->where('quantity_on_hand', '<=', 0)
                      ->orWhere('is_out_of_stock', true);
                });

            if (isset($validated['warehouse_id'])) {
                $query->where('warehouse_id', $validated['warehouse_id']);
            }

            $outOfStockItems = $query->get()->map(function($valuation) {
                $part = $valuation->part;
                
                return [
                    'part_id' => $part->id,
                    'part_sku' => $part->sku,
                    'part_name' => $part->name,
                    'warehouse_id' => $valuation->warehouse_id,
                    'warehouse_name' => $valuation->warehouse->name,
                    'current_stock' => (float) $valuation->quantity_on_hand,
                    'reorder_point' => (float) $part->reorder_point,
                    'suggested_order_quantity' => (float) $part->max_stock_level,
                    'last_movement_date' => $valuation->last_movement_at,
                    'days_out_of_stock' => $valuation->last_movement_at ? 
                        now()->diffInDays($valuation->last_movement_at) : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_items' => $outOfStockItems->count(),
                    'items' => $outOfStockItems,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve out of stock items: ' . $e->getMessage()
            ], 500);
        }
    }
}