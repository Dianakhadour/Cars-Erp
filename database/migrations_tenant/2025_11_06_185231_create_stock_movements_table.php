<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedBigInteger('item_id');
            
            // Movement Details
            $table->enum('movement_type', [
                'purchase_receipt', 'purchase_return', 
                'sale_issue', 'sale_return',
                'transfer_in', 'transfer_out',
                'adjustment_in', 'adjustment_out',
                'production_in', 'production_out',
                'waste_scrap', 'quality_reject'
            ]);
            
            // Quantities
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);
            
            // Locations
            $table->unsignedBigInteger('source_warehouse_id')->nullable();
            $table->unsignedBigInteger('source_bin_id')->nullable();
            $table->unsignedBigInteger('destination_warehouse_id')->nullable();
            $table->unsignedBigInteger('destination_bin_id')->nullable();
            
            // Reference to source documents
            $table->unsignedBigInteger('referenceable_id')->nullable();
            $table->string('referenceable_type')->nullable();
            $table->string('reference_number')->nullable();
            
            // Lot/Serial Tracking
            $table->string('lot_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('expiry_date')->nullable();
            
            // Vehicle-specific
            $table->json('vehicle_attributes')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'pending', 'completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            
            // Audit - use uuid for created_by
            $table->uuid('created_by');
            $table->timestamp('movement_date')->useCurrent();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
                  
            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');
                  
            $table->foreign('source_warehouse_id')
                  ->references('id')
                  ->on('warehouses')
                  ->onDelete('restrict');
                  
            $table->foreign('destination_warehouse_id')
                  ->references('id')
                  ->on('warehouses')
                  ->onDelete('restrict');
                  
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'movement_type']);
            $table->index(['company_id', 'item_id']);
            $table->index(['company_id', 'movement_date']);
            $table->index(['referenceable_id', 'referenceable_type']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_movements');
    }
};