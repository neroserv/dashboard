<?php

namespace App\Http\Controllers;

use App\Models\BalanceTransaction;
use App\Models\CustomerBalance;
use App\Models\GameServerAccount;
use App\Models\Invoice;
use App\Models\TeamSpeakServerAccount;
use App\Models\User;
use App\Models\WebspaceAccount;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\AiTokenService;
use App\Services\InvoiceEInvoiceService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use Symfony\Component\HttpFoundation\Response;

class MollieWebhookController extends CashierWebhookController
{
    public function __construct(
        \Laravel\Cashier\Mollie\Contracts\GetMolliePayment $getMolliePayment,
        protected InvoicePdfService $invoicePdfService,
        protected InvoiceEInvoiceService $invoiceEInvoiceService,
        protected AiTokenService $aiTokenService
    ) {
        parent::__construct($getMolliePayment);
    }

    /**
     * Handle Mollie webhook: process custom payments (balance top-up, invoice) or delegate to Cashier.
     */
    public function handleWebhook(Request $request): Response
    {
        $paymentId = $request->get('id');
        $payment = $this->getMolliePaymentById($paymentId);

        if (! $payment) {
            Log::info('Mollie webhook: payment not found or not retrievable', ['id' => $paymentId]);

            return new Response(null, 200);
        }

        $metadataArray = $payment->metadata !== null ? (is_array($payment->metadata) ? $payment->metadata : (array) $payment->metadata) : [];
        Cache::put('mollie_last_webhook_at', now()->toIso8601String(), now()->addDays(30));
        Log::info('Mollie webhook: payment received', [
            'id' => $payment->id,
            'status' => $payment->status,
            'metadata' => $metadataArray,
        ]);

        if ($payment->status === PaymentStatus::STATUS_PAID) {
            if ($this->handlePrepaidSubscriptionPayment($payment)) {
                return new Response(null, 200);
            }
            $handled = $this->handleBalanceTopUp($payment)
                || $this->handleAiTokenPayment($payment)
                || $this->handleInvoicePayment($payment);
            if ($handled) {
                return new Response(null, 200);
            }
        } else {
            Log::debug('Mollie webhook: payment not paid, skipping balance/invoice handling', [
                'id' => $payment->id,
                'status' => $payment->status,
            ]);
        }

        return parent::handleWebhook($request);
    }

    /**
     * When a Mollie subscription charges, extend the linked prepaid account's period by 1 month.
     * Returns true if the payment was handled (prepaid game/server/webspace/teamspeak account found).
     */
    protected function handlePrepaidSubscriptionPayment(Payment $payment): bool
    {
        $subscriptionId = $payment->subscriptionId ?? null;
        if ($subscriptionId === null || $subscriptionId === '') {
            return false;
        }

        $account = GameServerAccount::where('mollie_subscription_id', $subscriptionId)->first();
        if ($account) {
            $from = $account->current_period_ends_at && $account->current_period_ends_at->isFuture()
                ? $account->current_period_ends_at
                : now();
            $account->update([
                'current_period_ends_at' => $from->copy()->addMonth(),
                'status' => 'active',
            ]);
            Log::info('Mollie webhook: prepaid subscription payment extended game server', [
                'account_id' => $account->id,
                'subscription_id' => $subscriptionId,
                'payment_id' => $payment->id,
            ]);

            return true;
        }

        $account = WebspaceAccount::where('mollie_subscription_id', $subscriptionId)->first();
        if ($account) {
            $from = $account->current_period_ends_at && $account->current_period_ends_at->isFuture()
                ? $account->current_period_ends_at
                : now();
            $account->update([
                'current_period_ends_at' => $from->copy()->addMonth(),
                'status' => 'active',
            ]);
            Log::info('Mollie webhook: prepaid subscription payment extended webspace', [
                'account_id' => $account->id,
                'subscription_id' => $subscriptionId,
                'payment_id' => $payment->id,
            ]);

            return true;
        }

        $account = TeamSpeakServerAccount::where('mollie_subscription_id', $subscriptionId)->first();
        if ($account) {
            $from = $account->current_period_ends_at && $account->current_period_ends_at->isFuture()
                ? $account->current_period_ends_at
                : now();
            $account->update([
                'current_period_ends_at' => $from->copy()->addMonth(),
                'status' => 'active',
            ]);
            Log::info('Mollie webhook: prepaid subscription payment extended TeamSpeak server', [
                'account_id' => $account->id,
                'subscription_id' => $subscriptionId,
                'payment_id' => $payment->id,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Process balance top-up payment (metadata balance_topup = 1). Returns true if handled.
     */
    protected function handleBalanceTopUp(Payment $payment): bool
    {
        $metadata = $payment->metadata;
        $meta = $metadata !== null ? (is_array($metadata) ? $metadata : (array) $metadata) : [];

        $balanceTopUp = $meta['balance_topup'] ?? null;
        $isBalanceTopUp = $balanceTopUp === '1' || $balanceTopUp === 1 || $balanceTopUp === true;
        if (! $isBalanceTopUp) {
            Log::debug('Mollie webhook: not a balance top-up (metadata.balance_topup)', [
                'payment_id' => $payment->id,
                'balance_topup_value' => $balanceTopUp,
                'metadata_keys' => array_keys($meta),
            ]);

            return false;
        }

        $userId = $meta['user_id'] ?? null;
        $amountEur = isset($meta['amount_eur']) ? (float) $meta['amount_eur'] : 0;
        if ($amountEur <= 0) {
            $amountEur = (float) ($payment->amount->value ?? 0);
        }
        if (! $userId) {
            Log::warning('Mollie webhook balance_topup: missing user_id in metadata', [
                'payment_id' => $payment->id,
                'metadata' => $meta,
            ]);

            return true;
        }
        if ($amountEur <= 0) {
            Log::warning('Mollie webhook balance_topup: invalid amount', [
                'payment_id' => $payment->id,
                'metadata' => $meta,
            ]);

            return true;
        }

        if (Invoice::where('mollie_payment_id', $payment->id)->exists()) {
            Log::debug('Mollie webhook balance_topup: already processed', ['payment_id' => $payment->id]);

            return true;
        }

        $user = User::find($userId);
        if (! $user) {
            Log::warning('Mollie webhook balance_topup: user not found', ['user_id' => $userId, 'payment_id' => $payment->id]);

            return true;
        }

        $invoice = DB::transaction(function () use ($payment, $user, $amountEur) {
            $year = date('Y');
            $nextSeq = (int) Invoice::whereYear('invoice_date', $year)->max('id') + 1;
            $number = 'INV-'.$year.'-'.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'user_id' => $user->id,
                'site_subscription_id' => null,
                'mollie_payment_id' => $payment->id,
                'number' => $number,
                'type' => 'prepaid_charge',
                'amount' => $amountEur,
                'tax' => 0,
                'status' => 'paid',
                'billing_period_start' => null,
                'billing_period_end' => null,
                'invoice_date' => now(),
                'metadata' => ['mollie_payment_id' => $payment->id],
            ]);

            BalanceTransaction::create([
                'user_id' => $user->id,
                'amount' => $amountEur,
                'type' => 'mollie_topup',
                'description' => 'Guthaben aufladen (Mollie)',
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
            ]);

            $balance = CustomerBalance::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );
            $balance->increment('balance', $amountEur);

            return $invoice;
        });

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

        try {
            $invoice->user->notify(new InvoiceCreatedNotification($invoice));
        } catch (\Throwable $e) {
            report($e);
        }

        Log::info('Mollie webhook: balance top-up processed', [
            'user_id' => $user->id,
            'amount' => $amountEur,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
        ]);

        return true;
    }

    /**
     * Process invoice payment (metadata type = invoice_payment). Returns true if handled.
     */
    protected function handleInvoicePayment(Payment $payment): bool
    {
        $metadata = $payment->metadata;
        if (! $metadata || ($metadata->type ?? null) !== 'invoice_payment') {
            return false;
        }

        $invoiceId = (int) ($metadata->invoice_id ?? 0);
        $userId = $metadata->user_id ?? null;
        if ($invoiceId < 1 || ! $userId) {
            return true;
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice || (string) $invoice->user_id !== (string) $userId) {
            return true;
        }

        if ($invoice->status === 'paid') {
            Log::debug('Mollie webhook invoice_payment: already paid', ['invoice_id' => $invoiceId]);

            return true;
        }

        $invoice->update([
            'status' => 'paid',
            'mollie_payment_id' => $payment->id,
            'metadata' => array_merge($invoice->metadata ?? [], ['payment_method' => 'mollie']),
        ]);

        try {
            $pdfPath = $this->invoicePdfService->generate($invoice->fresh(['user.brand', 'siteSubscription.site', 'lineItems']));
            if ($pdfPath) {
                $invoice->update(['pdf_path' => $pdfPath]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $xmlPath = $this->invoiceEInvoiceService->generate($invoice->fresh(['user', 'lineItems']));
            if ($xmlPath) {
                $invoice->update(['invoice_xml_path' => $xmlPath]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        Log::info('Mollie webhook: invoice payment processed', ['invoice_id' => $invoice->id, 'payment_id' => $payment->id]);

        return true;
    }

    /**
     * Process AI token purchase (metadata type = ai_tokens). Returns true if handled.
     */
    protected function handleAiTokenPayment(Payment $payment): bool
    {
        $metadata = $payment->metadata;
        $meta = $metadata !== null ? (is_array($metadata) ? $metadata : (array) $metadata) : [];
        if (($meta['type'] ?? null) !== 'ai_tokens') {
            return false;
        }

        $userId = $meta['user_id'] ?? null;
        $tokenAmount = isset($meta['token_amount']) ? (int) $meta['token_amount'] : 0;
        if (! $userId || $tokenAmount <= 0) {
            Log::warning('Mollie webhook ai_tokens: missing user_id or token_amount', [
                'payment_id' => $payment->id,
                'metadata' => $meta,
            ]);

            return true;
        }

        if (Invoice::where('mollie_payment_id', $payment->id)->exists()) {
            Log::debug('Mollie webhook ai_tokens: already processed', ['payment_id' => $payment->id]);

            return true;
        }

        $user = User::find($userId);
        if (! $user) {
            Log::warning('Mollie webhook ai_tokens: user not found', ['user_id' => $userId, 'payment_id' => $payment->id]);

            return true;
        }

        $amountEur = (float) ($payment->amount->value ?? 0);

        $invoice = DB::transaction(function () use ($payment, $user, $tokenAmount, $amountEur) {
            $year = date('Y');
            $nextSeq = (int) Invoice::whereYear('invoice_date', $year)->max('id') + 1;
            $number = 'INV-'.$year.'-'.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'user_id' => $user->id,
                'site_subscription_id' => null,
                'mollie_payment_id' => $payment->id,
                'number' => $number,
                'type' => 'ai_tokens',
                'amount' => $amountEur,
                'tax' => 0,
                'status' => 'paid',
                'billing_period_start' => null,
                'billing_period_end' => null,
                'invoice_date' => now(),
                'metadata' => ['mollie_payment_id' => $payment->id],
            ]);

            $this->aiTokenService->addFromPurchase($user, $tokenAmount, 'AI-Token-Paket gekauft (Mollie)');

            return $invoice;
        });

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
        try {
            $invoice->user->notify(new InvoiceCreatedNotification($invoice));
        } catch (\Throwable $e) {
            report($e);
        }

        Log::info('Mollie webhook: AI token purchase processed', [
            'user_id' => $user->id,
            'token_amount' => $tokenAmount,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
        ]);

        return true;
    }
}
