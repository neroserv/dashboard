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
            $table->boolean('auto_renew_with_balance')->default(false)->after('renewal_type');
        });
        Schema::table('webspace_accounts', function (Blueprint $table) {
            $table->boolean('auto_renew_with_balance')->default(false)->after('renewal_type');
        });
        Schema::table('team_speak_server_accounts', function (Blueprint $table) {
            $table->boolean('auto_renew_with_balance')->default(false)->after('renewal_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->dropColumn('auto_renew_with_balance');
        });
        Schema::table('webspace_accounts', function (Blueprint $table) {
            $table->dropColumn('auto_renew_with_balance');
        });
        Schema::table('team_speak_server_accounts', function (Blueprint $table) {
            $table->dropColumn('auto_renew_with_balance');
        });
    }
};
