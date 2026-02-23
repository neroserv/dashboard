<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('order_confirmation_line_items');
        Schema::dropIfExists('order_confirmations');
        Schema::dropIfExists('quote_line_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('reminders');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tables are not recreated; use original migrations if rollback is needed.
    }
};
