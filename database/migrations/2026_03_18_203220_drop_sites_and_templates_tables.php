<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['site_subscription_id']);
            $table->dropColumn('site_subscription_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropColumn('site_id');
        });

        DB::table('products')->where('productable_type', 'App\Models\Template')->delete();

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('newsletter_posts');
        Schema::dropIfExists('newsletter_subscriptions');
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('site_blocks');
        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('site_media');
        Schema::dropIfExists('site_versions');
        Schema::dropIfExists('site_subscriptions');
        Schema::dropIfExists('site_invitations');
        Schema::dropIfExists('site_user');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('template_pages');
        Schema::dropIfExists('templates');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations. Not restorable without original migrations.
     */
    public function down(): void {}
};
