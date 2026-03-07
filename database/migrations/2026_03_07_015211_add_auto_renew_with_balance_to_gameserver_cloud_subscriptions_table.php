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
            $table->boolean('auto_renew_with_balance')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gameserver_cloud_subscriptions', function (Blueprint $table) {
            $table->dropColumn('auto_renew_with_balance');
        });
    }
};
