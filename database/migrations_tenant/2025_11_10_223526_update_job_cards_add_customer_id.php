<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateJobCardsAddCustomerId extends Migration
{
    public function up()
    {
        Schema::table('job_cards', function (Blueprint $table) {
            
            if (!Schema::hasColumn('job_cards', 'customer_id')) {
                $table->uuid('customer_id')->nullable()->after('vehicle_item_id');
            }
        });
    }

    public function down()
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
}