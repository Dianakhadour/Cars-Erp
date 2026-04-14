<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->string('po_number');
            $table->unsignedBigInteger('supplier_id');
            $table->date('po_date');
            $table->date('expected_delivery_date');
            $table->enum('status', ['draft', 'approved', 'partially_received', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('restrict');

            // Unique constraint for PO number per company
            $table->unique(['company_id', 'po_number']);
            
            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'po_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_orders');
    }
};
