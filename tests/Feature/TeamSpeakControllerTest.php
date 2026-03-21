<?php

use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\HostingPlan;
use App\Models\HostingServer;
use App\Models\TeamSpeakServerAccount;
use App\Models\TeamSpeakSnapshot;
use App\Models\User;
use App\Services\ControlPanels\TeamSpeakClient;

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

test('teamspeak account show includes connection_preview when API unavailable but host and port exist', function () {
    $hostingServer = HostingServer::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'ip_address' => '10.99.1.1',
        'config' => [],
        'is_active' => true,
    ]);

    $plan = HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => $hostingServer->id,
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
        'hosting_server_id' => $hostingServer->id,
        'product_id' => null,
        'name' => 'TS No API',
        'virtual_server_id' => null,
        'port' => 9987,
        'status' => 'active',
    ]);

    actingAs($this->user);

    $path = parse_url(route('teamspeak-accounts.show', $account), PHP_URL_PATH);
    $response = $this->get('http://teamspeak.praxishosting.test'.$path);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('teamspeak-accounts/Show')
        ->where('serverInfo', null)
        ->where('connection_preview.address', 'voice.example.test:9987')
        ->where('connection_preview.connection_uri', 'ts3server://voice.example.test?port=9987')
    );
});

test('teamspeak balance checkout redirects to account show using uuid route key', function () {
    $this->brandWithTeamSpeak->update([
        'features' => ['teamspeak' => true, 'prepaid_balance' => true],
    ]);

    $user = User::factory()->withBillingProfile()->create([
        'brand_id' => $this->brandWithTeamSpeak->id,
    ]);

    CustomerBalance::create([
        'user_id' => $user->id,
        'balance' => 100,
    ]);

    $hostingServer = HostingServer::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'ip_address' => '10.99.1.1',
        'config' => ['port_range_min' => 10072, 'port_range_max' => 10221],
        'is_active' => true,
    ]);

    $plan = HostingPlan::create([
        'brand_id' => $this->brandWithTeamSpeak->id,
        'hosting_server_id' => $hostingServer->id,
        'panel_type' => 'teamspeak',
        'config' => ['plan_options' => []],
        'name' => 'TS Plan Balance',
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

    $this->mock(TeamSpeakClient::class, function ($mock) {
        $mock->shouldReceive('setServer')->once();
        $mock->shouldReceive('getNextAvailablePort')->once()->andReturn(10080);
        $mock->shouldReceive('createVirtualServer')->once()->andReturn([
            'virtual_server_id' => 99,
            'port' => 10080,
        ]);
    });

    actingAs($user);

    $response = $this->post('http://teamspeak.praxishosting.test/teamspeak/checkout', [
        'hosting_plan_id' => $plan->id,
        'server_name' => 'Balance TS',
        'payment_method' => 'balance',
        'period_months' => 1,
        'accept_tos' => true,
        'accept_early_execution' => true,
    ]);

    $response->assertRedirect();

    $account = TeamSpeakServerAccount::query()->where('user_id', $user->id)->latest('id')->first();
    expect($account)->not->toBeNull();

    $location = (string) $response->headers->get('Location');
    expect($location)->toContain('/teamspeak-accounts/'.$account->uuid);
    expect($location)->not->toBe('/teamspeak-accounts/'.$account->id);
});
