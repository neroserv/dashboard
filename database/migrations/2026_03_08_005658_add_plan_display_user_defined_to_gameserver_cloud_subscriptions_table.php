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
        Schema::table('gameserver_cloud_subscriptions', function (Blueprint $table) {
            $table->boolean('plan_display_user_defined')->default(false)->after('gameserver_cloud_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gameserver_cloud_subscriptions', function (Blueprint $table) {
            $table->dropColumn('plan_display_user_defined');
        });
    }
};
