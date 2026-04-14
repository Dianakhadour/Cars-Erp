<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartMovementLinesTable extends Migration
{
    public function up()
    {
        Schema::create('part_movement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('movement_id');
            $table->uuid('part_id');
            
            // Line Details
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            
            // Batch/Serial Info  
            $table->string('batch_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('expiry_date')->nullable();
            
            // Location 
            $table->unsignedBigInteger('warehouse_id');  
            $table->uuid('location_id')->nullable();  
            
            // Status
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            
            $table->timestamps();

            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('movement_id')->references('id')->on('part_stock_movements')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            
            // Indexes
            $table->index(['company_id', 'movement_id']);
            $table->index(['part_id', 'warehouse_id']);
        });
    }

  
    public function down()
    {
        Schema::dropIfExists('part_movement_lines');
    }
}