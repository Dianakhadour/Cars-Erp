<?php
 
namespace App\Http\Controllers\Inventory\Report;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\PartCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\PartStockValuation;

class ValuationReportController extends Controller
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
    public function getValuationSummary(Request $request): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();
            
            // Total valuation
            $totalValuation = PartStockValuation::with(['part', 'warehouse'])
                ->byCompany($companyId)
                ->select(
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(total_value) as total_value'),
                    DB::raw('AVG(average_cost) as avg_cost_per_item'),
                    DB::raw('COUNT(DISTINCT part_id) as unique_parts')
                )
                ->first();

            // Valuation by category
            $byCategory = PartStockValuation::join('parts', 'part_stock_valuations.part_id', '=', 'parts.id')
                ->join('part_categories', 'parts.category_id', '=', 'part_categories.id')
                ->where('part_stock_valuations.company_id', $companyId)
                ->select(
                    'part_categories.name as category_name',
                    DB::raw('SUM(part_stock_valuations.total_value) as category_value'),
                    DB::raw('SUM(part_stock_valuations.quantity_on_hand) as category_quantity'),
                    DB::raw('COUNT(DISTINCT parts.id) as part_count')
                )
                ->groupBy('part_categories.id', 'part_categories.name')
                ->orderBy('category_value', 'desc')
                ->get();

            // Valuation by warehouse
            $byWarehouse = PartStockValuation::with(['warehouse'])
                ->byCompany($companyId)
                ->select(
                    'warehouse_id',
                    DB::raw('SUM(total_value) as warehouse_value'),
                    DB::raw('SUM(quantity_on_hand) as warehouse_quantity'),
                    DB::raw('COUNT(DISTINCT part_id) as part_count')
                )
                ->groupBy('warehouse_id')
                ->orderBy('warehouse_value', 'desc')
                ->get();

            // Stock status summary
            $stockStatus = PartStockValuation::byCompany($companyId)
                ->select(
                    DB::raw('SUM(CASE WHEN is_out_of_stock = 1 THEN 1 ELSE 0 END) as out_of_stock_count'),
                    DB::raw('SUM(CASE WHEN is_low_stock = 1 THEN 1 ELSE 0 END) as low_stock_count'),
                    DB::raw('COUNT(*) as total_items')
                )
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_valuation' => [
                        'total_value' => (float) ($totalValuation->total_value ?? 0),
                        'total_quantity' => (float) ($totalValuation->total_quantity ?? 0),
                        'avg_cost_per_item' => (float) ($totalValuation->avg_cost_per_item ?? 0),
                        'unique_parts' => (int) ($totalValuation->unique_parts ?? 0),
                    ],
                    'by_category' => $byCategory,
                    'by_warehouse' => $byWarehouse,
                    'stock_status' => $stockStatus,
                    'as_of_date' => now()->toDateTimeString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate valuation summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getValuationByCategory(Request $request): JsonResponse
    {
        try {
            $valuation = PartStockValuation::join('parts', 'part_stock_valuations.part_id', '=', 'parts.id')
                ->join('part_categories', 'parts.category_id', '=', 'part_categories.id')
                ->where('part_stock_valuations.company_id', $this->getCompanyId())
                ->select(
                    'part_categories.id',
                    'part_categories.name as category_name',
                    'part_categories.code as category_code',
                    DB::raw('SUM(part_stock_valuations.quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(part_stock_valuations.total_value) as total_value'),
                    DB::raw('AVG(part_stock_valuations.average_cost) as avg_cost'),
                    DB::raw('COUNT(DISTINCT parts.id) as part_count')
                )
                ->groupBy('part_categories.id', 'part_categories.name', 'part_categories.code')
                ->orderBy('total_value', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $valuation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate category valuation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getValuationByWarehouse(Request $request): JsonResponse
    {
        try {
            $valuation = PartStockValuation::with(['warehouse'])
                ->byCompany($this->getCompanyId())
                ->select(
                    'warehouse_id',
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(total_value) as total_value'),
                    DB::raw('AVG(average_cost) as avg_cost'),
                    DB::raw('COUNT(DISTINCT part_id) as part_count')
                )
                ->groupBy('warehouse_id')
                ->orderBy('total_value', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $valuation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate warehouse valuation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}