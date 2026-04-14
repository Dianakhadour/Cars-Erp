<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('purchase_order_id');
                $table->string('grn_number');
                $table->date('receipt_date');
                $table->unsignedBigInteger('warehouse_id');
                $table->text('notes')->nullable();
                $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
                $table->decimal('total_received_value', 15, 2)->default(0);
                $table->uuid('received_by');
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Add indexes first, foreign keys later
                $table->unique(['company_id', 'grn_number'], 'gr_company_grn_unique');
                $table->index(['company_id', 'purchase_order_id'], 'gr_company_po_index');
                $table->index(['company_id', 'receipt_date'], 'gr_company_date_index');
            });
            
            \Log::info('Goods receipts table created successfully');
        }
    }

    public function down()
    {
        Schema::dropIfExists('goods_receipts');
    }
};