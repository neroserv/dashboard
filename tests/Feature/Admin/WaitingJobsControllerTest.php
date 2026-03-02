<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests cannot access waiting jobs', function () {
    $response = $this->get(route('admin.waiting-jobs.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access waiting jobs', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.waiting-jobs.index'));
    $response->assertForbidden();
});

test('admin users can view waiting jobs page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.waiting-jobs.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/waiting-jobs/Index')
        ->has('waitingJobs')
        ->has('waitingJobs.data')
        ->has('waitingJobs.links')
    );
});

test('admin users see paginated waiting jobs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $now = time();
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => $now,
        'created_at' => $now,
    ]);

    $response = $this->get(route('admin.waiting-jobs.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/waiting-jobs/Index')
        ->has('waitingJobs.data')
        ->where('waitingJobs.data.0.queue', 'default')
    );
});
