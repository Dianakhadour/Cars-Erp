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
        Schema::create('chart_of_account_integrations', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->char('account_id', 36);
            $table->char('branch_id', 36)->nullable();
            $table->string('integration_type'); 
            $table->string('document_type')->nullable();  
            $table->integer('priority')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('coa_accounts')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            
            
            $table->index(['integration_type', 'document_type', 'branch_id'], 'coa_integrations_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('chart_of_account_integrations');
    }
};
