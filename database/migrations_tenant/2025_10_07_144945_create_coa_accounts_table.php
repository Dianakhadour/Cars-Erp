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
        Schema::create('coa_accounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->string('code');
            $t->string('name');
            $t->uuid('parent_id')->nullable();
            $t->string('type'); // asset, liability, equity, income, expense
            $t->boolean('is_posting')->default(false);
            $t->timestamps();

            $t->foreign('company_id')
            ->references('id')
            ->on('companies')
            ->onDelete('cascade');

            $t->foreign('parent_id')
                ->references('id')
                ->on('coa_accounts')
                ->onDelete('cascade');

            $t->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coa_accounts');
    }
};
