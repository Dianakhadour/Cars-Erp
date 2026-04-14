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
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedBigInteger('warehouse_id');
            
            // Identification
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            
            // Location Structure
            $table->string('zone')->nullable();
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->string('level')->nullable();
            
            // Physical Properties
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('volume_capacity', 10, 2)->nullable();
            $table->decimal('weight_capacity', 10, 2)->nullable();
            $table->integer('unit_capacity')->nullable();
            
            // Status
            $table->enum('bin_type', ['storage', 'picking', 'receiving', 'shipping', 'quarantine'])->default('storage');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_occupied')->default(false);
            
            // Vehicle-specific
            $table->boolean('is_vehicle_space')->default(false);
            $table->string('vehicle_space_type')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
                  
            $table->foreign('warehouse_id')
                  ->references('id')
                  ->on('warehouses')
                  ->onDelete('cascade');

            // Unique constraint: code must be unique per warehouse
            $table->unique(['warehouse_id', 'code']);
            
            // Indexes
            $table->index(['company_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'zone', 'aisle']);
            $table->index('bin_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
