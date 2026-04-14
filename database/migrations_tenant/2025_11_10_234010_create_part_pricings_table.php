<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartPricingsTable extends Migration
{
    public function up()
    {
        Schema::create('part_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('part_id');
            $table->string('pricing_type');  
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('SAR');
            $table->integer('min_quantity')->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('part_id')->references('id')->on('parts')->onDelete('cascade');
            
            $table->index(['company_id', 'part_id', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('part_pricings');
    }
}