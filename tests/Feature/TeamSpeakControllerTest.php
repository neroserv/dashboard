<?php

use App\Models\Brand;
use App\Models\HostingPlan;
use App\Models\TeamSpeakServerAccount;
use App\Models\TeamSpeakSnapshot;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->brandWithTeamSpeak = Brand::create([
        'key' => 'teamspeak-test',
        'name' => 'TeamSpeak Test',
        'domains' => ['teamspeak.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => true],
    ]);

    $this->brandWithoutTeamSpeak = Brand::create([
        'key' => 'no-teamspeak',
        'name' => 'No TeamSpeak',
        'domains' => ['noteamspeak.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => false],
    ]);

    $this->user = User::factory()->create([
        'brand_id' => $this->brandWithTeamSpeak->id,
    ]);
});

test('teamspeak index returns 200 when brand has teamspeak feature', function () {
    actingAs($this->user);

    $response = $this->get('http://teamspeak.praxishosting.test/teamspeak');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('teamspeak/Index')
        ->has('hostingPlans')
    );
});

test('teamspeak index redirects to dashboard when brand has no teamspeak feature', function () {
    $userNoTs = User::factory()->create(['brand_id' => $this->brandWithoutTeamSpeak->id]);
    actingAs($userNoTs);

    $response = $this->get('http://noteamspeak.praxishosting.test/teamspeak');

    $response->assertRedirect(route('dashboard'));
});

test('teamspeak index shows plans when brand has teamspeak and plans exist', function () {
    HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => null,
        'panel_type' => 'teamspeak',
        'config' => ['plan_options' => []],
        'name' => 'TeamSpeak Small',
        'plesk_package_name' => null,
        'disk_gb' => 0,
        'traffic_gb' => 0,
        'domains' => 0,
        'subdomains' => 0,
        'mailboxes' => 0,
        'databases' => 0,
        'price' => 4.99,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    actingAs($this->user);

    $response = $this->get('http://teamspeak.praxishosting.test/teamspeak');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('teamspeak/Index')
        ->has('hostingPlans')
        ->where('hostingPlans.0.name', 'TeamSpeak Small')
    );
});

test('owner can delete teamspeak backup', function () {
    $plan = HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => null,
        'panel_type' => 'teamspeak',
        'config' => ['plan_options' => []],
        'name' => 'TS Plan',
        'plesk_package_name' => null,
        'disk_gb' => 0,
        'traffic_gb' => 0,
        'domains' => 0,
        'subdomains' => 0,
        'mailboxes' => 0,
        'databases' => 0,
        'price' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $account = TeamSpeakServerAccount::create([
        'user_id' => $this->user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'product_id' => null,
        'name' => 'My TS Server',
        'virtual_server_id' => null,
        'port' => null,
        'status' => 'active',
    ]);

    $snapshot = TeamSpeakSnapshot::create([
        'team_speak_server_account_id' => $account->id,
        'snapshot' => 'hash=test|virtualserver_name=Test',
    ]);

    actingAs($this->user);

    $path = parse_url(route('teamspeak-accounts.backups.destroy', [$account, $snapshot]), PHP_URL_PATH);
    $response = $this->delete('http://teamspeak.praxishosting.test'.$path);

    $response->assertRedirect(route('teamspeak-accounts.show', $account));
    $response->assertSessionHas('success', 'Backup wurde gelöscht.');
    expect(TeamSpeakSnapshot::find($snapshot->id))->toBeNull();
});

test('teamspeak account show canRenew is false when plan has no price and no fallback', function () {
    $plan = HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => null,
        'panel_type' => 'teamspeak',
        'config' => ['plan_options' => []],
        'name' => 'TS Plan No Price',
        'plesk_package_name' => null,
        'disk_gb' => 0,
        'traffic_gb' => 0,
        'domains' => 0,
        'subdomains' => 0,
        'mailboxes' => 0,
        'databases' => 0,
        'price' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $account = TeamSpeakServerAccount::create([
        'user_id' => $this->user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'product_id' => null,
        'name' => 'My TS',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
        'renewal_type' => null,
        'mollie_subscription_id' => null,
    ]);

    config(['billing.fallback_monthly_price_teamspeak' => 0]);
    actingAs($this->user);

    $path = parse_url(route('teamspeak-accounts.show', $account), PHP_URL_PATH);
    $response = $this->get('http://teamspeak.praxishosting.test'.$path);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('teamspeak-accounts/Show')
        ->where('canRenew', false)
        ->where('renewalAmount', 0)
    );
});

test('teamspeak account show canRenew is true when plan has no price but fallback is set', function () {
    $plan = HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => null,
        'panel_type' => 'teamspeak',
        'config' => ['plan_options' => []],
        'name' => 'TS Plan No Price',
        'plesk_package_name' => null,
        'disk_gb' => 0,
        'traffic_gb' => 0,
        'domains' => 0,
        'subdomains' => 0,
        'mailboxes' => 0,
        'databases' => 0,
        'price' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $account = TeamSpeakServerAccount::create([
        'user_id' => $this->user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'product_id' => null,
        'name' => 'My TS',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
        'renewal_type' => null,
        'mollie_subscription_id' => null,
    ]);

    config(['billing.fallback_monthly_price_teamspeak' => 5.99]);
    actingAs($this->user);

    $path = parse_url(route('teamspeak-accounts.show', $account), PHP_URL_PATH);
    $response = $this->get('http://teamspeak.praxishosting.test'.$path);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('teamspeak-accounts/Show')
        ->where('canRenew', true)
        ->where('renewalAmount', 5.99) // float from config
    );
});

test('teamspeak account show canRenew is true when plan has no base price but option slots define price', function () {
    $plan = HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => null,
        'panel_type' => 'teamspeak',
        'config' => [
            'plan_options' => [
                [
                    'id' => 'slots',
                    'type' => 'range_slider',
                    'min' => 10,
                    'step' => 1,
                    'price_per_unit' => 0.25,
                ],
            ],
        ],
        'name' => 'TS Plan Slots Only',
        'plesk_package_name' => null,
        'disk_gb' => 0,
        'traffic_gb' => 0,
        'domains' => 0,
        'subdomains' => 0,
        'mailboxes' => 0,
        'databases' => 0,
        'price' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $account = TeamSpeakServerAccount::create([
        'user_id' => $this->user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => null,
        'product_id' => null,
        'name' => 'My TS',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
        'renewal_type' => null,
        'mollie_subscription_id' => null,
        'option_values' => ['slots' => 32],
    ]);

    config(['billing.fallback_monthly_price_teamspeak' => 0]);
    actingAs($this->user);

    $path = parse_url(route('teamspeak-accounts.show', $account), PHP_URL_PATH);
    $response = $this->get('http://teamspeak.praxishosting.test'.$path);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('teamspeak-accounts/Show')
        ->where('canRenew', true)
        ->where('renewalAmount', 8) // 32 slots * 0.25 (JSON returns int)
    );
});
