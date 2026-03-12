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
        Schema::create('product_shares', function (Blueprint $table) {
            $table->id();
            $table->string('shareable_type');
            $table->unsignedBigInteger('shareable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('permissions')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->unique(['shareable_type', 'shareable_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_shares');
    }
};
