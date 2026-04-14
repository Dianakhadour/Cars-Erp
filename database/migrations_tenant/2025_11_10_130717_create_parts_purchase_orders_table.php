<?php
 use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartsPurchaseOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('parts_purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->unsignedBigInteger('supplier_id');
            
            // PO Basic Info
            $table->string('po_number')->unique();
            $table->date('order_date');
            $table->date('expected_delivery_date');
            $table->enum('status', ['draft', 'ordered', 'partially_received', 'completed', 'cancelled'])->default('draft');
            
            // Financials
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0);
            
            // Logistics
            $table->unsignedBigInteger('warehouse_id');  
            $table->text('shipping_address')->nullable();
            $table->text('notes')->nullable();
          
            // Metadata
            $table->uuid('created_by')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
         
             
           

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        
          
            // Indexes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'order_date']);
            $table->index('po_number');
          
         
            $table->index([ 'warehouse_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('parts_purchase_orders');
    }
}