<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mollie Cashier: applied_coupons table.
     */
    public function up(): void
    {
        Schema::create('applied_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('redeemed_coupon_id');
            $table->morphs('model');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applied_coupons');
    }
};
