<?php

namespace App\Http\Controllers\Inventory;

use Illuminate\Http\Request;
use App\Models\Tenant\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PartSupplierController extends Controller
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
    /**
     * List parts suppliers
     * 
     * GET /api/inventory/parts-suppliers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'search' => 'sometimes|string|max:255',
                'is_active' => 'sometimes|boolean',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = Supplier::withCount(['purchaseOrders as total_orders'])
                ->with(['purchaseOrders' => function($query) {
                    $query->select('supplier_id', DB::raw('SUM(total_amount) as total_spent'))
                        ->where('status', 'completed')
                        ->groupBy('supplier_id');
                }]);

            if (isset($validated['search'])) {
                $query->where(function($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['search'] . '%')
                      ->orWhere('code', 'like', '%' . $validated['search'] . '%')
                      ->orWhere('contact_person', 'like', '%' . $validated['search'] . '%');
                });
            }

            if (isset($validated['is_active'])) {
                $query->where('is_active', $validated['is_active']);
            }

            $perPage = $validated['per_page'] ?? 15;
            $suppliers = $query->paginate($perPage);

            $suppliers->getCollection()->transform(function($supplier) {
                $totalSpent = $supplier->purchaseOrders->first()->total_spent ?? 0;
                
                return [
                    'id' => $supplier->id,
                    'code' => $supplier->code,
                    'name' => $supplier->name,
                    'contact_person' => $supplier->contact_person,
                    'phone' => $supplier->phone,
                    'email' => $supplier->email,
                    'address' => $supplier->address,
                    'tax_number' => $supplier->tax_number,
                    'is_active' => $supplier->is_active,
                    'total_orders' => $supplier->total_orders,
                    'total_spent' => (float) $totalSpent,
                    'created_at' => $supplier->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $suppliers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve suppliers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add parts supplier
     * 
     * POST /api/inventory/parts-suppliers
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();

            $validated = $request->validate([
                'code' => 'required|string|max:255|unique:suppliers,code,NULL,id,company_id,' . $companyId,
                'name' => 'required|string|max:255',
                'contact_person' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'address' => 'sometimes|string|max:500',
                'tax_number' => 'sometimes|string|max:255',
                'is_active' => 'sometimes|boolean',
            ]);

            $validated['company_id'] = $companyId;

            $supplier = Supplier::create($validated);

           
          

            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create supplier: ' . $e->getMessage()
            ], 500);
        }
    }
}