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
        Schema::create('product_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->json('permissions')->nullable();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['shareable_type', 'shareable_id', 'email']);
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_invitations');
    }
};
