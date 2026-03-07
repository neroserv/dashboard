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
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->foreignId('gameserver_cloud_subscription_id')
                ->nullable()
                ->after('hosting_plan_id')
                ->constrained('gameserver_cloud_subscriptions')
                ->nullOnDelete();
            $table->json('allocation')->nullable()->after('option_values');
        });

        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->dropForeign(['hosting_plan_id']);
        });
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('hosting_plan_id')->nullable()->change();
            $table->foreign('hosting_plan_id')->references('id')->on('hosting_plans')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->dropForeign(['gameserver_cloud_subscription_id']);
            $table->dropColumn(['gameserver_cloud_subscription_id', 'allocation']);
        });

        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->dropForeign(['hosting_plan_id']);
            $table->unsignedBigInteger('hosting_plan_id')->nullable(false)->change();
            $table->foreign('hosting_plan_id')->references('id')->on('hosting_plans')->cascadeOnDelete();
        });
    }
};
