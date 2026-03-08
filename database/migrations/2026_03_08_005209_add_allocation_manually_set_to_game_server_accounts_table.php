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
            $table->boolean('allocation_manually_set')->default(false)->after('allocation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_server_accounts', function (Blueprint $table) {
            $table->dropColumn('allocation_manually_set');
        });
    }
};
