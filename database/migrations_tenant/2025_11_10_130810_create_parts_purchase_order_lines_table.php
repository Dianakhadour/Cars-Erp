<?php
 use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartsPurchaseOrderLinesTable extends Migration
{
    public function up()
    {
        Schema::create('parts_purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('purchase_order_id');
            $table->uuid('part_id');
            
            // Line Details
            $table->decimal('quantity_ordered', 15, 4);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('line_total', 15, 4);
            
            // Receiving Info
            $table->decimal('quantity_pending', 15, 4)->virtualAs('quantity_ordered - quantity_received');
            $table->boolean('is_fully_received')->default(false);
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('parts_purchase_orders')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_id', 'purchase_order_id']);
            $table->index(['part_id', 'is_fully_received']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('parts_purchase_order_lines');
    }
}