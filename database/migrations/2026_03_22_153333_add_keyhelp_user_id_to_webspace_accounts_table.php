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
        Schema::table('webspace_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('keyhelp_user_id')->nullable()->after('plesk_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webspace_accounts', function (Blueprint $table) {
            $table->dropColumn('keyhelp_user_id');
        });
    }
};
