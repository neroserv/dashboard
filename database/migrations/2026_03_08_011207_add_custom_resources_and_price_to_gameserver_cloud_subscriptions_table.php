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
            $table->unsignedInteger('custom_max_cpu')->nullable()->after('plan_display_user_defined');
            $table->unsignedInteger('custom_max_memory_mb')->nullable()->after('custom_max_cpu');
            $table->unsignedInteger('custom_max_disk_gb')->nullable()->after('custom_max_memory_mb');
            $table->decimal('custom_price', 10, 2)->nullable()->after('custom_max_disk_gb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gameserver_cloud_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'custom_max_cpu',
                'custom_max_memory_mb',
                'custom_max_disk_gb',
                'custom_price',
            ]);
        });
    }
};
