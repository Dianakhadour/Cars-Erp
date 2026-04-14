<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create default permissions
        $permissions = [
            // Company permissions
            ['company.create', 'Create companies'],
            ['company.view', 'View companies'],
            ['company.edit', 'Edit companies'],
            ['company.delete', 'Delete companies'],
            
            // Branch permissions
            ['branch.create', 'Create branches'],
            ['branch.view', 'View branches'],
            ['branch.edit', 'Edit branches'],
            ['branch.delete', 'Delete branches'],
            
            // Reporting permissions
            ['report.branch.view', 'View single branch reports'],
            ['report.branch.multi', 'View multi-branch reports'],
            ['report.branch.all', 'View all branches reports'],
            
            // User management
            ['user.manage', 'Manage users'],
            ['role.manage', 'Manage roles'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => $permission[1],
                'slug' => $permission[0],
                'description' => $permission[1],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
