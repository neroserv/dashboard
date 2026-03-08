<?php

use App\Models\User;

test('guests cannot access admin update page', function () {
    $response = $this->get(route('admin.update.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access admin update page', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.update.index'));
    $response->assertForbidden();
});

test('admin users can view update page with commit info', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->get(route('admin.update.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/update/Index')
        ->has('currentCommit')
        ->has('remoteCommit')
        ->has('updateAvailable')
        ->has('recentCommits')
        ->has('error')
        ->has('canRunUpdate')
        ->where('canRunUpdate', true)
    );
});

test('post run without admin permission returns 403', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->post(route('admin.update.run'));
    $response->assertForbidden();
});

test('post run as admin returns stream when no update available', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $response = $this->post(route('admin.update.run'), [], [
        'Accept' => 'text/event-stream',
    ]);
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
    $body = $response->streamedContent();
    expect($body)->toContain('"done":true');
});
