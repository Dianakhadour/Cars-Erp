<?php
 use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartStockMovementsTable extends Migration
{
    public function up()
    {
        Schema::create('part_stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('part_id');
            $table->unsignedBigInteger('warehouse_id');
            
            // Movement Details
            $table->enum('movement_type', [
                'receipt', 'issue', 'transfer', 'adjustment', 
                'reservation', 'allocation', 'return'
            ]);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            
            // References
            $table->string('reference_type')->nullable();  
            $table->uuid('reference_id')->nullable();
            $table->string('document_number')->nullable(); 
            
            // Location
            $table->uuid('source_location')->nullable(); 
            $table->uuid('destination_location')->nullable();  
            
            // Status
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            
            // Metadata
            $table->uuid('created_by')->nullable();
            $table->timestamp('movement_date');
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_id', 'movement_date']);
            $table->index(['part_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['movement_type', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('part_stock_movements');
    }
}