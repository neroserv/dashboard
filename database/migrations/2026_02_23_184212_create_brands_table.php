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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->json('domains')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('logo_url')->nullable();
            $table->json('theme_colors')->nullable();
            $table->json('features')->nullable();
            $table->string('salutation')->nullable();
            $table->text('mail_header')->nullable();
            $table->text('mail_footer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
