<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedeemVoucherRequest;
use App\Models\BalanceTransaction;
use App\Models\CustomerBalance;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RedeemVoucherController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('billing/RedeemVoucher');
    }

    public function store(RedeemVoucherRequest $request): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->validated('code')));
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            return redirect()->route('billing.redeem-voucher')->with('error', 'Gutscheincode ungültig oder bereits eingelöst.');
        }

        if (! $voucher->is_active) {
            return redirect()->route('billing.redeem-voucher')->with('error', 'Gutscheincode ungültig oder bereits eingelöst.');
        }

        $user = $request->user();
        if ($voucher->user_id !== null && (int) $voucher->user_id !== (int) $user->id) {
            return redirect()->route('billing.redeem-voucher')->with('error', 'Dieser Gutschein ist einem anderen Kunden zugewiesen.');
        }

        if ($voucher->isRedeemed()) {
            return redirect()->route('billing.redeem-voucher')->with('error', 'Gutscheincode ungültig oder bereits eingelöst.');
        }

        $isMultiUse = $voucher->use_type === 'multi_use';
        if ($isMultiUse) {
            $alreadyRedeemed = BalanceTransaction::where('user_id', $user->id)
                ->where('reference_type', Voucher::class)
                ->where('reference_id', $voucher->id)
                ->exists();
            if ($alreadyRedeemed) {
                return redirect()->route('billing.redeem-voucher')->with('error', 'Diesen Gutschein hast du bereits eingelöst.');
            }
        }

        $amount = (float) $voucher->balance;
        if ($amount <= 0) {
            return redirect()->route('billing.redeem-voucher')->with('error', 'Gutscheincode ungültig oder bereits eingelöst.');
        }

        DB::transaction(function () use ($user, $voucher, $amount, $isMultiUse): void {
            BalanceTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'voucher_redemption',
                'description' => 'Gutschein eingelöst: '.$voucher->code,
                'reference_type' => Voucher::class,
                'reference_id' => $voucher->id,
            ]);

            $balance = CustomerBalance::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );
            $balance->increment('balance', $amount);

            if (! $isMultiUse) {
                $voucher->update(['redeemed_at' => now()]);
            }
        });

        return redirect()->route('billing.redeem-voucher')->with(
            'success',
            sprintf('Gutschein erfolgreich eingelöst. Es wurden %s € deinem Guthaben gutgeschrieben.', number_format($amount, 2, ',', '.'))
        );
    }
}
