<?php

namespace App\Http\Controllers\Inventory;

use Illuminate\Http\Request;
use App\Models\Tenant\Category;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * List all categories
     */
    public function index(Request $request): JsonResponse
    {
        try {$user = Auth::guard('tenant')->user();
            $companyId = $request->header('X-Company-ID') ?? session('company_id'); 
            $categories = Category::with(['parent', 'children'])
                ->where('company_id',  $companyId )
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new category
     */
    public function store(Request $request) : JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:vehicle,part,consumable,service',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:categories,id',
                'color' => 'nullable|string|max:50',
            ]);

            DB::beginTransaction();
            $user = Auth::guard('tenant')->user(); 
             $companyId = $request->header('X-Company-ID') ?? session('company_id');   
            $category = Category::create([
                'company_id' =>$companyId,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null,
                'color' => $validated['color'] ?? null,
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category->load(['parent', 'children'])
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
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category details
     */
    public function show(Request $request ,string $id): JsonResponse
    {
        try { $user = Auth::guard('tenant')->user();
            $companyId = $request->header('X-Company-ID') ?? session('company_id'); 
            $category = Category::with(['parent', 'children', 'items'])
                ->where('company_id', $companyId)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update category
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {$user = Auth::guard('tenant')->user();
            $companyId = $request->header('X-Company-ID') ?? session('company_id'); 
            $category = Category::where('company_id',$companyId)
                ->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'type' => 'sometimes|in:vehicle,part,consumable,service',
                'description' => 'nullable|string',
                'parent_id' => ['nullable', Rule::exists('categories', 'id')->where('company_id', $user->company_id)],
                'color' => 'nullable|string|max:50',
                'is_active' => 'sometimes|boolean',
            ]);

           
            if (isset($validated['parent_id']) && $validated['parent_id'] == $category->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category cannot be its own parent'
                ], 422);
            }

            DB::beginTransaction();

            $category->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'data' => $category->load(['parent', 'children'])
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
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete category
     */
    public function destroy(Request $request,string $id): JsonResponse
    {
        try {$user = Auth::guard('tenant')->user();
            $companyId = $request->header('X-Company-ID') ?? session('company_id'); 
            $category = Category::where('company_id',  $companyId)
                ->findOrFail($id);

          
            if ($category->items()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with associated items'
                ], 422);
            }

          
            if ($category->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with sub-categories'
                ], 422);
            }

            DB::beginTransaction();

            $category->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get categories by type
     */
    public function byType(string $type): JsonResponse
    {
        try {
            $validTypes = ['vehicle', 'part', 'consumable', 'service'];
            
            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid category type'
                ], 422);
            }
            $user = Auth::guard('tenant')->user();
            $companyId = $request->header('X-Company-ID') ?? session('company_id'); 
            $categories = Category::with(['parent', 'children'])
                ->where('company_id', $companyId)
                ->where('type', $type)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}