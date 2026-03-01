<?php

use App\Exceptions\InsufficientBalanceException;
use App\Models\BalanceTransaction;
use App\Models\CustomerBalance;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BalancePaymentService;
use App\Services\InvoiceEInvoiceService;
use App\Services\InvoicePdfService;

beforeEach(function () {
    $pdfService = Mockery::mock(InvoicePdfService::class)->shouldReceive('generate')->andReturn(null)->getMock();
    $eInvoiceService = Mockery::mock(InvoiceEInvoiceService::class)->shouldReceive('generate')->andReturn(null)->getMock();
    $this->app->instance(InvoicePdfService::class, $pdfService);
    $this->app->instance(InvoiceEInvoiceService::class, $eInvoiceService);
    $this->service = app(BalancePaymentService::class);
});

test('pay creates invoice balance transaction and reduces balance when sufficient', function () {
    $user = User::factory()->create();
    CustomerBalance::create(['user_id' => $user->id, 'balance' => 50]);

    $invoice = $this->service->pay($user, 20.50, 'webspace', 'Webspace Paket Basic', [
        'description' => 'Webspace Paket Basic – 1 Monat',
    ]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->type)->toBe('webspace')
        ->and((float) $invoice->amount)->toBe(20.50)
        ->and($invoice->status)->toBe('paid');

    $balance = CustomerBalance::where('user_id', $user->id)->first();
    expect($balance)->not->toBeNull()
        ->and((float) $balance->balance)->toBe(29.50);

    $tx = BalanceTransaction::where('user_id', $user->id)->where('type', 'payment_application')->first();
    expect($tx)->not->toBeNull()
        ->and((float) $tx->amount)->toBe(-20.50)
        ->and($tx->reference_type)->toBe(Invoice::class)
        ->and($tx->reference_id)->toBe($invoice->id);

    $this->assertDatabaseHas('invoice_line_items', [
        'invoice_id' => $invoice->id,
        'position' => 1,
        'description' => 'Webspace Paket Basic – 1 Monat',
    ]);
});

test('pay throws InsufficientBalanceException when balance too low', function () {
    $user = User::factory()->create();
    CustomerBalance::create(['user_id' => $user->id, 'balance' => 10]);

    $this->service->pay($user, 25.00, 'webspace', 'Webspace Paket', ['description' => 'Webspace']);
})->throws(InsufficientBalanceException::class);

test('pay throws when user has no balance record', function () {
    $user = User::factory()->create();

    $this->service->pay($user, 5.00, 'domain_purchase', 'Domain', ['description' => 'Domain']);
})->throws(InsufficientBalanceException::class);
