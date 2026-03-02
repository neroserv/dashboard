<?php

use App\Models\User;

test('guests cannot access jobs monitor', function () {
    $response = $this->get(route('admin.jobs-monitor.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access jobs monitor', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.jobs-monitor.index'));
    $response->assertForbidden();
});

test('admin users can view jobs monitor page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.jobs-monitor.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/jobs-monitor/Index')
        ->has('failedJobsCount')
        ->has('waitingJobsCount')
        ->has('finishedBatchesCount')
    );
});
