<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\StockValuation;
use App\Models\Tenant\Item;
use App\Models\Tenant\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockQueryController extends Controller
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
     * Get current stock levels
     */
    public function getStockLevels(Request $request): JsonResponse
    {
        try {
            $query = StockValuation::with([
                'item', 
                'warehouse', 
                'bin'
            ])
            ->whereHas('item', function($q) {
                $q->where('company_id', $this->getCompanyId());
            })
            ->where('quantity_on_hand', '>', 0);

            // Filter by warehouse
            if ($request->has('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            // Filter by item type (vehicle only for now)
            if ($request->has('item_type')) {
                $query->whereHas('item', function($q) use ($request) {
                    $q->where('item_type', $request->item_type);
                });
            } else {
                // Default to vehicles only
                $query->whereHas('item', function($q) {
                    $q->where('item_type', 'vehicle');
                });
            }

            // Filter by low stock
            if ($request->has('low_stock_only') && $request->low_stock_only) {
                $query->whereHas('item', function($q) {
                    $q->whereRaw('stock_valuations.quantity_on_hand <= items.min_stock_level');
                });
            }

            // Filter by out of stock
            if ($request->has('out_of_stock_only') && $request->out_of_stock_only) {
                $query->where('quantity_on_hand', '<=', 0);
            }

            // Search by item name, SKU, or VIN
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->whereHas('item', function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('sku', 'like', "%{$searchTerm}%")
                      ->orWhere('vin', 'like', "%{$searchTerm}%")
                      ->orWhere('make', 'like', "%{$searchTerm}%")
                      ->orWhere('model', 'like', "%{$searchTerm}%");
                });
            }

            // Order by
            $orderBy = $request->get('order_by', 'item_name');
            $orderDirection = $request->get('order_direction', 'asc');

            switch ($orderBy) {
                case 'quantity':
                    $query->orderBy('quantity_on_hand', $orderDirection);
                    break;
                case 'value':
                    $query->orderBy('total_value', $orderDirection);
                    break;
                case 'warehouse':
                    $query->join('warehouses', 'stock_valuations.warehouse_id', '=', 'warehouses.id')
                          ->orderBy('warehouses.name', $orderDirection)
                          ->select('stock_valuations.*');
                    break;
                case 'item_name':
                default:
                    $query->join('items', 'stock_valuations.item_id', '=', 'items.id')
                          ->orderBy('items.name', $orderDirection)
                          ->select('stock_valuations.*');
                    break;
            }

            $stockLevels = $query->paginate($request->get('per_page', 20));

            // Calculate summary statistics
            $summary = StockValuation::whereHas('item', function($q) {
                    $q->where('company_id', $this->getCompanyId())
                      ->where('item_type', 'vehicle');
                })
                ->select(
                    DB::raw('COUNT(DISTINCT item_id) as total_vehicles'),
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(total_value) as total_value'),
                    DB::raw('COUNT(DISTINCT warehouse_id) as total_warehouses')
                )
                ->first();

            return response()->json([
                'success' => true,
                'data' => $stockLevels->items(),
                'summary' => $summary,
                'meta' => [
                    'current_page' => $stockLevels->currentPage(),
                    'per_page' => $stockLevels->perPage(),
                    'total' => $stockLevels->total(),
                    'last_page' => $stockLevels->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock levels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check vehicle availability
     */
    public function checkVehicleAvailability(string $id): JsonResponse
    {
        try {
            // Get the vehicle item
            $vehicle = Item::with(['category'])
                ->where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->findOrFail($id);

            // Get stock across all warehouses
            $stockData = StockValuation::with(['warehouse', 'bin'])
                ->where('item_id', $id)
                ->where('quantity_on_hand', '>', 0)
                ->get();

            // Calculate total availability
            $totalQuantity = $stockData->sum('quantity_on_hand');
            $totalReserved = $stockData->sum('quantity_reserved');
            $totalAvailable = $totalQuantity - $totalReserved;

            // Get warehouse-specific availability
            $warehouseAvailability = $stockData->map(function($stock) {
                return [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name,
                    'warehouse_code' => $stock->warehouse->code,
                    'bin_id' => $stock->bin_id,
                    'bin_name' => $stock->bin ? $stock->bin->name : null,
                    'quantity_on_hand' => $stock->quantity_on_hand,
                    'quantity_reserved' => $stock->quantity_reserved,
                    'quantity_available' => $stock->quantity_available,
                    'location' => $stock->bin ? $stock->bin->full_location : $stock->warehouse->name,
                ];
            });

            // Check if vehicle is available for sale
            $isAvailable = $totalAvailable > 0;
            $availabilityStatus = $isAvailable ? 'available' : 'out_of_stock';

            // Check if low stock
            $isLowStock = $totalAvailable <= $vehicle->min_stock_level && $totalAvailable > 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'vehicle' => [
                        'id' => $vehicle->id,
                        'sku' => $vehicle->sku,
                        'name' => $vehicle->name,
                        'vin' => $vehicle->vin,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'color' => $vehicle->color,
                        'vehicle_status' => $vehicle->vehicle_status,
                        'min_stock_level' => $vehicle->min_stock_level,
                    ],
                    'availability' => [
                        'status' => $availabilityStatus,
                        'total_quantity' => $totalQuantity,
                        'total_reserved' => $totalReserved,
                        'total_available' => $totalAvailable,
                        'is_available' => $isAvailable,
                        'is_low_stock' => $isLowStock,
                    ],
                    'locations' => $warehouseAvailability,
                    'summary' => [
                        'total_warehouses' => $warehouseAvailability->count(),
                        'total_locations' => $warehouseAvailability->where('quantity_available', '>', 0)->count(),
                    ]
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check vehicle availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get vehicles in specific lot
     */
    public function getWarehouseVehicles(string $id, Request $request): JsonResponse
    {
        try {
            
            $warehouse = Warehouse::where('company_id', $this->getCompanyId())
                ->findOrFail($id);

            $query = StockValuation::with([
                'item.category',
                'bin'
            ])
            ->where('warehouse_id', $id)
            ->whereHas('item', function($q) {
                $q->where('item_type', 'vehicle');
            });

           
            if ($request->has('vehicle_status')) {
                $query->whereHas('item', function($q) use ($request) {
                    $q->where('vehicle_status', $request->vehicle_status);
                });
            }

            // Filter by make
            if ($request->has('make')) {
                $query->whereHas('item', function($q) use ($request) {
                    $q->where('make', 'like', "%{$request->make}%");
                });
            }

            // Filter by model
            if ($request->has('model')) {
                $query->whereHas('item', function($q) use ($request) {
                    $q->where('model', 'like', "%{$request->model}%");
                });
            }

            // Filter by year
            if ($request->has('year')) {
                $query->whereHas('item', function($q) use ($request) {
                    $q->where('year', $request->year);
                });
            }

            // Filter by color
            if ($request->has('color')) {
                $query->whereHas('item', function($q) use ($request) {
                    $q->where('color', 'like', "%{$request->color}%");
                });
            }

            // Filter by in-stock only
            if ($request->has('in_stock_only') && $request->in_stock_only) {
                $query->where('quantity_on_hand', '>', 0);
            }

            // Search by vehicle attributes
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->whereHas('item', function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('sku', 'like', "%{$searchTerm}%")
                      ->orWhere('vin', 'like', "%{$searchTerm}%")
                      ->orWhere('make', 'like', "%{$searchTerm}%")
                      ->orWhere('model', 'like', "%{$searchTerm}%");
                });
            }

            // Order by
            $orderBy = $request->get('order_by', 'item_name');
            $orderDirection = $request->get('order_direction', 'asc');

            switch ($orderBy) {
                case 'quantity':
                    $query->orderBy('quantity_on_hand', $orderDirection);
                    break;
                case 'value':
                    $query->orderBy('total_value', $orderDirection);
                    break;
                case 'make':
                    $query->join('items', 'stock_valuations.item_id', '=', 'items.id')
                          ->orderBy('items.make', $orderDirection)
                          ->orderBy('items.model', $orderDirection)
                          ->select('stock_valuations.*');
                    break;
                case 'year':
                    $query->join('items', 'stock_valuations.item_id', '=', 'items.id')
                          ->orderBy('items.year', $orderDirection)
                          ->select('stock_valuations.*');
                    break;
                case 'item_name':
                default:
                    $query->join('items', 'stock_valuations.item_id', '=', 'items.id')
                          ->orderBy('items.name', $orderDirection)
                          ->select('stock_valuations.*');
                    break;
            }

            $vehicles = $query->paginate($request->get('per_page', 20));

            // Calculate warehouse summary
            $warehouseSummary = StockValuation::where('warehouse_id', $id)
                ->whereHas('item', function($q) {
                    $q->where('item_type', 'vehicle');
                })
                ->select(
                    DB::raw('COUNT(DISTINCT item_id) as total_vehicles'),
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                    DB::raw('SUM(total_value) as total_value'),
                    DB::raw('COUNT(DISTINCT bin_id) as total_bins_used')
                )
                ->first();

            // Get vehicle status distribution
            $statusDistribution = DB::table('stock_valuations')
                ->join('items', 'stock_valuations.item_id', '=', 'items.id')
                ->where('stock_valuations.warehouse_id', $id)
                ->where('items.item_type', 'vehicle')
                ->where('stock_valuations.quantity_on_hand', '>', 0)
                ->select(
                    'items.vehicle_status',
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('items.vehicle_status')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse' => [
                        'id' => $warehouse->id,
                        'code' => $warehouse->code,
                        'name' => $warehouse->name,
                        'type' => $warehouse->type,
                    ],
                    'vehicles' => $vehicles->items(),
                    'summary' => $warehouseSummary,
                    'status_distribution' => $statusDistribution,
                ],
                'meta' => [
                    'current_page' => $vehicles->currentPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'last_page' => $vehicles->lastPage(),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouse vehicles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock summary dashboard
     */
    public function getStockSummary(): JsonResponse
    {
        try {
        
            $totalVehicles = StockValuation::whereHas('item', function($q) {
                    $q->where('company_id', $this->getCompanyId())
                      ->where('item_type', 'vehicle');
                })
                ->sum('quantity_on_hand');

            
            $totalValue = StockValuation::whereHas('item', function($q) {
                    $q->where('company_id', $this->getCompanyId())
                      ->where('item_type', 'vehicle');
                })
                ->sum('total_value');
 
            $uniqueModels = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->whereHas('stockValuations', function($q) {
                    $q->where('quantity_on_hand', '>', 0);
                })
                ->count();

            // Low stock vehicles
            $lowStockCount = StockValuation::whereHas('item', function($q) {
                    $q->where('company_id', $this->getCompanyId())
                      ->where('item_type', 'vehicle')
                      ->whereRaw('stock_valuations.quantity_on_hand <= items.min_stock_level');
                })
                ->where('quantity_on_hand', '>', 0)
                ->count();

            // Out of stock vehicles
            $outOfStockCount = Item::where('company_id', $this->getCompanyId())
                ->where('item_type', 'vehicle')
                ->whereDoesntHave('stockValuations', function($q) {
                    $q->where('quantity_on_hand', '>', 0);
                })
                ->count();

            // Recent stock movements (last 7 days)
            $recentMovements = DB::table('stock_movements')
                ->where('company_id', $this->getCompanyId())
                ->where('created_at', '>=', now()->subDays(7))
                ->select('movement_type', DB::raw('COUNT(*) as count'))
                ->groupBy('movement_type')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_vehicles' => $totalVehicles,
                    'total_value' => $totalValue,
                    'unique_models' => $uniqueModels,
                    'low_stock_count' => $lowStockCount,
                    'out_of_stock_count' => $outOfStockCount,
                    'recent_movements' => $recentMovements,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}