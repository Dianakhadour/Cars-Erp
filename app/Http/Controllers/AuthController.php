<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Master\MasterUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:master.users,email',  

        'password' => 'required|min:8|confirmed',
    ]);

    DB::connection('master')->beginTransaction();
    try {
        $user = MasterUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
       $role = Role::where('name', $request->role_name)->first();
         $adminRoleId = $role->id;
     //   $adminRoleId = '8f6e7587-f9d3-4997-87ce-ebf49691d14e';

   
        DB::connection('master')->table('user_roles')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 
            'user_id' => $user->id,
            'role_id' => $adminRoleId,
        ]);

        DB::connection('master')->commit();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ], 201);

    } catch (\Exception $e) {
        DB::connection('master')->rollBack();
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

         
        $user = MasterUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials'
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function profile(Request $request)
    { 
        return response()->json($request->user());
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

}
