<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * No-op: Sites/template_pages feature has been removed; tables dropped in a later migration.
     */
    public function up(): void
    {
        // Previously migrated custom_page_data into site_pages and site_blocks.
        // Sites product removed – migration left as no-op for compatibility.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op.
    }
};
