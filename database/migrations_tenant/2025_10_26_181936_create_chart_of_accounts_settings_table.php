<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        Schema::create('chart_of_accounts_settings', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->char('branch_id', 36)->nullable();
            $table->date('finance_start_period');
            $table->date('finance_end_period');
            $table->integer('finance_year');
            $table->boolean('is_active')->default(false);
            $table->boolean('allow_backdated_entries')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            
            
            $table->unique(['company_id', 'branch_id', 'finance_year'], 'coa_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts_settings');
    } 
     
};
