<?php

use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\User;
use App\Models\Voucher;

test('authenticated user can view redeem voucher page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('billing.redeem-voucher'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('billing/RedeemVoucher'));
});

test('guest cannot view redeem voucher page', function () {
    $response = $this->get(route('billing.redeem-voucher'));

    $response->assertRedirect(route('login'));
});

test('user can redeem valid single-use voucher', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-redeem',
        'name' => 'Test',
        'domains' => null,
        'is_default' => true,
        'features' => ['prepaid_balance' => true],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $voucher = Voucher::create([
        'code' => 'TESTCODE123',
        'balance' => 25.50,
        'use_type' => 'single_use',
        'user_id' => null,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('billing.redeem-voucher.store'), [
        'code' => 'testcode123',
    ]);

    $response->assertRedirect(route('billing.redeem-voucher'));
    $response->assertSessionHas('success');

    $voucher->refresh();
    expect($voucher->redeemed_at)->not->toBeNull();

    $balance = CustomerBalance::where('user_id', $user->id)->first();
    expect($balance)->not->toBeNull();
    expect((float) $balance->balance)->toBe(25.50);
});

test('user cannot redeem same single-use voucher twice', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-redeem2',
        'name' => 'Test',
        'domains' => null,
        'is_default' => true,
        'features' => ['prepaid_balance' => true],
    ]);

    $user = User::factory()->create(['brand_id' => $brand->id]);
    $voucher = Voucher::create([
        'code' => 'ONCEONLY',
        'balance' => 10,
        'use_type' => 'single_use',
        'user_id' => null,
        'is_active' => true,
        'redeemed_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->post(route('billing.redeem-voucher.store'), [
        'code' => 'onceonly',
    ]);

    $response->assertRedirect(route('billing.redeem-voucher'));
    $response->assertSessionHas('error');

    expect(CustomerBalance::where('user_id', $user->id)->exists())->toBeFalse();
});

test('user cannot redeem invalid voucher code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('billing.redeem-voucher.store'), [
        'code' => 'NONEXISTENT',
    ]);

    $response->assertRedirect(route('billing.redeem-voucher'));
    $response->assertSessionHas('error');
});

test('user cannot redeem voucher assigned to another user', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'test-redeem3',
        'name' => 'Test',
        'domains' => null,
        'is_default' => true,
        'features' => ['prepaid_balance' => true],
    ]);

    $owner = User::factory()->create(['brand_id' => $brand->id]);
    $other = User::factory()->create(['brand_id' => $brand->id]);
    Voucher::create([
        'code' => 'ASSIGNED',
        'balance' => 15,
        'use_type' => 'single_use',
        'user_id' => $owner->id,
        'is_active' => true,
    ]);

    $this->actingAs($other);

    $response = $this->post(route('billing.redeem-voucher.store'), [
        'code' => 'assigned',
    ]);

    $response->assertRedirect(route('billing.redeem-voucher'));
    $response->assertSessionHas('error');

    expect(CustomerBalance::where('user_id', $other->id)->exists())->toBeFalse();
});
