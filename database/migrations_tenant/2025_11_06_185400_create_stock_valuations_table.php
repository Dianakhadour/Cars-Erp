<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('stock_valuations', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('bin_id')->nullable();
            $table->decimal('quantity_on_hand', 12, 4)->default(0);
            $table->decimal('quantity_reserved', 12, 4)->default(0);
            $table->decimal('quantity_available', 12, 4)->virtualAs('quantity_on_hand - quantity_reserved');
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);
            $table->decimal('last_purchase_cost', 15, 4)->default(0);
            $table->json('cost_layers')->nullable();
            $table->timestamp('last_receipt_date')->nullable();
            $table->timestamp('last_issue_date')->nullable();
            $table->decimal('last_receipt_quantity', 12, 4)->default(0);
            $table->decimal('last_issue_quantity', 12, 4)->default(0);
            $table->integer('days_in_stock')->default(0);
            $table->decimal('turnover_rate', 8, 2)->default(0);
            $table->decimal('current_market_value', 15, 4)->nullable();
            $table->decimal('depreciation_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('bin_id')->references('id')->on('bins')->onDelete('cascade');
            $table->unique(['item_id', 'warehouse_id', 'bin_id']);
            $table->index(['company_id', 'item_id']);
        });
    }
    public function down() { Schema::dropIfExists('stock_valuations'); }
};           
