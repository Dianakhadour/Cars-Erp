<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class CreateTenantUsersTable extends Migration
{
    public function up()
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            
            // User Information
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('employee_id')->nullable();
            
            // Role and Permissions
            $table->enum('role', [
                'technician', 
                'service_advisor', 
                'workshop_manager', 
                'inventory_manager',
                'receptionist',
                'admin'
            ])->default('technician');
            
            $table->json('permissions')->nullable();
            
            // Workshop Specific Fields
            $table->enum('technician_level', ['trainee', 'junior', 'senior', 'master'])->nullable();
            $table->json('specializations')->nullable();  
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->boolean('is_available')->default(true);
            
            // Employment Details
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Profile
            $table->text('bio')->nullable();
            $table->json('certifications')->nullable();
            $table->string('profile_image')->nullable();
            
            // System
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_id', 'role']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'technician_level']);
            $table->index(['company_id', 'is_available']);
        });

        // Seed sample users for each tenant
        $this->seedTenantUsers();
    }

    public function down()
    {
        Schema::dropIfExists('tenant_users');
    }

   
}