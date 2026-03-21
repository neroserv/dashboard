<?php

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;

test('guests cannot access admin tickets', function () {
    $response = $this->get(route('admin.tickets.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access admin tickets', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.tickets.index'));
    $response->assertForbidden();
});

test('admin users can view tickets index', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->get(route('admin.tickets.index'));
    $response->assertOk();
});

test('admin tickets index passes table state for sorting and search', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $this->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tickets/Index')
            ->has('tableState')
            ->where('tableState.sort', 'updated_at')
            ->where('tableState.direction', 'desc'));
});

test('admin tickets index hides closed tickets closed longer than 24 hours by default', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $stale = Ticket::factory()->create(['ticket_category_id' => $category->id, 'status' => 'open']);
    $stale->forceFill([
        'status' => 'closed',
        'closed_at' => now()->subHours(48),
    ])->saveQuietly();
    $recent = Ticket::factory()->create(['ticket_category_id' => $category->id, 'status' => 'open']);
    $recent->forceFill([
        'status' => 'closed',
        'closed_at' => now()->subHours(2),
    ])->saveQuietly();
    $this->actingAs($admin);
    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $this->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tickets.data', fn ($data) => ! collect($data)->pluck('id')->contains($stale->id)
                && collect($data)->pluck('id')->contains($recent->id)));
});

test('admin tickets index includes archived closed tickets when include_archived is set', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $stale = Ticket::factory()->create(['ticket_category_id' => $category->id, 'status' => 'open']);
    $stale->forceFill([
        'status' => 'closed',
        'closed_at' => now()->subHours(48),
    ])->saveQuietly();
    $this->actingAs($admin);
    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $this->get(route('admin.tickets.index', ['include_archived' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tickets.data', fn ($data) => collect($data)->pluck('id')->contains($stale->id)));
});

test('admin tickets index search finds ticket by subject', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $needle = 'UniqueTicketSubject'.uniqid();
    Ticket::factory()->create([
        'ticket_category_id' => $category->id,
        'subject' => $needle,
    ]);
    $this->actingAs($admin);
    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $this->get(route('admin.tickets.index', ['search' => $needle]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tickets.data', fn ($data) => count($data) >= 1
                && collect($data)->contains(fn ($row) => str_contains((string) ($row['subject'] ?? ''), $needle))));
});

test('admin tickets index exposes service display from ticket services', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    Ticket::factory()->create(['ticket_category_id' => $category->id]);
    $this->actingAs($admin);

    $this->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tickets/Index')
            ->has('tickets.data.0.service_display'));
});

test('admin tickets index includes assignee when ticket is assigned', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create([
        'ticket_category_id' => $category->id,
        'assigned_to' => $admin->id,
    ]);
    $this->actingAs($admin);
    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $this->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tickets/Index')
            ->where('tickets.data.0.id', $ticket->id)
            ->has('tickets.data.0.assigned_to')
            ->where('tickets.data.0.assigned_to.id', $admin->id));
});

test('admin ticket show exposes assigned_to id for the assignee', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create([
        'ticket_category_id' => $category->id,
        'assigned_to' => $admin->id,
    ]);
    $this->actingAs($admin);
    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $this->get(route('admin.tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/tickets/Show')
            ->where('ticket.assigned_to', $admin->id));
});

test('admin users can view ticket', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create(['ticket_category_id' => $category->id]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.tickets.show', $ticket));
    $response->assertOk();
});

test('admin users can update ticket', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create(['ticket_category_id' => $category->id, 'status' => 'open']);
    $this->actingAs($admin);

    $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminDomainForAdminRoutes::class);

    $response = $this->put(route('admin.tickets.update', $ticket), [
        'status' => 'in_progress',
        'ticket_category_id' => $ticket->ticket_category_id,
    ]);
    $response->assertRedirect(route('admin.tickets.show', $ticket));
    $ticket->refresh();
    expect($ticket->status)->toBe('in_progress');
});

test('admin users can post message on ticket', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $category = TicketCategory::factory()->create();
    $ticket = Ticket::factory()->create(['ticket_category_id' => $category->id]);
    $this->actingAs($admin);

    $response = $this->post(route('admin.tickets.messages.store', $ticket), [
        'body' => 'Admin reply',
        'is_internal' => false,
    ]);
    $response->assertRedirect(route('admin.tickets.show', $ticket));
    $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'Admin reply']);
});
