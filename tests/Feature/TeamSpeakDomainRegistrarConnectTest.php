<?php

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\HostingPlan;
use App\Models\HostingServer;
use App\Models\ResellerDomain;
use App\Models\TeamSpeakServerAccount;
use App\Models\User;
use App\Services\RealtimeRegisterApiService;
use App\Services\SkrimeApiService;
use App\Support\DomainRegistrar;

use function Pest\Laravel\actingAs;

test('teamspeak connect-domain works with skrime registrar when extension is installed', function () {
    $brand = Brand::create([
        'key' => 'ts-skrime-connect',
        'name' => 'TS Skrime Connect',
        'domains' => ['ts-skrime-connect.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => true],
    ]);
    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_SKRIME,
        'installed_at' => now(),
        'settings' => [],
    ]);
    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'email_verified_at' => now(),
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'ip_address' => '10.0.0.1',
        'config' => [],
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
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
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = TeamSpeakServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'product_id' => null,
        'name' => 'TS Server',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
    ]);
    $resellerDomain = ResellerDomain::factory()->forUser($user)->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::SKRIME,
        'domain' => 'customer-skrime.test',
    ]);

    $this->mock(SkrimeApiService::class, function ($mock): void {
        $mock->shouldReceive('forBrand')->once()->andReturnSelf();
        $mock->shouldReceive('getDns')->once()->with('customer-skrime.test')->andReturn([]);
        $mock->shouldReceive('setDns')->once()->withArgs(function (string $domain, array $records): bool {
            return $domain === 'customer-skrime.test'
                && collect($records)->contains(fn (array $record): bool => $record['type'] === 'SRV' && $record['name'] === '_ts3._udp.voice');
        })->andReturn([]);
    });
    $this->mock(RealtimeRegisterApiService::class, function ($mock): void {
        $mock->shouldReceive('forBrand')->once()->andReturnSelf();
    });

    actingAs($user);

    $response = $this->post('http://ts-skrime-connect.praxishosting.test'.parse_url(route('teamspeak-accounts.connect-domain.store', $account), PHP_URL_PATH), [
        'reseller_domain_uuid' => $resellerDomain->uuid,
        'subdomain' => 'voice',
    ]);

    $response->assertRedirect(route('teamspeak-accounts.show', $account));
    $response->assertSessionHas('success');
});

test('teamspeak connect-domain works with realtime register registrar when extension is installed', function () {
    $brand = Brand::create([
        'key' => 'ts-rr-connect',
        'name' => 'TS RR Connect',
        'domains' => ['ts-rr-connect.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => true],
    ]);
    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_REALTIMEREGISTER,
        'installed_at' => now(),
        'settings' => [],
    ]);
    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'email_verified_at' => now(),
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'ip_address' => '10.0.0.1',
        'config' => [],
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
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
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = TeamSpeakServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'product_id' => null,
        'name' => 'TS Server',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
    ]);
    $resellerDomain = ResellerDomain::factory()->forUser($user)->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::REALTIME_REGISTER,
        'domain' => 'customer-rr.test',
        'realtimeregister_domain_name' => 'customer-rr.test',
    ]);

    $this->mock(SkrimeApiService::class, function ($mock): void {
        $mock->shouldReceive('forBrand')->once()->andReturnSelf();
    });
    $this->mock(RealtimeRegisterApiService::class, function ($mock): void {
        $mock->shouldReceive('forBrand')->once()->andReturnSelf();
        $mock->shouldReceive('getDns')->once()->with('customer-rr.test')->andReturn([]);
        $mock->shouldReceive('setDns')->once()->withArgs(function (string $domain, array $records): bool {
            return $domain === 'customer-rr.test'
                && collect($records)->contains(fn (array $record): bool => $record['type'] === 'SRV' && $record['name'] === '_ts3._udp.voice');
        })->andReturn([]);
    });

    actingAs($user);

    $response = $this->post('http://ts-rr-connect.praxishosting.test'.parse_url(route('teamspeak-accounts.connect-domain.store', $account), PHP_URL_PATH), [
        'reseller_domain_uuid' => $resellerDomain->uuid,
        'subdomain' => 'voice',
    ]);

    $response->assertRedirect(route('teamspeak-accounts.show', $account));
    $response->assertSessionHas('success');
});

test('teamspeak connect-domain blocks duplicate srv record for realtime register domains', function () {
    $brand = Brand::create([
        'key' => 'ts-rr-duplicate',
        'name' => 'TS RR Duplicate',
        'domains' => ['ts-rr-duplicate.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => true],
    ]);
    BrandExtension::query()->create([
        'brand_id' => $brand->id,
        'extension' => BrandExtension::EXTENSION_REALTIMEREGISTER,
        'installed_at' => now(),
        'settings' => [],
    ]);
    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'email_verified_at' => now(),
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'config' => [],
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
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
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = TeamSpeakServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'product_id' => null,
        'name' => 'TS Server',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
    ]);
    $resellerDomain = ResellerDomain::factory()->forUser($user)->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::REALTIME_REGISTER,
        'domain' => 'customer-rr-duplicate.test',
        'realtimeregister_domain_name' => 'customer-rr-duplicate.test',
    ]);

    $this->mock(SkrimeApiService::class, function ($mock): void {
        $mock->shouldReceive('forBrand')->once()->andReturnSelf();
    });
    $this->mock(RealtimeRegisterApiService::class, function ($mock): void {
        $mock->shouldReceive('forBrand')->once()->andReturnSelf();
        $mock->shouldReceive('getDns')->once()->with('customer-rr-duplicate.test')->andReturn([
            ['name' => '_ts3._udp.voice', 'type' => 'SRV', 'data' => '0 5 9987 voice.example.test.'],
        ]);
        $mock->shouldNotReceive('setDns');
    });

    actingAs($user);

    $response = $this->post('http://ts-rr-duplicate.praxishosting.test'.parse_url(route('teamspeak-accounts.connect-domain.store', $account), PHP_URL_PATH), [
        'reseller_domain_uuid' => $resellerDomain->uuid,
        'subdomain' => 'voice',
    ]);

    $response->assertRedirect(route('teamspeak-accounts.connect-domain.show', $account));
    $response->assertSessionHas('error');
});

test('teamspeak connect-domain returns error when registrar provider extension is not installed', function () {
    $brand = Brand::create([
        'key' => 'ts-rr-no-extension',
        'name' => 'TS RR No Extension',
        'domains' => ['ts-rr-no-extension.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => true],
    ]);
    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'email_verified_at' => now(),
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'config' => [],
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
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
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = TeamSpeakServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'product_id' => null,
        'name' => 'TS Server',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
    ]);
    $resellerDomain = ResellerDomain::factory()->forUser($user)->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::REALTIME_REGISTER,
        'domain' => 'customer-rr-no-extension.test',
        'realtimeregister_domain_name' => 'customer-rr-no-extension.test',
    ]);

    actingAs($user);

    $response = $this->post('http://ts-rr-no-extension.praxishosting.test'.parse_url(route('teamspeak-accounts.connect-domain.store', $account), PHP_URL_PATH), [
        'reseller_domain_uuid' => $resellerDomain->uuid,
        'subdomain' => 'voice',
    ]);

    $response->assertRedirect(route('teamspeak-accounts.connect-domain.show', $account));
    $response->assertSessionHas('error', fn (string $value) => str_contains($value, 'nicht aktiviert'));
});

test('teamspeak connect-domain returns error for skrime when skrime extension is not installed', function () {
    $brand = Brand::create([
        'key' => 'ts-skrime-no-extension',
        'name' => 'TS Skrime No Extension',
        'domains' => ['ts-skrime-no-extension.praxishosting.test'],
        'is_default' => false,
        'features' => ['teamspeak' => true],
    ]);
    $user = User::factory()->create([
        'brand_id' => $brand->id,
        'email_verified_at' => now(),
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'teamspeak',
        'name' => 'TS Node',
        'hostname' => 'voice.example.test',
        'config' => [],
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
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
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = TeamSpeakServerAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'product_id' => null,
        'name' => 'TS Server',
        'virtual_server_id' => 1,
        'port' => 9987,
        'status' => 'active',
    ]);
    $resellerDomain = ResellerDomain::factory()->forUser($user)->create([
        'brand_id' => $brand->id,
        'registrar' => DomainRegistrar::SKRIME,
        'domain' => 'customer-skrime-no-extension.test',
    ]);

    actingAs($user);

    $response = $this->post('http://ts-skrime-no-extension.praxishosting.test'.parse_url(route('teamspeak-accounts.connect-domain.store', $account), PHP_URL_PATH), [
        'reseller_domain_uuid' => $resellerDomain->uuid,
        'subdomain' => 'voice',
    ]);

    $response->assertRedirect(route('teamspeak-accounts.connect-domain.show', $account));
    $response->assertSessionHas('error', fn (string $value) => str_contains($value, 'nicht aktiviert'));
});
