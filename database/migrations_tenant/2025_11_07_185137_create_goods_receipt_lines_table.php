<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_receipt_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity_received', 12, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('line_total', 15, 4);
            $table->unsignedBigInteger('bin_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('vehicle_attributes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts')->onDelete('cascade');
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->onDelete('restrict');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
            $table->foreign('bin_id')->references('id')->on('bins')->onDelete('restrict');

            // Indexes with shorter names
            $table->index(['goods_receipt_id', 'purchase_order_line_id'], 'grl_gr_pol_index');
            $table->index(['goods_receipt_id', 'item_id'], 'grl_gr_item_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};