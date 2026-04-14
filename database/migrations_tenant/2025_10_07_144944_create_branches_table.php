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
        Schema::create('branches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('company_id');
            $t->string('name');
            $t->string('code');
            $t->string('city')->nullable();
            $t->boolean('is_main')->default(false);
            $t->timestamps();

            $t->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
            $t->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
