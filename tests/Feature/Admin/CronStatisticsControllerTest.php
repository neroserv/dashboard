<?php

use App\Models\CronDailyStats;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('guests cannot access cron statistics', function () {
    $response = $this->get(route('admin.cron-statistics.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access cron statistics', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    $response = $this->get(route('admin.cron-statistics.index'));
    $response->assertForbidden();
});

test('admin users can view cron statistics page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.cron-statistics.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/cron-statistics/Index')
        ->has('lastSchedulerRunAt')
        ->has('lastCronRunAt')
        ->has('nextCronRunDescription')
        ->has('nextDailyRunAt')
        ->has('dailyStats')
        ->has('filterDays')
        ->where('nextCronRunDescription', 'Jede Minute')
    );
});

test('admin users see daily stats when cron_daily_stats has data', function () {
    CronDailyStats::create([
        'date' => now()->toDateString(),
        'invoices_created' => 3,
        'services_suspended' => 1,
        'services_terminated' => 0,
        'tickets_closed' => 0,
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.cron-statistics.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/cron-statistics/Index')
        ->has('dailyStats')
        ->where('dailyStats.0.invoices_created', 3)
        ->where('dailyStats.0.services_suspended', 1)
    );
});

test('admin users can filter cron statistics by days', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.cron-statistics.index', ['days' => 14]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filterDays', 14)
        ->has('dailyStats')
        ->where('dailyStats', fn ($stats) => count($stats) === 14)
    );
});

test('last scheduler run is shown when cache has value', function () {
    Cache::put('scheduler_last_run_at', now()->subMinutes(5)->toIso8601String(), now()->addDay());
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $response = $this->get(route('admin.cron-statistics.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('lastSchedulerRunAt', fn ($v) => $v !== null && is_string($v))
    );
});
