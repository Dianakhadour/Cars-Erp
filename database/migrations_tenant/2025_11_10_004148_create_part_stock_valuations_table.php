<?php
 use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartStockValuationsTable extends Migration
{
    public function up()
    {
        Schema::create('part_stock_valuations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('part_id');
            $table->unsignedBigInteger('warehouse_id');
               
            // Stock Quantities
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->decimal('quantity_reserved', 15, 4)->default(0);
            $table->decimal('quantity_available', 15, 4)->virtualAs('quantity_on_hand - quantity_reserved');
            $table->decimal('quantity_committed', 15, 4)->default(0);
            
            // Costing
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->decimal('last_cost', 15, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);
            
            // Stock Status
            $table->boolean('is_low_stock')->default(false);
            $table->boolean('is_out_of_stock')->default(false);
            $table->timestamp('last_movement_at')->nullable();
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
             // Indexes
            $table->unique(['part_id', 'warehouse_id']);
            $table->index(['company_id', 'is_low_stock']);
            $table->index(['company_id', 'is_out_of_stock']);
            $table->index(['part_id', 'quantity_available']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('part_stock_valuations');
    }
}