<?php

namespace App\Listeners;

use App\Models\BalanceTransaction;
use App\Models\CustomerBalance;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\InvoiceEInvoiceService;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class AddBalanceFromStripeWebhook
{
    public function __construct(
        protected InvoicePdfService $invoicePdfService,
        protected InvoiceEInvoiceService $invoiceEInvoiceService
    ) {}

    public function handle(WebhookReceived $event): void
    {
        if (($event->payload['type'] ?? null) !== 'checkout.session.completed') {
            return;
        }

        $session = $event->payload['data']['object'] ?? null;
        if (! $session) {
            return;
        }

        $metadata = $session['metadata'] ?? [];
        if (($metadata['balance_topup'] ?? null) !== '1') {
            return;
        }

        $sessionId = $session['id'] ?? null;
        if (! $sessionId) {
            return;
        }

        if (Invoice::where('metadata->stripe_checkout_session_id', $sessionId)->exists()) {
            Log::debug('Balance top-up webhook: session already processed', ['session_id' => $sessionId]);

            return;
        }

        $userId = $metadata['user_id'] ?? null;
        $amountEur = isset($metadata['amount_eur']) ? (float) $metadata['amount_eur'] : 0;
        if (! $userId || $amountEur <= 0) {
            $amountTotal = isset($session['amount_total']) ? (float) ($session['amount_total'] / 100) : 0;
            if ($amountTotal <= 0) {
                Log::warning('Balance top-up webhook: missing user_id or invalid amount', [
                    'session_id' => $sessionId,
                    'metadata' => $metadata,
                ]);

                return;
            }
            $amountEur = $amountTotal;
        }

        $user = User::find($userId);
        if (! $user) {
            Log::warning('Balance top-up webhook: user not found', ['user_id' => $userId, 'session_id' => $sessionId]);

            return;
        }

        $amountTotal = isset($session['amount_total']) ? (float) ($session['amount_total'] / 100) : $amountEur;

        $year = date('Y');
        $nextSeq = (int) Invoice::whereYear('invoice_date', $year)->max('id') + 1;
        $number = 'INV-'.$year.'-'.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'site_subscription_id' => null,
            'stripe_invoice_id' => null,
            'number' => $number,
            'type' => 'prepaid_charge',
            'amount' => $amountTotal,
            'tax' => 0,
            'status' => 'paid',
            'billing_period_start' => null,
            'billing_period_end' => null,
            'invoice_date' => now(),
            'metadata' => [
                'stripe_checkout_session_id' => $sessionId,
            ],
        ]);

        try {
            $pdfPath = $this->invoicePdfService->generate($invoice);
            if ($pdfPath) {
                $invoice->update(['pdf_path' => $pdfPath]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $xmlPath = $this->invoiceEInvoiceService->generate($invoice);
            if ($xmlPath) {
                $invoice->update(['invoice_xml_path' => $xmlPath]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        BalanceTransaction::create([
            'user_id' => $user->id,
            'amount' => $amountTotal,
            'type' => 'stripe_topup',
            'description' => 'Guthaben aufladen (Stripe)',
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
        ]);

        $balance = CustomerBalance::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0]
        );
        $balance->increment('balance', $amountTotal);

        $invoice->user->notify(new InvoiceCreatedNotification($invoice));

        Log::info('Balance top-up processed', [
            'user_id' => $user->id,
            'amount' => $amountTotal,
            'invoice_id' => $invoice->id,
            'session_id' => $sessionId,
        ]);
    }
}
