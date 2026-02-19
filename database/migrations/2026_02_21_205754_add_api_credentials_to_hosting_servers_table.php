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
        Schema::table('hosting_servers', function (Blueprint $table) {
            $table->string('api_username')->nullable()->after('api_token');
            $table->unsignedSmallInteger('port')->default(8443)->after('hostname');
            $table->boolean('use_ssl')->default(true)->after('port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hosting_servers', function (Blueprint $table) {
            $table->dropColumn(['api_username', 'port', 'use_ssl']);
        });
    }
};
