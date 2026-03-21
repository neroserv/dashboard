<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Str;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard and receive dashboard props', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('stats')
        ->has('status')
        ->has('favorites')
        ->has('activeServices')
        ->has('recentEmails')
        ->has('recentInvoices')
        ->has('brandFeatures')
        ->where('stats.activeServicesCount', 0)
        ->where('stats.registeredAt', $user->created_at->format('d.m.Y'))
        ->where('status.level', 'ok')
    );
});

test('dashboard recent invoices expose status for client display', function () {
    $user = User::factory()->create();
    Invoice::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'number' => 'INV-DASH-'.Str::random(6),
        'type' => 'subscription',
        'amount' => 19.99,
        'tax' => 0,
        'status' => 'paid',
        'invoice_date' => now()->toDateString(),
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('recentInvoices', 1)
        ->where('recentInvoices.0.status', 'paid')
    );
});
