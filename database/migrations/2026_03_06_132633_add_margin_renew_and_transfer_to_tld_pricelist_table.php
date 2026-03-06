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
        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->decimal('margin_renew_value', 10, 2)->nullable()->after('margin_value');
            $table->decimal('margin_transfer_value', 10, 2)->nullable()->after('margin_renew_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropColumn(['margin_renew_value', 'margin_transfer_value']);
        });
    }
};
