<?php
 
namespace App\Http\Controllers\Inventory\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\PartStockMovement;
use App\Models\Tenant\PartStockValuation;

class FinanceIntegrationController extends Controller
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
    public function exportStockValuation(Request $request): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();
            $exportDate = $request->get('as_of_date', now()->format('Y-m-d'));

            // Get current stock valuation for export
            $stockData = PartStockValuation::with(['part', 'part.category', 'warehouse'])
                ->byCompany($companyId)
                ->where('quantity_on_hand', '>', 0)
                ->get()
                ->map(function ($valuation) {
                    return [
                        'part_sku' => $valuation->part->sku,
                        'part_name' => $valuation->part->name,
                        'category' => $valuation->part->category->name ?? 'Uncategorized',
                        'warehouse' => $valuation->warehouse->name,
                        'quantity_on_hand' => (float) $valuation->quantity_on_hand,
                        'average_cost' => (float) $valuation->average_cost,
                        'total_value' => (float) $valuation->total_value,
                        'valuation_method' => 'weighted_average',  
                        'last_updated' => $valuation->updated_at->toDateTimeString(),
                    ];
                });

            $summary = [
                'export_date' => now()->toDateTimeString(),
                'as_of_date' => $exportDate,
                'total_items' => $stockData->count(),
                'total_quantity' => $stockData->sum('quantity_on_hand'),
                'total_value' => $stockData->sum('total_value'),
                'company_id' => $companyId,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Stock valuation exported successfully',
                'data' => [
                    'summary' => $summary,
                    'items' => $stockData,
                    'export_format' => 'accounting_ready'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export stock valuation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCostOfGoodsSold(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $companyId = $this->getCompanyId();

            
            $cogsData = PartStockMovement::with(['part', 'part.category'])
                ->byCompany($companyId)
                ->where('movement_type', 'issue')
                ->whereBetween('movement_date', [
                    $request->start_date, 
                    $request->end_date
                ])
                ->where('status', 'completed')
                ->select(
                    DB::raw('SUM(total_cost) as total_cogs'),
                    DB::raw('COUNT(*) as transaction_count'),
                    DB::raw('SUM(quantity) as total_quantity_issued')
                )
                ->first();

            // COGS by category
            $cogsByCategory = PartStockMovement::join('parts', 'part_stock_movements.part_id', '=', 'parts.id')
                ->leftJoin('part_categories', 'parts.category_id', '=', 'part_categories.id')
                ->where('part_stock_movements.company_id', $companyId)
                ->where('movement_type', 'issue')
                ->whereBetween('movement_date', [$request->start_date, $request->end_date])
                ->where('part_stock_movements.status', 'completed')
                ->select(
                    'part_categories.name as category_name',
                    DB::raw('SUM(part_stock_movements.total_cost) as category_cogs'),
                    DB::raw('SUM(part_stock_movements.quantity) as quantity_issued'),
                    DB::raw('COUNT(*) as transaction_count')
                )
                ->groupBy('part_categories.id', 'part_categories.name')
                ->orderBy('category_cogs', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                    ],
                    'summary' => [
                        'total_cogs' => (float) ($cogsData->total_cogs ?? 0),
                        'total_quantity_issued' => (float) ($cogsData->total_quantity_issued ?? 0),
                        'transaction_count' => (int) ($cogsData->transaction_count ?? 0),
                    ],
                    'by_category' => $cogsByCategory,
                    'calculation_method' => 'Actual cost (based on stock movements)'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate COGS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function periodEndClosing(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'closing_date' => 'required|date',
                'period' => 'required|in:monthly,quarterly,yearly',
            ]);

            $companyId = $this->getCompanyId();
            $closingDate = $request->closing_date;

            DB::beginTransaction();

        
            $closingSnapshot = PartStockValuation::with(['part'])
                ->byCompany($companyId)
                ->get()
                ->map(function ($valuation) use ($closingDate) {
                    return [
                        'part_id' => $valuation->part_id,
                        'part_sku' => $valuation->part->sku,
                        'closing_quantity' => (float) $valuation->quantity_on_hand,
                        'closing_value' => (float) $valuation->total_value,
                        'average_cost' => (float) $valuation->average_cost,
                        'as_of_date' => $closingDate,
                    ];
                });
 
            $periodMetrics = [
                'closing_date' => $closingDate,
                'period_type' => $request->period,
                'total_closing_value' => $closingSnapshot->sum('closing_value'),
                'total_items' => $closingSnapshot->count(),
                'snapshot_taken_at' => now()->toDateTimeString(),
            ];

            

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Period end closing completed successfully',
                'data' => [
                    'period_metrics' => $periodMetrics,
                    'closing_snapshot' => $closingSnapshot,
                    'next_period_start' => date('Y-m-d', strtotime($closingDate . ' +1 day'))
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete period end closing',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}