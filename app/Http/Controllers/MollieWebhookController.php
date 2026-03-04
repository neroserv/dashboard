<?php

namespace App\Http\Controllers;

use App\Models\BalanceTransaction;
use App\Models\CustomerBalance;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\InvoiceEInvoiceService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
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
        protected InvoiceEInvoiceService $invoiceEInvoiceService
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

        Log::info('Mollie webhook: payment received', [
            'id' => $payment->id,
            'status' => $payment->status,
            'metadata' => $payment->metadata ? (array) $payment->metadata : null,
        ]);

        if ($payment->status === PaymentStatus::STATUS_PAID) {
            $handled = $this->handleBalanceTopUp($payment) || $this->handleInvoicePayment($payment);
            if ($handled) {
                return new Response(null, 200);
            }
        }

        return parent::handleWebhook($request);
    }

    /**
     * Process balance top-up payment (metadata balance_topup = 1). Returns true if handled.
     */
    protected function handleBalanceTopUp(Payment $payment): bool
    {
        $metadata = $payment->metadata;
        $meta = $metadata !== null ? (is_array($metadata) ? $metadata : (array) $metadata) : [];
        if (($meta['balance_topup'] ?? null) !== '1') {
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

        $invoice->user->notify(new InvoiceCreatedNotification($invoice));

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
}
