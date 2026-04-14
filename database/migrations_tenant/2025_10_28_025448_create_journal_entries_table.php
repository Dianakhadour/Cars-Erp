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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('company_id', 36);
            $table->char('branch_id', 36)->nullable();
            
            // Identification
            $table->string('journal_entry_number')->unique();
            $table->date('issue_date');
            $table->date('posting_date');
            
            // Classification
            $table->enum('journal_entry_type', [
                'manual', 'invoice', 'purchase', 'payroll', 'closing', 'adjustment',
                'vehicle_sale', 'parts_sale', 'service_invoice', 'vehicle_purchase'
            ])->default('manual');
            $table->enum('journal_entry_by', ['manual', 'automatic'])->default('manual');
            
            // Source document reference
            $table->char('document_id', 36)->nullable();
            $table->string('document_ref')->nullable();
            $table->text('narration')->nullable();
            
            // Status and totals
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->integer('finance_year');
            $table->decimal('total_debit', 15, 4)->default(0);
            $table->decimal('total_credit', 15, 4)->default(0);
            
            // Currency (for future multi-currency support)
            $table->string('currency', 3)->default('SAR');
            $table->decimal('exchange_rate', 10, 6)->default(1);
            
            // Audit
            $table->char('created_by', 36)->nullable();
            $table->char('posted_by', 36)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('posted_by')->references('id')->on('users');
            
            // Indexes
            $table->index(['company_id', 'branch_id', 'finance_year'], 'je_company_branch_year_idx');
            $table->index(['issue_date', 'status'], 'je_date_status_idx');
            $table->index(['journal_entry_type', 'status'], 'je_type_status_idx');
            $table->index('journal_entry_number', 'je_number_idx');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
