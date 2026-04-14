<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TenantAuthController extends Controller
{
    /**
     * Register a new tenant user
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role_slug' => 'required|string|in:super-admin,company-admin,branch-manager,accountant,viewer',
        ]);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'active' => true,  
        ]);
        
        $role = Role::where('slug', $request->role_slug)->first();
    
        if ($role) {
            DB::table('user_roles')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->id,
                'role_id' => $role->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
         
       // $token = $user->createToken('tenant-api-token', ['tenant'])->plainTextToken;
        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $token,  
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ], 201);
    }

    /**
     * Login tenant user
     */
    public function login(Request $request) : JsonResponse
    {
         
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
        
            //   MANUAL AUTHENTICATION ON TENANT CONNECTION 
            $user = User::where('email', $request->email)
                ->where('active', true)
                ->first();
        
            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => 'Invalid credentials or user inactive'
                ]);
            }
        
            //   GENERATE JWT TOKEN FOR THE AUTHENTICATED USER 
            $token = JWTAuth::fromUser($user);
        
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => $user
            ]);
    }

    /**
     * Get current tenant user profile
     */
    public function profile(Request $request): JsonResponse
    {
        
        $user = JWTAuth::user()->load(['roles', 'branches.company']);

        return response()->json([
            'user' => $user
        ]);
    }
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(JWTAuth::refresh());
    }
    /**
     * Logout tenant user
     */
    public function logout(Request $request): JsonResponse
    {
        JWTAuth::logout();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Format token response
     */
    protected function respondWithToken($token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => JWTAuth::user()
        ]);
    }
}