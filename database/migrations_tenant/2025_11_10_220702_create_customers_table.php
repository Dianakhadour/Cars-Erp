<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCustomersTable extends Migration
{
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            
            // Basic Information
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            
            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();
            
            // Additional Details
            $table->enum('type', ['individual', 'corporate'])->default('individual');
            $table->string('company_name')->nullable();
            $table->string('tax_number')->nullable();
            
            // Contact Persons (for corporate customers)
            $table->json('contact_persons')->nullable();
            
            // Customer Classification
            $table->enum('customer_class', ['regular', 'premium', 'vip'])->default('regular');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Metadata
            $table->text('notes')->nullable();
            $table->json('preferences')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        // Seed sample customers for each tenant
        $this->seedSampleCustomers();
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }

   
}