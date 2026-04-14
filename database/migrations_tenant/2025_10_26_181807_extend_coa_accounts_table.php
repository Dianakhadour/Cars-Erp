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
        Schema::table('coa_accounts', function (Blueprint $table) {
         
            $table->text('description')->nullable()->after('name');
            $table->enum('account_side', ['debit', 'credit'])->default('debit')->after('description');
            $table->enum('report_type', ['balance_sheet', 'income_statement'])->default('balance_sheet')->after('account_side');
            $table->integer('account_level')->default(1)->after('parent_id');
            $table->boolean('is_main')->default(false)->after('account_level');
            $table->enum('expenses_type', ['not_expense', 'work_expense', 'normal_expense'])->default('not_expense')->after('is_main');
            $table->boolean('is_active')->default(true)->after('expenses_type');
            $table->char('branch_id', 36)->nullable()->after('company_id');
            $table->json('metadata')->nullable()->after('is_active');
            $table->softDeletes()->after('updated_at');

  
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            
          
            $table->index(['company_id', 'branch_id', 'code'], 'coa_accounts_main_idx');
            $table->index(['parent_id', 'is_active'], 'coa_parent_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coa_accounts', function (Blueprint $table) {
            // Drop indexes with custom names
            $table->dropForeign(['branch_id']);
            $table->dropIndex('coa_accounts_main_idx');
            $table->dropIndex('coa_parent_idx');
            $table->dropColumn([
                'description', 'account_side', 'report_type', 'account_level',
                'is_main', 'expenses_type', 'is_active', 'branch_id', 'metadata',
                'deleted_at'
            ]);
        });
    }
};
