<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
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
     * List all suppliers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Supplier::where('company_id', $this->getCompanyId())
                ->orderBy('name');

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

 
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('code', 'like', "%{$searchTerm}%")
                      ->orWhere('contact_person', 'like', "%{$searchTerm}%");
                });
            }

            $suppliers = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $suppliers->items(),
                'meta' => [
                    'current_page' => $suppliers->currentPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                    'last_page' => $suppliers->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create supplier
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:50',
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'tax_number' => 'nullable|string|max:100',
            ]);

            DB::beginTransaction();

 
            $existingSupplier = Supplier::where('company_id', $this->getCompanyId())
                ->where('code', $validated['code'])
                ->first();

            if ($existingSupplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier code already exists for this company'
                ], 422);
            }

            $supplier = Supplier::create([
                'company_id' => $this->getCompanyId(),
                'code' => $validated['code'],
                'name' => $validated['name'],
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'is_active' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully',
                'data' => $supplier
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
                'message' => 'Failed to create supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $supplier = Supplier::with(['purchaseOrders' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }])
            ->where('company_id', $this->getCompanyId())
            ->findOrFail($id);

            // Calculate supplier statistics
            $stats = DB::table('purchase_orders')
                ->where('supplier_id', $id)
                ->select(
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(total_amount) as total_spent'),
                    DB::raw('AVG(total_amount) as average_order_value'),
                    DB::raw('MAX(created_at) as last_order_date')
                )
                ->first();

            $supplier->statistics = $stats;

            return response()->json([
                'success' => true,
                'data' => $supplier
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}