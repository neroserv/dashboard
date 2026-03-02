<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests cannot access finished batches', function () {
    $response = $this->get(route('admin.finished-batches.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access finished batches', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.finished-batches.index'));
    $response->assertForbidden();
});

test('admin users can view finished batches page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.finished-batches.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/finished-batches/Index')
        ->has('finishedBatches')
        ->has('finishedBatches.data')
        ->has('finishedBatches.links')
    );
});

test('admin users see paginated finished batches', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $now = time();
    DB::table('job_batches')->insert([
        'id' => 'test-batch-'.uniqid(),
        'name' => 'Test Batch',
        'total_jobs' => 5,
        'pending_jobs' => 0,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'created_at' => $now,
        'finished_at' => $now,
    ]);

    $response = $this->get(route('admin.finished-batches.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/finished-batches/Index')
        ->has('finishedBatches.data')
    );
});
