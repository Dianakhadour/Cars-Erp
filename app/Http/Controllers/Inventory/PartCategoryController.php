<?php
 
namespace App\Http\Controllers\Inventory;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\PartCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PartCategoryController extends Controller
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
            $categories = PartCategory::with(['parent', 'children'])
                ->byCompany($this->getCompanyId())
                ->when($request->has('active_only'), function($query) {
                    return $query->active();
                })
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch part categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50|unique:part_categories,code,NULL,id,company_id,' . $this->getCompanyId(),
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:part_categories,id',
                'is_active' => 'boolean'
            ]);

            $category = PartCategory::create([
                'id' => (string) Str::uuid(),  
                ...$validated,
                'company_id' => $this->getCompanyId()
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Part category created successfully',
                'data' => $category->load(['parent', 'children'])
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
                'message' => 'Failed to create part category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $category = PartCategory::with(['parent', 'children', 'parts'])
                ->byCompany($this->getCompanyId())
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Part category not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $category = PartCategory::byCompany($this->getCompanyId())->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|nullable|string|max:50|unique:part_categories,code,' . $id . ',id,company_id,' . $this->getCompanyId(),
                'description' => 'sometimes|nullable|string',
                'parent_id' => 'sometimes|nullable|exists:part_categories,id',
                'is_active' => 'sometimes|boolean'
            ]);

            $category->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Part category updated successfully',
                'data' => $category->load(['parent', 'children'])
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
                'message' => 'Failed to update part category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $category = PartCategory::byCompany($this->getCompanyId())->findOrFail($id);

            // Check if category has parts
            if ($category->parts()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category that has parts assigned'
                ], 422);
            }

            // Check if category has children
            if ($category->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category that has sub-categories'
                ], 422);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Part category deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete part category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}