<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * mollie_payment_id = Einmalzahlungs-Referenz (Idempotenz). mollie_subscription_id nur für echte Abos.
     */
    public function up(): void
    {
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->string('mollie_payment_id')->nullable()->after('mollie_subscription_id');
        });

        // Bestehende Accounts: Wurde Payment-ID fälschlich in mollie_subscription_id gespeichert (Einmalkauf).
        // Korrigieren: als Einmalzahlung behandeln, damit Verlängern wieder möglich ist.
        DB::table('game_server_accounts')
            ->whereNotNull('mollie_subscription_id')
            ->where('renewal_type', 'auto')
            ->update([
                'mollie_payment_id' => DB::raw('mollie_subscription_id'),
                'mollie_subscription_id' => null,
                'renewal_type' => 'manual',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->dropColumn('mollie_payment_id');
        });
    }
};
