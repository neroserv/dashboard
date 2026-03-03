<?php

use App\Models\User;

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
