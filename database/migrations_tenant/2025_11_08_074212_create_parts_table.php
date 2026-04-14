<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartsTable extends Migration
{
    public function up()
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('category_id')->nullable();
            
            // Basic Information
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('barcode')->nullable();
            $table->string('oem_number')->nullable();
            $table->string('brand')->nullable();
            $table->string('model_compatibility')->nullable(); 
            
            // Inventory Details
            $table->string('unit_of_measure')->default('piece');
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('volume', 8, 2)->nullable();
            
            // Pricing
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('retail_price', 15, 2)->default(0);
            
            // Inventory Control
            $table->decimal('min_stock_level', 10, 2)->default(0);
            $table->decimal('max_stock_level', 10, 2)->default(0);
            $table->decimal('reorder_point', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trackable')->default(true);  
            
            // Additional Fields
            $table->text('specifications')->nullable(); 
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('part_categories')->onDelete('set null');
            
            // Indexes
            $table->index(['company_id', 'sku']);
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'is_active']);
            $table->index('oem_number');
            $table->index('barcode');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parts');
    }
}