<?php

namespace App\Http\Controllers\Inventory;

use App\Models\Tenant\Item;
use Illuminate\Http\Request;
use App\Models\Tenant\Company;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class VehicleController extends Controller
{ 
        /**
         * List all vehicles
         */
        public function index(Request $request): JsonResponse
        {
            $user = Auth::guard('tenant')->user();
            $companyId = $request->header('X-Company-ID') ?? session('company_id'); 
            try {
                $vehicles = Item::with(['category', 'company'])
                    ->where('item_type', 'vehicle')
                    ->where('company_id', $companyId)
                    ->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20));
    
                return response()->json([
                    'success' => true,
                    'data' => $vehicles->items(),
                    'meta' => [
                        'current_page' => $vehicles->currentPage(),
                        'per_page' => $vehicles->perPage(),
                        'total' => $vehicles->total(),
                        'last_page' => $vehicles->lastPage(),
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch vehicles',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    
        /**
         * Create new vehicle
         */
        public function store(Request $request): JsonResponse
        {
            try {
                $validated = $request->validate([
                    'sku' => 'required|string|max:100',
                    'name' => 'required|string|max:255',
                    'category_id' => 'required|exists:categories,id',
                    'vin' => 'nullable|string|unique:items,vin',
                    'make' => 'required|string|max:100',
                    'model' => 'required|string|max:100',
                    'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                    'color' => 'nullable|string|max:50',
                    'mileage' => 'nullable|integer|min:0',
                    'vehicle_status' => 'required|in:new,used,demo,reconditioned',
                    'vehicle_features' => 'nullable|array',
                    'standard_cost' => 'required|numeric|min:0',
                    'selling_price' => 'required|numeric|min:0',
                    'description' => 'nullable|string',
                    'images' => 'nullable|array',
                    'images.*' => 'string',
                ]);
    
                DB::beginTransaction();
                $user = Auth::guard('tenant')->user();
                $companyId = $request->header('X-Company-ID') ?? session('company_id');
                $vehicle = Item::create([
                    'company_id' => $companyId ,
                    'item_type' => 'vehicle',
                    'sku' => $validated['sku'],
                    'name' => $validated['name'],
                    'category_id' => $validated['category_id'],
                    'vin' => $validated['vin'] ?? null,
                    'make' => $validated['make'],
                    'model' => $validated['model'],
                    'year' => $validated['year'],
                    'color' => $validated['color'] ?? null,
                    'mileage' => $validated['mileage'] ?? 0,
                    'vehicle_status' => $validated['vehicle_status'],
                    'vehicle_features' => $validated['vehicle_features'] ?? [],
                    'standard_cost' => $validated['standard_cost'],
                    'selling_price' => $validated['selling_price'],
                    'description' => $validated['description'] ?? null,
                    'images' => $validated['images'] ?? [],
                    'is_active' => true,
                ]);
    
                DB::commit();
    
                return response()->json([
                    'success' => true,
                    'message' => 'Vehicle created successfully',
                    'data' => $vehicle->load(['category', 'company'])
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
                    'message' => 'Failed to create vehicle',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    
        /**
         * Get vehicle details
         */
        public function show(Request $request,string $id): JsonResponse
        {
            try {
                $user = Auth::guard('tenant')->user();
                $companyId = $request->header('X-Company-ID') ?? session('company_id');
                $vehicle = Item::with(['category', 'company', 'stockValuations.warehouse'])
                    ->where('item_type', 'vehicle')
                    ->where('company_id',  $companyId)
                    ->findOrFail($id);
    
                return response()->json([
                    'success' => true,
                    'data' => $vehicle
                ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found'
                ], 404);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch vehicle',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    
        /**
         * Update vehicle
         */
        public function update(Request $request, string $id): JsonResponse
        {
            try {
                $user = Auth::guard('tenant')->user();
                $companyId = $request->header('X-Company-ID') ?? session('company_id');
                $vehicle = Item::where('item_type', 'vehicle')
                    ->where('company_id',$companyId)
                    ->findOrFail($id);
    
                $validated = $request->validate([
                    'sku' => 'sometimes|string|max:100',
                    'name' => 'sometimes|string|max:255',
                    'category_id' => 'sometimes|exists:categories,id',
                    'vin' => ['nullable', 'string', Rule::unique('items', 'vin')->ignore($vehicle->id)],
                    'make' => 'sometimes|string|max:100',
                    'model' => 'sometimes|string|max:100',
                    'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
                    'color' => 'nullable|string|max:50',
                    'mileage' => 'nullable|integer|min:0',
                    'vehicle_status' => 'sometimes|in:new,used,demo,reconditioned',
                    'vehicle_features' => 'nullable|array',
                    'standard_cost' => 'sometimes|numeric|min:0',
                    'selling_price' => 'sometimes|numeric|min:0',
                    'description' => 'nullable|string',
                    'images' => 'nullable|array',
                    'images.*' => 'string',
                    'is_active' => 'sometimes|boolean',
                ]);
    
                DB::beginTransaction();
    
                $vehicle->update($validated);
    
                DB::commit();
    
                return response()->json([
                    'success' => true,
                    'message' => 'Vehicle updated successfully',
                    'data' => $vehicle->load(['category', 'company'])
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
                    'message' => 'Vehicle not found'
                ], 404);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update vehicle',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    
        /**
         * Search vehicles by VIN/make/model
         */
        public function search(Request $request): JsonResponse
        {
            try {   $user = Auth::guard('tenant')->user();
                $companyId = $request->header('X-Company-ID') ?? session('company_id');
                $query = Item::with(['category', 'company'])
                    ->where('item_type', 'vehicle')
                    ->where('company_id',    $companyId);
    
                // Search by VIN
                if ($request->has('vin')) {
                    $query->where('vin', 'like', '%' . $request->vin . '%');
                }
    
                // Search by make
                if ($request->has('make')) {
                    $query->where('make', 'like', '%' . $request->make . '%');
                }
    
                // Search by model
                if ($request->has('model')) {
                    $query->where('model', 'like', '%' . $request->model . '%');
                }
    
                // Search by year
                if ($request->has('year')) {
                    $query->where('year', $request->year);
                }
    
                // Search by status
                if ($request->has('vehicle_status')) {
                    $query->where('vehicle_status', $request->vehicle_status);
                }
    
                // Search by color
                if ($request->has('color')) {
                    $query->where('color', 'like', '%' . $request->color . '%');
                }
    
                $vehicles = $query->orderBy('created_at', 'desc')
                    ->paginate($request->get('per_page', 20));
    
                return response()->json([
                    'success' => true,
                    'data' => $vehicles->items(),
                    'meta' => [
                        'current_page' => $vehicles->currentPage(),
                        'per_page' => $vehicles->perPage(),
                        'total' => $vehicles->total(),
                        'last_page' => $vehicles->lastPage(),
                    ]
                ]);
    
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search vehicles',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
}
