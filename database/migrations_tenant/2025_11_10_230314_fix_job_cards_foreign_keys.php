<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixJobCardsForeignKeys extends Migration
{
    public function up()
    {
        Schema::table('job_cards', function (Blueprint $table) {
            // Drop the incorrect foreign key constraints
            $table->dropForeign(['assigned_technician_id']);
            $table->dropForeign(['service_advisor_id']);
            
            // Add correct foreign key constraints pointing to tenant_users
            $table->foreign('assigned_technician_id')
                  ->references('id')
                  ->on('tenant_users')
                  ->onDelete('set null');
                  
            $table->foreign('service_advisor_id')
                  ->references('id')
                  ->on('tenant_users')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('job_cards', function (Blueprint $table) {
            // Drop the correct foreign keys
            $table->dropForeign(['assigned_technician_id']);
            $table->dropForeign(['service_advisor_id']);
            
            // Re-add the incorrect ones (if needed for rollback)
            $table->foreign('assigned_technician_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            $table->foreign('service_advisor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }
}