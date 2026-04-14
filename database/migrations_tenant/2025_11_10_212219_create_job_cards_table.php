<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobCardsTable extends Migration
{
    public function up()
    {
        Schema::create('job_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            
            // Job Information
            $table->string('job_number')->unique();
            
        
             
            $table->unsignedBigInteger('vehicle_item_id');  
            $table->foreign('vehicle_item_id')->references('id')->on('items')->onDelete('cascade');
       
        
            // Customer Information
            $table->uuid('customer_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            
            // Vehicle Details  
            $table->string('vehicle_license')->nullable();
            $table->string('vehicle_vin')->nullable();
            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->integer('vehicle_year')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->integer('vehicle_mileage')->nullable();
            
            // Job Details
            $table->string('service_type'); 
            $table->text('work_description');
            $table->text('customer_complaint')->nullable();
            $table->text('technician_findings')->nullable();
            
            // Status & Dates
            $table->enum('status', [
                'pending', 'in_progress', 'waiting_parts', 'waiting_approval', 
                'completed', 'cancelled', 'on_hold'
            ])->default('pending');
            $table->timestamp('estimated_start')->nullable();
            $table->timestamp('estimated_completion')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_completion')->nullable();
            
            // Pricing
            $table->decimal('estimated_labor_cost', 15, 2)->default(0);
            $table->decimal('estimated_parts_cost', 15, 2)->default(0);
            $table->decimal('actual_labor_cost', 15, 2)->default(0);
            $table->decimal('actual_parts_cost', 15, 2)->default(0);
            $table->decimal('total_estimated_cost', 15, 2)->default(0);
            $table->decimal('total_actual_cost', 15, 2)->default(0);
            
            // Workshop Details
            $table->uuid('assigned_technician_id')->nullable();
            $table->uuid('service_advisor_id')->nullable();
            $table->uuid('workshop_bay_id')->nullable();
            
            $table->text('technician_notes')->nullable();
            $table->text('service_advisor_notes')->nullable();
            $table->text('customer_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys 
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            
            // Reference items table for vehicles
          
            // Customer reference  
            // $table->foreign('customer_id')
            //       ->references('id')
            //       ->on('customers')
            //       ->onDelete('set null');
                  
            // User references
            $table->foreign('assigned_technician_id')
                  ->references('id')
                  ->on('tenant_users')
                  ->onDelete('set null');
                  
            $table->foreign('service_advisor_id')
                  ->references('id')
                  ->on('tenant_users')
                  ->onDelete('set null');
            
            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'job_number']);
            $table->index(['company_id', 'vehicle_item_id']);
            // $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'assigned_technician_id']);
            $table->index(['vehicle_license']);
            $table->index(['vehicle_vin']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_cards');
    }
}