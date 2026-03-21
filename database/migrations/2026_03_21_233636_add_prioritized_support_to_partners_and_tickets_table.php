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
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('prioritized_support')->default(false)->after('is_active');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('prioritized_support')->default(false)->after('ticket_priority_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('prioritized_support');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('prioritized_support');
        });
    }
};
