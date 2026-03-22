<?php

use App\Models\Brand;
use App\Models\BrandExtension;
use App\Models\HostingPlan;
use App\Models\HostingServer;
use App\Models\User;
use App\Models\WebspaceAccount;
use App\Services\ControlPanels\KeyHelpClient;
use App\Services\ControlPanels\WebspacePanelDispatcher;
use Illuminate\Support\Facades\Http;

test('brand extension keyhelp is in all extension keys', function () {
    expect(in_array(BrandExtension::EXTENSION_KEYHELP, BrandExtension::allExtensionKeys(), true))->toBeTrue();
});

test('api v1 hosting-plans type webspace panel keyhelp returns only keyhelp plans', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-api',
        'name' => 'KH API',
        'is_default' => true,
    ]);
    HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => null,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'KeyHelp S',
        'plesk_package_name' => '1',
        'disk_gb' => 10,
        'traffic_gb' => 100,
        'domains' => 1,
        'subdomains' => 5,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 5.99,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => null,
        'panel_type' => 'plesk',
        'config' => [],
        'name' => 'Plesk S',
        'plesk_package_name' => 'Unlimited',
        'disk_gb' => 10,
        'traffic_gb' => 100,
        'domains' => 1,
        'subdomains' => 5,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 4.99,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/hosting-plans?type=webspace&panel=keyhelp');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.panel_type', 'keyhelp');
    $response->assertJsonPath('data.0.name', 'KeyHelp S');
});

test('admin keyhelp hosting server check succeeds when api ping returns 200', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-check',
        'name' => 'KH Check',
        'is_default' => true,
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'test-api-key',
        'is_active' => true,
    ]);
    $admin = User::factory()->create(['is_admin' => true, 'brand_id' => $brand->id]);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v2/ping')) {
            return Http::response(['pong' => true], 200);
        }

        return Http::response('not found', 404);
    });

    $this->actingAs($admin);

    $response = $this->getJson(route('admin.hosting-servers.check', $server));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('panel_type', 'keyhelp');
    $server->refresh();
    expect($server->api_check_status)->toBe('ok');
});

test('hosting server resolve active for webspace plan matches panel type and brand', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'resolve',
        'name' => 'Resolve',
        'is_default' => true,
    ]);
    $otherBrand = Brand::create([
        'key' => 'other',
        'name' => 'Other',
        'is_default' => false,
    ]);
    HostingServer::create([
        'brand_id' => $otherBrand->id,
        'panel_type' => 'keyhelp',
        'name' => 'Wrong brand',
        'hostname' => 'a.test',
        'config' => [],
        'api_token' => 'k',
        'is_active' => true,
    ]);
    $match = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'Match',
        'hostname' => 'b.test',
        'config' => [],
        'api_token' => 'k',
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => null,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'KH Plan',
        'plesk_package_name' => '1',
        'disk_gb' => 5,
        'traffic_gb' => 50,
        'domains' => 1,
        'subdomains' => 0,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 3.99,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $resolved = HostingServer::resolveActiveForWebspacePlan($plan);

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($match->id);
});

test('admin can store keyhelp hosting server with api token', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-store',
        'name' => 'KH Store',
        'is_default' => true,
    ]);
    $admin = User::factory()->create([
        'is_admin' => true,
        'brand_id' => $brand->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin);

    $response = $this->post(route('admin.hosting-servers.store'), [
        'brand_id' => (string) $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KeyHelp Panel',
        'hostname' => 'keyhelp.example.test',
        'api_token' => 'x-api-key-value',
        'use_ssl' => '1',
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('admin.hosting-servers.index'));
    $this->assertDatabaseHas('hosting_servers', [
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'hostname' => 'keyhelp.example.test',
    ]);
    expect(HostingServer::query()->where('hostname', 'keyhelp.example.test')->value('api_token'))->toBe('x-api-key-value');
});

test('webspace account show URL uses uuid so post-checkout redirect resolves', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-route',
        'name' => 'KH Route',
        'is_default' => true,
    ]);
    $user = User::factory()->create();
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'S',
        'hostname' => 'h.test',
        'config' => [],
        'api_token' => 'k',
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'Plan',
        'plesk_package_name' => '1',
        'disk_gb' => 1,
        'traffic_gb' => 1,
        'domains' => 1,
        'subdomains' => 0,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = WebspaceAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'domain' => 'example-route.test',
        'plesk_username' => 'u1',
        'status' => 'active',
    ]);

    expect($account->uuid)->not->toBeEmpty();
    expect(route('webspace-accounts.show', $account))->toContain($account->uuid);
    expect(route('webspace-accounts.show', $account))->not->toEndWith('/'.$account->id);
});

test('keyhelp webspace usage skips plesk when keyhelp_user_id is missing', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-disp',
        'name' => 'KH Disp',
        'is_default' => true,
    ]);
    $user = User::factory()->create();
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh.example.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'x',
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'Plan',
        'plesk_package_name' => '5',
        'disk_gb' => 1,
        'traffic_gb' => 1,
        'domains' => 1,
        'subdomains' => 0,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = WebspaceAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'domain' => 'pending-keyhelp.test',
        'plesk_username' => 'u1',
        'status' => 'pending',
    ]);
    $account->load('hostingPlan', 'hostingServer');

    $usage = app(WebspacePanelDispatcher::class)->getWebspaceResourceUsage($account);

    expect($usage)->toBeNull();
});

test('admin webspace account show includes hosting plan panel_type keyhelp', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-admin-show',
        'name' => 'KH Admin Show',
        'is_default' => true,
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $customer = User::factory()->create();
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh-admin.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'x',
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'KH Plan',
        'plesk_package_name' => '1',
        'disk_gb' => 1,
        'traffic_gb' => 1,
        'domains' => 1,
        'subdomains' => 0,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $account = WebspaceAccount::create([
        'user_id' => $customer->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'domain' => 'admin-show.test',
        'plesk_username' => 'ws0001abc',
        'status' => 'pending',
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.webspace-accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/webspace-accounts/Show')
            ->where('webspaceAccount.hosting_plan.panel_type', 'keyhelp'));
});

test('keyhelp createAccount posts id_hosting_plan to api v2 clients', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');
        if (str_ends_with($path, '/api/v2/clients') && $request->method() === 'POST') {
            /** @var array<string, mixed> $data */
            $data = json_decode($request->body(), true) ?? [];
            expect($data['id_hosting_plan'] ?? null)->toBe(11);
            expect($data['username'] ?? null)->toBe('u1kh');
            expect($data['create_system_domain'] ?? null)->toBeFalse();
            expect($data['send_login_credentials'] ?? null)->toBeFalse();
            expect($data['contact_data']['country'] ?? null)->toBe('DE');

            return Http::response(['id' => 888], 201);
        }
        if (str_contains($path, '/api/v2/domains') && $request->method() === 'POST') {
            return Http::response(['id' => 1], 201);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-create-client',
        'name' => 'KH Create',
        'is_default' => true,
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'keyhelp-api.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);

    $returnedId = app(KeyHelpClient::class)->setServer($server)->createAccount(
        'u1kh',
        'provisioned.test',
        '11',
        'LongPassword123!xyz',
        'buyer@example.com',
    );

    expect($returnedId)->toBe(888);
});

test('admin keyhelp hosting plans json endpoint returns normalized plans', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v2/hosting-plans')) {
            return Http::response([
                [
                    'id' => 8,
                    'name' => 'Hosting Plus',
                    'resources' => [
                        'disk_space' => 1_073_741_824,
                        'traffic' => -1,
                        'domains' => 10,
                        'subdomains' => 50,
                        'email_accounts' => 20,
                        'databases' => 10,
                    ],
                ],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-hp-list',
        'name' => 'KH HP List',
        'is_default' => true,
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh-plans.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $this->getJson(route('admin.hosting-plans.keyhelp-hosting-plans', ['hosting_server_id' => $server->id]))
        ->assertOk()
        ->assertJsonPath('plans.0.id', 8)
        ->assertJsonPath('plans.0.name', 'Hosting Plus')
        ->assertJsonPath('plans.0.disk_gb', 1)
        ->assertJsonPath('plans.0.disk_unlimited', false)
        ->assertJsonPath('plans.0.traffic_gb', null)
        ->assertJsonPath('plans.0.traffic_unlimited', true)
        ->assertJsonPath('plans.0.domains', 10)
        ->assertJsonPath('plans.0.domains_unlimited', false)
        ->assertJsonPath('plans.0.mailboxes', 20)
        ->assertJsonPath('plans.0.mailboxes_unlimited', false);
});

test('admin keyhelp hosting plans json treats negative KeyHelp quotas as unlimited', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v2/hosting-plans')) {
            return Http::response([
                [
                    'id' => 99,
                    'name' => 'All you can eat',
                    'resources' => [
                        'disk_space' => -1,
                        'traffic' => -1,
                        'domains' => -1,
                        'subdomains' => -1,
                        'email_accounts' => -1,
                        'databases' => -1,
                    ],
                ],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-unl',
        'name' => 'KH Unlimited',
        'is_default' => true,
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh-unl.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $this->getJson(route('admin.hosting-plans.keyhelp-hosting-plans', ['hosting_server_id' => $server->id]))
        ->assertOk()
        ->assertJsonPath('plans.0.disk_unlimited', true)
        ->assertJsonPath('plans.0.disk_gb', null)
        ->assertJsonPath('plans.0.traffic_unlimited', true)
        ->assertJsonPath('plans.0.traffic_gb', null)
        ->assertJsonPath('plans.0.domains_unlimited', true)
        ->assertJsonPath('plans.0.domains', null)
        ->assertJsonPath('plans.0.subdomains_unlimited', true)
        ->assertJsonPath('plans.0.mailboxes_unlimited', true)
        ->assertJsonPath('plans.0.databases_unlimited', true);
});

test('admin keyhelp hosting plans json allows server with null brand_id when default brand is in context', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), '/api/v2/hosting-plans')) {
            return Http::response([
                [
                    'id' => 1,
                    'name' => 'Shared',
                    'resources' => [
                        'disk_space' => 1_073_741_824,
                        'traffic' => 0,
                        'domains' => 1,
                        'subdomains' => 1,
                        'email_accounts' => 1,
                        'databases' => 1,
                    ],
                ],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    Brand::create([
        'key' => 'kh-null-brand',
        'name' => 'KH Null Brand',
        'is_default' => true,
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $server = HostingServer::create([
        'brand_id' => null,
        'panel_type' => 'keyhelp',
        'name' => 'KH shared',
        'hostname' => 'kh-shared.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $this->getJson(route('admin.hosting-plans.keyhelp-hosting-plans', ['hosting_server_id' => $server->id]))
        ->assertOk()
        ->assertJsonPath('plans.0.id', 1)
        ->assertJsonPath('plans.0.name', 'Shared');
});

test('keyhelp getWebspaceResourceUsage uses clients stats endpoint', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');
        if (str_ends_with($path, '/api/v2/clients/42/stats')) {
            return Http::response([
                'disk_space' => ['value' => 1_000_000, 'max' => 2_147_483_648],
                'domains' => ['value' => 2, 'max' => 10],
                'subdomains' => ['value' => 3, 'max' => 20],
                'email_accounts' => ['value' => 4, 'max' => 20],
                'databases' => ['value' => 5, 'max' => 10],
                'traffic' => ['value' => 100, 'max' => -1],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-stats',
        'name' => 'KH Stats',
        'is_default' => true,
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh-stats.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);

    $usage = app(KeyHelpClient::class)->setServer($server)->getWebspaceResourceUsage(42, 'example.test');

    expect($usage)->not->toBeNull()
        ->and($usage['disk_bytes'])->toBe(1_000_000)
        ->and($usage['domains_used'])->toBe(2)
        ->and($usage['subdomains_used'])->toBe(3)
        ->and($usage['mailboxes_used'])->toBe(4)
        ->and($usage['databases_used'])->toBe(5);
});

test('keyhelp getClientResourcesSanitized strips password and certificate keys', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');
        if (str_ends_with($path, '/api/v2/clients/7/resources')) {
            return Http::response([
                'emails' => [
                    [
                        'id' => 1,
                        'email' => 'a@b.de',
                        'email_utf8' => 'a@b.de',
                        'password_hash' => 'secret',
                        'size' => 100,
                        'max_size' => 200,
                    ],
                ],
                'databases' => [
                    [
                        'id' => 2,
                        'database_name' => 'db1',
                        'database_username' => 'db1',
                        'password_hash' => 'mysqlhash',
                        'size' => 50,
                    ],
                ],
                'certificates' => [
                    [
                        'id' => 3,
                        'name' => 'LE',
                        'issuer' => 'R3',
                        'components' => ['private_key' => 'BEGIN'],
                        'secured_domains' => ['x.test'],
                    ],
                ],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-res',
        'name' => 'KH Res',
        'is_default' => true,
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh-res.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);

    $out = app(KeyHelpClient::class)->setServer($server)->getClientResourcesSanitized(7);

    expect($out)->toBeArray()
        ->and($out['emails'][0] ?? [])->not->toHaveKey('password_hash')
        ->and($out['databases'][0] ?? [])->not->toHaveKey('password_hash')
        ->and($out['certificates'][0] ?? [])->not->toHaveKey('components')
        ->and($out['emails'][0]['email'] ?? null)->toBe('a@b.de');
});

test('keyhelp resourceUsagePayloadForKeyhelp adds extras from stats', function () {
    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-payload',
        'name' => 'KH Payload',
        'is_default' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => null,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'Plan',
        'plesk_package_name' => '1',
        'disk_gb' => 2,
        'traffic_gb' => 1,
        'domains' => 1,
        'subdomains' => 0,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $stats = [
        'disk_space' => ['value' => 500, 'max' => 1_000],
        'domains' => ['value' => 1, 'max' => 5],
        'subdomains' => ['value' => 0, 'max' => 10],
        'email_accounts' => ['value' => 1, 'max' => 5],
        'databases' => ['value' => 0, 'max' => 3],
        'ftp_users' => ['value' => 1, 'max' => 5],
        'files' => ['value' => 10, 'max' => 100],
    ];
    $usage = [
        'disk_bytes' => 500,
        'domains_used' => 1,
        'subdomains_used' => 0,
        'mailboxes_used' => 1,
        'databases_used' => 0,
    ];

    $payload = KeyHelpClient::resourceUsagePayloadForKeyhelp($plan, $usage, $stats);

    expect($payload['disk_limit_bytes'])->toBe(1_000)
        ->and($payload['domains_limit'])->toBe(5)
        ->and($payload['keyhelp_extras']['ftp_users']['value'])->toBe(1)
        ->and($payload['keyhelp_extras']['files']['max'])->toBe(100);
});

test('dispatcher getWebspaceResourceUsageForShow returns keyhelp stats for keyhelp account', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $path = (string) (parse_url($request->url(), PHP_URL_PATH) ?? '');
        if (str_ends_with($path, '/api/v2/clients/99/stats')) {
            return Http::response([
                'disk_space' => ['value' => 10, 'max' => 100],
                'domains' => ['value' => 1, 'max' => 1],
                'subdomains' => ['value' => 0, 'max' => 0],
                'email_accounts' => ['value' => 0, 'max' => 1],
                'databases' => ['value' => 0, 'max' => 1],
            ], 200);
        }

        return Http::response('not found', 404);
    });

    Brand::query()->update(['is_default' => false]);
    $brand = Brand::create([
        'key' => 'kh-wrap',
        'name' => 'KH Wrap',
        'is_default' => true,
    ]);
    $server = HostingServer::create([
        'brand_id' => $brand->id,
        'panel_type' => 'keyhelp',
        'name' => 'KH',
        'hostname' => 'kh-wrap.test',
        'port' => null,
        'use_ssl' => true,
        'config' => [],
        'api_token' => 'token',
        'is_active' => true,
    ]);
    $plan = HostingPlan::create([
        'brand_id' => $brand->id,
        'hosting_server_id' => $server->id,
        'panel_type' => 'keyhelp',
        'config' => [],
        'name' => 'Plan',
        'plesk_package_name' => '1',
        'disk_gb' => 1,
        'traffic_gb' => 1,
        'domains' => 1,
        'subdomains' => 0,
        'mailboxes' => 1,
        'databases' => 1,
        'price' => 1,
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $user = User::factory()->create();
    $account = WebspaceAccount::create([
        'user_id' => $user->id,
        'hosting_plan_id' => $plan->id,
        'hosting_server_id' => $server->id,
        'domain' => 'wrap.test',
        'plesk_username' => 'u1',
        'keyhelp_user_id' => 99,
        'status' => 'active',
    ]);
    $account->load(['hostingPlan', 'hostingServer']);

    $wrapped = app(WebspacePanelDispatcher::class)->getWebspaceResourceUsageForShow($account);

    expect($wrapped)->not->toBeNull()
        ->and($wrapped['keyhelp_stats'])->toBeArray()
        ->and($wrapped['usage']['disk_bytes'])->toBe(10);
});
