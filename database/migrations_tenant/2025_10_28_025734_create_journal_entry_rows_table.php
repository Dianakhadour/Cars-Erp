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
        Schema::create('journal_entry_rows', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('journal_entry_id', 36);
            $table->char('account_id', 36);
            $table->char('company_id', 36);
            $table->char('branch_id', 36)->nullable();
            
            // Line details
            $table->integer('line_number');
            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);
            $table->decimal('currency_amount', 15, 4)->nullable();
            
            // Dates and classification
            $table->date('issue_date');
            $table->integer('finance_year');
            $table->enum('entry_type', ['normal', 'adjustment', 'reversal', 'closing'])->default('normal');
            
            // Dimensions 
            $table->char('cost_center_id', 36)->nullable();
            $table->char('project_id', 36)->nullable();
            
            // Reference
            $table->string('reference')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('coa_accounts')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            
            // Indexes
            $table->index(['journal_entry_id', 'line_number'], 'jer_entry_line_idx');
            $table->index(['account_id', 'issue_date'], 'jer_account_date_idx');
            $table->index(['company_id', 'finance_year'], 'jer_company_year_idx');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_rows');
    }
};
