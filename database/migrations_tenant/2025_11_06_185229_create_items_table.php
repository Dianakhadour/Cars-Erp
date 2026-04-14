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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedBigInteger('category_id');
            
            // Basic Identification
            $table->string('sku');
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            
            // Item Type
            $table->enum('item_type', ['vehicle', 'part', 'consumable', 'service'])->default('vehicle');
            
            // Vehicle Specific Fields
            $table->string('vin')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('color')->nullable();
            $table->integer('mileage')->nullable();
            $table->enum('vehicle_status', ['new', 'used', 'demo', 'reconditioned','sold'])->default('new');
            $table->text('vehicle_features')->nullable();
            
            // Inventory Management
            $table->string('unit_of_measure')->default('unit');
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('volume', 8, 2)->nullable();
            
            // Costing
            $table->enum('cost_method', ['fifo', 'lifo', 'weighted_average', 'standard'])->default('weighted_average');
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->decimal('last_purchase_cost', 15, 2)->default(0);
            
            // Pricing
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('min_price', 15, 2)->default(0);
            $table->decimal('max_price', 15, 2)->nullable();
            
            // Stock Control
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->nullable();
            $table->integer('reorder_point')->default(0);
            $table->integer('economic_order_qty')->nullable();
            
            // Status & Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_serialized')->default(false);
            $table->boolean('is_lot_tracked')->default(false);
            
            // Media
            $table->json('images')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Add foreign keys with proper types
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->onDelete('cascade');
                  
            $table->foreign('category_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('restrict');

            // Unique constraints with company scope
            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'barcode']);
            $table->unique(['company_id', 'vin']);

            // Indexes
            $table->index(['company_id', 'item_type']);
            $table->index(['make', 'model', 'year']);
            $table->index('vehicle_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
