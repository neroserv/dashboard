<?php

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('creates client and invoice in Invoice Ninja when extension is configured', function () {
    $brand = Brand::query()->create([
        'key' => 'inv-ninja-'.uniqid('', true),
        'name' => 'Invoice Ninja Test Brand',
        'is_default' => false,
    ]);

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_INVOICE_NINJA,
        'installed_at' => now(),
        'settings' => [
            'base_url' => 'https://ninja.test',
            'api_token' => 'secret-token',
        ],
    ]);

    Http::fake([
        'https://ninja.test/api/v1/clients' => Http::response(['data' => ['id' => 'cl_hash_1']], 200),
        'https://ninja.test/api/v1/invoices' => Http::response(['data' => ['id' => 'inv_hash_1']], 200),
    ]);
    Http::preventStrayRequests();

    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Max Mustermann',
        'email' => 'max@example.test',
        'street' => 'Hauptstr.',
        'street_number' => '1',
        'postal_code' => '10115',
        'city' => 'Berlin',
        'state' => 'BE',
        'country' => 'DE',
        'phone' => '+49123456789',
    ]);

    $invoice = Invoice::create([
        'user_id' => $user->id,
        'number' => 'INV-NJ-001',
        'type' => 'manual',
        'amount' => 19.99,
        'tax' => 0,
        'status' => 'sent',
        'invoice_date' => now(),
        'due_date' => now()->addDays(14),
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'position' => 1,
        'description' => 'Test position',
        'quantity' => 1,
        'unit' => 'Stück',
        'unit_price' => 19.99,
        'amount' => 19.99,
    ]);

    $invoice->refresh();

    expect($invoice->metadata['invoice_ninja_invoice_id'] ?? null)->toBe('inv_hash_1');
    expect($user->fresh()->invoice_ninja_client_id)->toBe('cl_hash_1');

    Http::assertSentCount(2);
});

test('does not call Invoice Ninja when extension is not installed', function () {
    Http::fake();
    Http::preventStrayRequests();

    $brand = Brand::query()->create([
        'key' => 'no-inv-ninja-'.uniqid('', true),
        'name' => 'No Ninja Brand',
        'is_default' => false,
    ]);

    $user = User::factory()->create([
        'brand_id' => $brand->id,
    ]);

    $invoice = Invoice::create([
        'user_id' => $user->id,
        'number' => 'INV-NO-NJ',
        'type' => 'manual',
        'amount' => 5,
        'tax' => 0,
        'status' => 'draft',
        'invoice_date' => now(),
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'position' => 1,
        'description' => 'X',
        'quantity' => 1,
        'unit' => 'Stück',
        'unit_price' => 5,
        'amount' => 5,
    ]);

    Http::assertNothingSent();
});

test('skips sync when only pdf_path is updated', function () {
    $brand = Brand::query()->create([
        'key' => 'pdf-only-'.uniqid('', true),
        'name' => 'PDF Only Brand',
        'is_default' => false,
    ]);

    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_INVOICE_NINJA,
        'installed_at' => now(),
        'settings' => [
            'base_url' => 'https://ninja.test',
            'api_token' => 'secret-token',
        ],
    ]);

    Http::fake([
        'https://ninja.test/api/v1/clients' => Http::response(['data' => ['id' => 'cl_2']], 200),
        'https://ninja.test/api/v1/invoices' => Http::response(['data' => ['id' => 'inv_2']], 200),
    ]);
    Http::preventStrayRequests();

    $user = User::factory()->create(['brand_id' => $brand->id]);

    $invoice = Invoice::create([
        'user_id' => $user->id,
        'number' => 'INV-PDF',
        'type' => 'manual',
        'amount' => 10,
        'tax' => 0,
        'status' => 'sent',
        'invoice_date' => now(),
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'position' => 1,
        'description' => 'Line',
        'quantity' => 1,
        'unit' => 'Stück',
        'unit_price' => 10,
        'amount' => 10,
    ]);

    Http::assertSentCount(2);

    $invoice->refresh();
    $invoice->update(['pdf_path' => 'invoices/test.pdf']);

    Http::assertSentCount(2);
});
