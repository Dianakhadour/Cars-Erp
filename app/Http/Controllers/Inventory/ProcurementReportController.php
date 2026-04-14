<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\GoodsReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcurementReportController extends Controller
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
     * Purchase order reports
     */
    public function purchaseOrderReports(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'supplier_id' => 'nullable|exists:suppliers,id',
                'status' => 'nullable|in:draft,approved,partially_received,completed,cancelled',
            ]);
    
            $query = PurchaseOrder::with(['supplier', 'creator'])
                ->where('company_id', $this->getCompanyId())
                ->whereBetween('po_date', [$validated['start_date'], $validated['end_date']]);
    
            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $validated['supplier_id']);
            }
    
            if ($request->has('status')) {
                $query->where('status', $validated['status']);
            }
    
            $purchaseOrders = $query->orderBy('po_date', 'desc')->get();
    
            // Calculate summary statistics
            $summary = [
                'total_orders' => $purchaseOrders->count(),
                'total_amount' => $purchaseOrders->sum('total_amount'),
                'total_received_amount' => $purchaseOrders->sum('received_amount'),
                'orders_by_status' => $purchaseOrders->groupBy('status')->map->count(),
                'average_order_value' => $purchaseOrders->avg('total_amount'),
            ];
    
            // Monthly breakdown
            $monthlyBreakdown = PurchaseOrder::where('company_id', $this->getCompanyId())
                ->whereBetween('po_date', [$validated['start_date'], $validated['end_date']])
                ->select(
                    DB::raw('YEAR(po_date) as year'),
                    DB::raw('MONTH(po_date) as month'),
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(total_amount) as total_amount'),
                    DB::raw('SUM(received_amount) as received_amount')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();
    
             
            $topSuppliers = PurchaseOrder::where('purchase_orders.company_id', $this->getCompanyId())
                ->whereBetween('purchase_orders.po_date', [$validated['start_date'], $validated['end_date']])
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->select(
                    'suppliers.id',
                    'suppliers.name',
                    'suppliers.code',
                    DB::raw('COUNT(purchase_orders.id) as order_count'),
                    DB::raw('SUM(purchase_orders.total_amount) as total_spent'),
                    DB::raw('AVG(purchase_orders.total_amount) as average_order_value')
                )
                ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.code')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'purchase_orders' => $purchaseOrders,
                    'summary' => $summary,
                    'monthly_breakdown' => $monthlyBreakdown,
                    'top_suppliers' => $topSuppliers,
                    'filters' => [
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'],
                        'supplier_id' => $validated['supplier_id'] ?? null,
                        'status' => $validated['status'] ?? null,
                    ]
                ]
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate purchase order report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supplier performance report
     */
    public function supplierPerformance(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'min_orders' => 'nullable|integer|min:1',
            ]);

            $query = Supplier::where('suppliers.company_id', $this->getCompanyId())
                ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->whereBetween('purchase_orders.po_date', [$validated['start_date'], $validated['end_date']])
                ->select(
                    'suppliers.id',
                    'suppliers.code',
                    'suppliers.name',
                    'suppliers.contact_person',
                    'suppliers.phone',
                    'suppliers.email',
                    'suppliers.is_active',
                    DB::raw('COUNT(purchase_orders.id) as total_orders'),
                    DB::raw('SUM(purchase_orders.total_amount) as total_spent'),
                    DB::raw('AVG(purchase_orders.total_amount) as average_order_value'),
                    DB::raw('MAX(purchase_orders.po_date) as last_order_date'),
                    DB::raw('MIN(purchase_orders.po_date) as first_order_date'),
                    DB::raw('SUM(purchase_orders.received_amount) as total_received_amount'),
                    DB::raw('(SUM(purchase_orders.received_amount) / SUM(purchase_orders.total_amount)) * 100 as fulfillment_rate')
                )
                ->groupBy(
                    'suppliers.id', 
                    'suppliers.code', 
                    'suppliers.name', 
                    'suppliers.contact_person', 
                    'suppliers.phone', 
                    'suppliers.email',
                    'suppliers.is_active'
                )
                ->having('total_orders', '>', 0)
                ->orderBy('total_spent', 'desc');

            if ($request->has('min_orders')) {
                $query->having('total_orders', '>=', $validated['min_orders']);
            }

            $supplierPerformance = $query->get();

            // Calculate overall metrics
            $overallMetrics = [
                'total_suppliers' => $supplierPerformance->count(),
                'total_orders' => $supplierPerformance->sum('total_orders'),
                'total_spent' => $supplierPerformance->sum('total_spent'),
                'average_fulfillment_rate' => $supplierPerformance->avg('fulfillment_rate'),
                'average_orders_per_supplier' => $supplierPerformance->avg('total_orders'),
            ];

            // Performance categories
            $performanceCategories = [
                'excellent' => $supplierPerformance->where('fulfillment_rate', '>=', 95)->count(),
                'good' => $supplierPerformance->whereBetween('fulfillment_rate', [85, 94.99])->count(),
                'average' => $supplierPerformance->whereBetween('fulfillment_rate', [70, 84.99])->count(),
                'poor' => $supplierPerformance->where('fulfillment_rate', '<', 70)->count(),
            ];

            // On-time delivery analysis (simplified - using receipt date vs expected date)
            $deliveryAnalysis = DB::table('purchase_orders')
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->join('goods_receipts', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
                ->where('suppliers.company_id', $this->getCompanyId())
                ->whereBetween('purchase_orders.po_date', [$validated['start_date'], $validated['end_date']])
                ->select(
                    'suppliers.id',
                    'suppliers.name',
                    DB::raw('COUNT(purchase_orders.id) as total_orders'),
                    DB::raw('SUM(CASE WHEN goods_receipts.receipt_date <= purchase_orders.expected_delivery_date THEN 1 ELSE 0 END) as on_time_orders'),
                    DB::raw('(SUM(CASE WHEN goods_receipts.receipt_date <= purchase_orders.expected_delivery_date THEN 1 ELSE 0 END) / COUNT(purchase_orders.id)) * 100 as on_time_rate')
                )
                ->groupBy('suppliers.id', 'suppliers.name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'supplier_performance' => $supplierPerformance,
                    'overall_metrics' => $overallMetrics,
                    'performance_categories' => $performanceCategories,
                    'delivery_analysis' => $deliveryAnalysis,
                    'filters' => [
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'],
                        'min_orders' => $validated['min_orders'] ?? null,
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate supplier performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procurement dashboard summary
     */
    public function procurementDashboard(): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();
    
            // Current month statistics
            $currentMonth = now()->format('Y-m');
            $monthlyStats = PurchaseOrder::where('company_id', $companyId)
                ->whereRaw("DATE_FORMAT(po_date, '%Y-%m') = ?", [$currentMonth])
                ->select(
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(total_amount) as total_amount'),
                    DB::raw('SUM(received_amount) as received_amount'),
                    DB::raw('AVG(total_amount) as average_order_value')
                )
                ->first();
    
            // Pending orders by status
            $pendingOrders = PurchaseOrder::where('company_id', $companyId)
                ->whereIn('status', ['draft', 'approved', 'partially_received'])
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get();
    
            // Recent purchase orders
            $recentOrders = PurchaseOrder::with(['supplier'])
                ->where('company_id', $companyId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
    
            
            $topSuppliers = PurchaseOrder::where('purchase_orders.company_id', $companyId)
                ->whereRaw("DATE_FORMAT(purchase_orders.po_date, '%Y-%m') = ?", [$currentMonth])
                ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                ->select(
                    'suppliers.name',
                    DB::raw('COUNT(purchase_orders.id) as order_count'),
                    DB::raw('SUM(purchase_orders.total_amount) as total_spent')
                )
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderBy('total_spent', 'desc')
                ->limit(5)
                ->get();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'monthly_stats' => $monthlyStats,
                    'pending_orders' => $pendingOrders,
                    'recent_orders' => $recentOrders,
                    'top_suppliers' => $topSuppliers,
                    'current_month' => $currentMonth,
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch procurement dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}