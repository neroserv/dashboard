<?php

use App\Jobs\CreateSiteRenewalInvoicesJob;
use App\Models\Invoice;
use App\Models\Site;
use App\Models\SiteSubscription;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('create site renewal invoices job does not create invoice when template has no price', function (): void {
    $site = Site::factory()->create(['is_legacy' => false, 'status' => 'active']);
    if ($site->template) {
        $site->template->update(['price' => 0]);
    }
    SiteSubscription::create([
        'site_id' => $site->id,
        'mollie_subscription_id' => 'sub_'.Str::random(14),
        'mollie_status' => 'active',
        'current_period_ends_at' => Carbon::now()->addDays(10),
        'cancel_at_period_end' => false,
    ]);

    $beforeCount = Invoice::where('type', 'subscription_renewal')->count();

    (new CreateSiteRenewalInvoicesJob(7, 14))->handle(
        app(\App\Services\InvoicePdfService::class),
        app(\App\Services\InvoiceEInvoiceService::class)
    );

    expect(Invoice::where('type', 'subscription_renewal')->count())->toBe($beforeCount);
});

test('create site renewal invoices job does not create duplicate invoice for same period', function (): void {
    $site = Site::factory()->create(['is_legacy' => false, 'status' => 'active']);
    $periodEndsAt = Carbon::now()->addDays(10);
    $sub = SiteSubscription::create([
        'site_id' => $site->id,
        'mollie_subscription_id' => 'sub_'.Str::random(14),
        'mollie_status' => 'active',
        'current_period_ends_at' => $periodEndsAt,
        'cancel_at_period_end' => false,
    ]);

    Invoice::create([
        'user_id' => $site->user_id,
        'site_subscription_id' => $sub->id,
        'number' => 'INV-2026-00001',
        'type' => 'subscription_renewal',
        'amount' => 10,
        'tax' => 0,
        'status' => 'sent',
        'billing_period_start' => $periodEndsAt,
        'billing_period_end' => $periodEndsAt->copy()->addMonth(),
        'invoice_date' => now(),
        'due_date' => $periodEndsAt,
    ]);

    $beforeCount = Invoice::where('type', 'subscription_renewal')->count();

    (new CreateSiteRenewalInvoicesJob(7, 14))->handle(
        app(\App\Services\InvoicePdfService::class),
        app(\App\Services\InvoiceEInvoiceService::class)
    );

    expect(Invoice::where('type', 'subscription_renewal')->count())->toBe($beforeCount);
});
