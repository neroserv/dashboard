<?php

use App\Models\Brand;
use App\Models\WebspaceAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * mollie_payment_id = Einmalzahlungs-Referenz (Prepaid). mollie_subscription_id nur für echte Abo-Marken.
     */
    public function up(): void
    {
        Schema::table('webspace_accounts', function (Blueprint $table) {
            $table->string('mollie_payment_id')->nullable()->after('mollie_subscription_id');
        });

        $prepaidBrandIds = Brand::all()->filter(fn (Brand $b) => ($b->getFeaturesArray()['prepaid_balance'] ?? false))->pluck('id')->all();
        if ($prepaidBrandIds !== []) {
            WebspaceAccount::query()
                ->whereNotNull('mollie_subscription_id')
                ->where('renewal_type', 'auto')
                ->whereHas('user', fn ($q) => $q->whereIn('brand_id', $prepaidBrandIds))
                ->each(function (WebspaceAccount $wa): void {
                    $wa->update([
                        'mollie_payment_id' => $wa->mollie_subscription_id,
                        'mollie_subscription_id' => null,
                        'renewal_type' => 'manual',
                    ]);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webspace_accounts', function (Blueprint $table) {
            $table->dropColumn('mollie_payment_id');
        });
    }
};
