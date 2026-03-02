<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests cannot access failed jobs', function () {
    $response = $this->get(route('admin.failed-jobs.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access failed jobs', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.failed-jobs.index'));
    $response->assertForbidden();
});

test('admin users can view failed jobs index', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.failed-jobs.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/failed-jobs/Index')
        ->has('failedJobs')
        ->has('failedJobs.data')
        ->has('failedJobs.links')
    );
});

test('admin users can retry a failed job', function () {
    $id = DB::table('failed_jobs')->insertGetId([
        'uuid' => (string) str()->uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['job' => 'test']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->post(route('admin.failed-jobs.retry', $id));
    $response->assertRedirect(route('admin.failed-jobs.index'));
    $response->assertSessionHas('success');
});

test('admin users can retry all failed jobs', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['job' => 'test']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->post(route('admin.failed-jobs.retry-all'));
    $response->assertRedirect(route('admin.failed-jobs.index'));
    $response->assertSessionHas('success');
});

test('admin users can destroy a failed job', function () {
    $id = DB::table('failed_jobs')->insertGetId([
        'uuid' => (string) str()->uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['job' => 'test']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->delete(route('admin.failed-jobs.destroy', $id));
    $response->assertRedirect(route('admin.failed-jobs.index'));
    $response->assertSessionHas('success');
});

test('admin users can flush all failed jobs', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['job' => 'test']),
        'exception' => 'Test exception',
        'failed_at' => now(),
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->post(route('admin.failed-jobs.flush'));
    $response->assertRedirect(route('admin.failed-jobs.index'));
    $response->assertSessionHas('success');
});
