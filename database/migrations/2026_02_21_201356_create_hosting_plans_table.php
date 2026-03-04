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
        Schema::create('hosting_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plesk_package_name');
            $table->unsignedInteger('disk_gb')->default(0);
            $table->unsignedInteger('traffic_gb')->default(0);
            $table->unsignedInteger('domains')->default(1);
            $table->unsignedInteger('subdomains')->default(0);
            $table->unsignedInteger('mailboxes')->default(0);
            $table->unsignedInteger('databases')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hosting_plans');
    }
};
