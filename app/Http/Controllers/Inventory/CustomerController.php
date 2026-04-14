<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Add a new customer
     */
    public function addCustomer(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:companies,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            
            // Address
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            
            // Additional Details
            'type' => 'required|in:individual,corporate',
            'company_name' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            
            // Contact Persons (for corporate customers)
            'contact_persons' => 'nullable|array',
            'contact_persons.*.name' => 'required_with:contact_persons|string|max:255',
            'contact_persons.*.email' => 'nullable|email',
            'contact_persons.*.phone' => 'nullable|string|max:20',
            
            // Customer Classification
            'customer_class' => 'required|in:regular,premium,vip',
            'credit_limit' => 'nullable|numeric|min:0',
            'current_balance' => 'nullable|numeric|min:0',
            
            // Status
            'is_active' => 'boolean',
            
            // Metadata
            'notes' => 'nullable|string',
            'preferences' => 'nullable|array'
        ]);

        try {
            $customerId = (string) Str::uuid();
            
            // Build customer data with proper null checks
            $customerData = [
                'id' => $customerId,
                'company_id' => $validated['company_id'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
                'country' => $validated['country'] ?? null,
                'type' => $validated['type'],
                'company_name' => $validated['company_name'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'contact_persons' => isset($validated['contact_persons']) ? json_encode($validated['contact_persons']) : null,
                'customer_class' => $validated['customer_class'],
                'credit_limit' => $validated['credit_limit'] ?? 0.00,
                'current_balance' => $validated['current_balance'] ?? 0.00,
                'is_active' => $validated['is_active'] ?? true,
                'notes' => $validated['notes'] ?? null,
                'preferences' => isset($validated['preferences']) ? json_encode($validated['preferences']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('customers')->insert($customerData);

            // Get the created customer with company info
            $customer = DB::table('customers')
                ->join('companies', 'customers.company_id', '=', 'companies.id')
                ->where('customers.id', $customerId)
                ->select('customers.*', 'companies.name as company_name')
                ->first();

            return response()->json([
                'message' => 'Customer created successfully',
                'customer' => $customer
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create customer: ' . $e->getMessage()
            ], 500);
        }
    }
}
