<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * No-op: subscription_items table removed (Mollie Cashier uses orders/order_items).
     */
    public function up(): void
    {
        if (Schema::hasTable('subscription_items')) {
            Schema::table('subscription_items', function (Blueprint $table) {
                if (! Schema::hasColumn('subscription_items', 'meter_id')) {
                    $table->string('meter_id')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_items') && Schema::hasColumn('subscription_items', 'meter_id')) {
            Schema::table('subscription_items', function (Blueprint $table) {
                $table->dropColumn('meter_id');
            });
        }
    }
};
