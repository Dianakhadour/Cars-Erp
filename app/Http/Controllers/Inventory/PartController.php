<?php
 
namespace App\Http\Controllers\Inventory;

use App\Models\Tenant\Part;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\PartCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PartController extends Controller
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
    public function index(Request $request): JsonResponse
    {
        try {
            $parts = Part::with(['category'])
                ->byCompany($this->getCompanyId())
                ->when($request->has('category_id'), function($query) use ($request) {
                    return $query->byCategory($request->category_id);
                })
                ->when($request->has('active_only'), function($query) {
                    return $query->active();
                })
                ->when($request->has('search'), function($query) use ($request) {
                    return $query->search($request->search);
                })
                ->when($request->has('low_stock'), function($query) {
              
                    return $query->whereRaw('(SELECT COALESCE(SUM(quantity_on_hand), 0) FROM stock_valuations WHERE item_id = parts.id AND item_type = "part") <= reorder_point');
                })
                ->orderBy('name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $parts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch parts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'category_id' => 'nullable|exists:part_categories,id',
                'sku' => 'required|string|max:100|unique:parts,sku,NULL,id,company_id,' . $this->getCompanyId(),
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'barcode' => 'nullable|string|max:255|unique:parts,barcode,NULL,id,company_id,' . $this->getCompanyId(),
                'oem_number' => 'nullable|string|max:255',
                'brand' => 'nullable|string|max:255',
                'model_compatibility' => 'nullable|array',
                'unit_of_measure' => 'required|string|max:50',
                'weight' => 'nullable|numeric|min:0',
                'volume' => 'nullable|numeric|min:0',
                'cost_price' => 'required|numeric|min:0',
                'selling_price' => 'required|numeric|min:0',
                'retail_price' => 'nullable|numeric|min:0',
                'min_stock_level' => 'required|numeric|min:0',
                'max_stock_level' => 'required|numeric|min:0',
                'reorder_point' => 'required|numeric|min:0',
                'is_active' => 'boolean',
                'is_trackable' => 'boolean',
                'specifications' => 'nullable|array',
                'notes' => 'nullable|string'
            ]);

            $part = Part::create([
                ...$validated,
                'company_id' => $this->getCompanyId()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Part created successfully',
                'data' => $part->load(['category'])
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create part',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $part = Part::with(['category', 'stockValuations'])
                ->byCompany($this->getCompanyId())
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $part
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Part not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $part = Part::byCompany($this->getCompanyId())->findOrFail($id);

            $validated = $request->validate([
                'category_id' => 'sometimes|nullable|exists:part_categories,id',
                'sku' => 'sometimes|required|string|max:100|unique:parts,sku,' . $id . ',id,company_id,' . $this->getCompanyId(),
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string',
                'barcode' => 'sometimes|nullable|string|max:255|unique:parts,barcode,' . $id . ',id,company_id,' . $this->getCompanyId(),
                'oem_number' => 'sometimes|nullable|string|max:255',
                'brand' => 'sometimes|nullable|string|max:255',
                'model_compatibility' => 'sometimes|nullable|array',
                'unit_of_measure' => 'sometimes|required|string|max:50',
                'weight' => 'sometimes|nullable|numeric|min:0',
                'volume' => 'sometimes|nullable|numeric|min:0',
                'cost_price' => 'sometimes|required|numeric|min:0',
                'selling_price' => 'sometimes|required|numeric|min:0',
                'retail_price' => 'sometimes|nullable|numeric|min:0',
                'min_stock_level' => 'sometimes|required|numeric|min:0',
                'max_stock_level' => 'sometimes|required|numeric|min:0',
                'reorder_point' => 'sometimes|required|numeric|min:0',
                'is_active' => 'sometimes|boolean',
                'is_trackable' => 'sometimes|boolean',
                'specifications' => 'sometimes|nullable|array',
                'notes' => 'sometimes|nullable|string'
            ]);

            $part->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Part updated successfully',
                'data' => $part->load(['category'])
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
                'message' => 'Failed to update part',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $part = Part::byCompany($this->getCompanyId())->findOrFail($id);

            // Check if part has stock movements or purchase orders
            if ($part->stockMovements()->exists() || $part->purchaseOrders()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete part that has transactions history'
                ], 422);
            }

            $part->delete();

            return response()->json([
                'success' => true,
                'message' => 'Part deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete part',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2'
            ]);

            $parts = Part::with(['category'])
                ->byCompany($this->getCompanyId())
                ->active()
                ->search($request->q)
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $parts
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
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}