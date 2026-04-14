<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TenantUserController extends Controller
{
     /**
     * Add a new tenant user
     */
    public function addTenantUser(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:companies,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant_users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'nullable|string|max:50',
            'role' => [
                'required',
                Rule::in([
                    'technician', 
                    'service_advisor', 
                    'workshop_manager', 
                    'inventory_manager',
                    'receptionist',
                    'admin'
                ])
            ],
            'technician_level' => 'nullable|in:trainee,junior,senior,master',
            'specializations' => 'nullable|array',
            'hourly_rate' => 'nullable|numeric|min:0',
            'is_available' => 'boolean',
            'hire_date' => 'nullable|date',
            'is_active' => 'boolean',
            'bio' => 'nullable|string',
            'certifications' => 'nullable|array',
            'profile_image' => 'nullable|string'
        ]);

        try {
            $userId = (string) Str::uuid();
            
            // Build user data with proper null checks
            $userData = [
                'id' => $userId,
                'company_id' => $validated['company_id'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'employee_id' => $validated['employee_id'] ?? null,
                'role' => $validated['role'],
                'technician_level' => $validated['technician_level'] ?? null,
                'specializations' => isset($validated['specializations']) ? json_encode($validated['specializations']) : null,
                'hourly_rate' => $validated['hourly_rate'] ?? null,
                'is_available' => $validated['is_available'] ?? true,
                'hire_date' => $validated['hire_date'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'bio' => $validated['bio'] ?? null,
                'certifications' => isset($validated['certifications']) ? json_encode($validated['certifications']) : null,
                'profile_image' => $validated['profile_image'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('tenant_users')->insert($userData);

            // Get the created user with company info
            $user = DB::table('tenant_users')
                ->join('companies', 'tenant_users.company_id', '=', 'companies.id')
                ->where('tenant_users.id', $userId)
                ->select('tenant_users.*', 'companies.name as company_name')
                ->first();

            return response()->json([
                'message' => 'Tenant user created successfully',
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create tenant user: ' . $e->getMessage()
            ], 500);
        }
    }
}
