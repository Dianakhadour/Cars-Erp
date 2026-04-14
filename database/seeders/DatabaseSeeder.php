<?php

namespace Database\Seeders;

use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
 
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use App\Models\Master\MasterUser;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        // Create Role
    $adminRole = Role::firstOrCreate(
        ['name' => 'admin'],
        ['id' => Str::uuid(), 'description' => 'System Administrator']
    );

    // Create User
    $admin = MasterUser::firstOrCreate(
        ['email' => 'admin@master.com'],
        [
            'id' => Str::uuid(),
            'name' => 'Master Admin',
            'password' => bcrypt('123456'),
            'active' => true
        ]
    );

    // Attach Role
    DB::table('user_roles')->updateOrInsert(
        ['user_id' => $admin->id, 'role_id' => $adminRole->id],
        ['id' => Str::uuid()]
    );
    }
}
