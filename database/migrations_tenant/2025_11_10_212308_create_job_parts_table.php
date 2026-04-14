<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobPartsTable extends Migration
{
    public function up()
    {
        Schema::create('job_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('job_card_id');
            $table->uuid('part_id');  
             
            
            // Part Requirements
            $table->decimal('quantity_required', 10, 4)->default(1);
            $table->decimal('quantity_issued', 10, 4)->default(0);
            $table->decimal('quantity_used', 10, 4)->default(0);
            $table->decimal('quantity_returned', 10, 4)->default(0);
            
            // Pricing
            $table->decimal('estimated_unit_cost', 15, 4)->default(0);
            $table->decimal('actual_unit_cost', 15, 4)->default(0);
            $table->decimal('selling_price', 15, 4)->default(0);
            
            // Status
            $table->enum('status', [
                'pending', 'reserved', 'issued', 'used', 'returned', 'cancelled'
            ])->default('pending');
            
            // Reservation & Issue Details
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            
            // Movement References
            $table->uuid('reservation_movement_id')->nullable();
            $table->uuid('issue_movement_id')->nullable();
            $table->uuid('return_movement_id')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->unsignedBigInteger('warehouse_id');  

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        
         
            $table->index([ 'warehouse_id']);
            // Foreign Keys  
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('job_card_id')->references('id')->on('job_cards')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
             
            $table->foreign('reservation_movement_id')->references('id')->on('part_stock_movements')->onDelete('set null');
            $table->foreign('issue_movement_id')->references('id')->on('part_stock_movements')->onDelete('set null');
            $table->foreign('return_movement_id')->references('id')->on('part_stock_movements')->onDelete('set null');
            
            // Indexes
            $table->index(['company_id', 'job_card_id']);
            $table->index(['company_id', 'part_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_parts');
    }
}