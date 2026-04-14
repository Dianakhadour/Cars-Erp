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
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        //  default roles
        DB::table('roles')->insert([
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Full system access'],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Company Admin', 'slug' => 'company-admin', 'description' => 'Company-level administration'],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Branch Manager', 'slug' => 'branch-manager', 'description' => 'Branch-level management'],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Financial operations'],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only access'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
