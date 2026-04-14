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
        Schema::create('audit_log', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('actor_user_id')->nullable();
            $t->uuid('company_id')->nullable();
            $t->uuid('branch_id')->nullable();
            $t->string('action');
            $t->json('payload')->nullable();
            $t->json('metadata')->nullable();    
            $t->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
