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
            $table->unsignedSmallInteger('port')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hosting_servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('port')->default(8443)->change();
        });
    }
};
