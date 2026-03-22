<?php

namespace App\Services\ControlPanels;

use App\Models\HostingPlan;
use App\Models\HostingServer;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KeyHelp REST API (v2). Auth: X-API-Key. See https://app.swaggerhub.com/apis-docs/keyhelp/api/
 */
class KeyHelpClient
{
    protected ?HostingServer $server = null;

    public function setServer(HostingServer $server): self
    {
        $this->server = $server;

        return $this;
    }

    /**
     * Create KeyHelp user and primary domain. Returns KeyHelp user id or null on failure.
     */
    public function createAccount(string $username, string $domain, string $packageId, string $password, ?string $email = null): ?int
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        $email = $email ?? 'webspace@placeholder.local';
        $idHostingPlan = (int) $packageId;
        if ($idHostingPlan < 1) {
            throw new Exception(
                'KeyHelp: Ungültige Hosting-Plan-ID (id_hosting_plan). Im Webspace-Paket (Admin) muss bei KeyHelp im Feld „KeyHelp-Paket-ID“ eine positive ganze Zahl stehen (ID des Hosting-Plans in KeyHelp, nicht der Anzeigename).',
            );
        }

        $userId = $this->createUser($username, $password, $email, $idHostingPlan);
        if ($userId === null) {
            return null;
        }

        if (! $this->createDomain($userId, $domain)) {
            try {
                $this->deleteUser($userId);
            } catch (\Throwable) {
            }
            Log::error('KeyHelp: domain creation failed after user create', ['user_id' => $userId, 'domain' => $domain]);

            return null;
        }

        Log::info('KeyHelp account created', ['server' => $this->server->hostname, 'domain' => $domain, 'user_id' => $userId]);

        return $userId;
    }

    public function suspendUser(int $userId): bool
    {
        return $this->patchUserDisabled($userId, true);
    }

    public function unsuspendUser(int $userId): bool
    {
        return $this->patchUserDisabled($userId, false);
    }

    public function deleteUser(int $userId): bool
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        foreach (["/clients/{$userId}", "/users/{$userId}"] as $path) {
            try {
                $response = $this->http()->delete($this->apiUrl($path));
                if ($response->successful() || $response->status() === 204) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::debug('KeyHelp deleteUser attempt failed', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        Log::error('KeyHelp deleteUser: all attempts failed', ['user_id' => $userId]);

        return false;
    }

    /**
     * Terminate: delete user (domains should be removed with user per KeyHelp behaviour).
     */
    public function terminateUser(int $userId): bool
    {
        return $this->deleteUser($userId);
    }

    /**
     * @return array{success: bool, message: string, info?: array<string, mixed>|null}
     */
    public function testConnection(): array
    {
        if (! $this->server) {
            return ['success' => false, 'message' => 'Server not configured'];
        }

        try {
            $response = $this->http()->get($this->apiUrl('/ping'));
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'info' => ['ping' => $response->json()],
                ];
            }

            return [
                'success' => false,
                'message' => 'KeyHelp: HTTP '.$response->status().' — '.$response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /api/v2/hosting-plans — list hosting plans (sort: id|name, order: asc|desc).
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     disk_gb: int|null,
     *     disk_unlimited: bool,
     *     traffic_gb: int|null,
     *     traffic_unlimited: bool,
     *     domains: int|null,
     *     domains_unlimited: bool,
     *     subdomains: int|null,
     *     subdomains_unlimited: bool,
     *     mailboxes: int|null,
     *     mailboxes_unlimited: bool,
     *     databases: int|null,
     *     databases_unlimited: bool
     * }>
     *
     * @throws Exception
     */
    public function listHostingPlans(string $sort = 'id', string $order = 'asc'): array
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        $sort = in_array($sort, ['id', 'name'], true) ? $sort : 'id';
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';

        $response = $this->http()->get($this->apiUrl('/hosting-plans'), [
            'sort' => $sort,
            'order' => $order,
        ]);

        if (! $response->successful()) {
            throw new Exception(
                'KeyHelp: Hosting-Pläne konnten nicht geladen werden (HTTP '.$response->status().').',
            );
        }

        $json = $response->json();
        $rows = is_array($json) ? ($json['data'] ?? $json['items'] ?? $json) : [];
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            $resources = is_array($row['resources'] ?? null) ? $row['resources'] : [];
            $diskBytes = (int) ($resources['disk_space'] ?? 0);
            $diskUnlimited = $diskBytes < 0;
            $diskGb = $diskUnlimited ? null : ($diskBytes > 0 ? max(1, (int) round($diskBytes / (1024 ** 3))) : 0);
            $trafficRaw = isset($resources['traffic']) ? (int) $resources['traffic'] : 0;
            $trafficUnlimited = $trafficRaw < 0;
            $trafficGb = $trafficUnlimited ? null : max(0, (int) round($trafficRaw / (1024 ** 3)));

            $domains = self::keyhelpResourceCount($resources['domains'] ?? 0);
            $subdomains = self::keyhelpResourceCount($resources['subdomains'] ?? 0);
            $mailboxes = self::keyhelpResourceCount($resources['email_accounts'] ?? $resources['mailboxes'] ?? 0);
            $databases = self::keyhelpResourceCount($resources['databases'] ?? 0);

            $out[] = [
                'id' => $id,
                'name' => $name,
                'disk_gb' => $diskGb,
                'disk_unlimited' => $diskUnlimited,
                'traffic_gb' => $trafficGb,
                'traffic_unlimited' => $trafficUnlimited,
                'domains' => $domains['value'],
                'domains_unlimited' => $domains['unlimited'],
                'subdomains' => $subdomains['value'],
                'subdomains_unlimited' => $subdomains['unlimited'],
                'mailboxes' => $mailboxes['value'],
                'mailboxes_unlimited' => $mailboxes['unlimited'],
                'databases' => $databases['value'],
                'databases_unlimited' => $databases['unlimited'],
            ];
        }

        return $out;
    }

    /**
     * GET /clients/{id}/stats — client-wide usage and limits.
     *
     * @return array<string, mixed>|null
     */
    public function getClientStats(int $userId): ?array
    {
        if (! $this->server) {
            return null;
        }

        try {
            $response = $this->http()->get($this->apiUrl("/clients/{$userId}/stats"));
            if (! $response->successful()) {
                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('KeyHelp getClientStats failed', [
                'server' => $this->server->hostname,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * GET /clients/{id}/resources — lists domains, mail, DBs, etc. Sensitive fields stripped.
     *
     * @return array<string, mixed>|null
     */
    public function getClientResourcesSanitized(int $userId): ?array
    {
        if (! $this->server) {
            return null;
        }

        try {
            $response = $this->http()->get($this->apiUrl("/clients/{$userId}/resources"));
            if (! $response->successful()) {
                return null;
            }
            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            return $this->sanitizeClientResourcesPayload($json);
        } catch (\Throwable $e) {
            Log::warning('KeyHelp getClientResourcesSanitized failed', [
                'server' => $this->server->hostname,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{usage: array{disk_bytes: int, domains_used: int, subdomains_used: int, mailboxes_used: int, databases_used: int}, stats: array<string, mixed>|null}|null
     */
    public function fetchClientUsageContext(int $userId, string $domain): ?array
    {
        $stats = $this->getClientStats($userId);
        if ($stats !== null) {
            return [
                'usage' => [
                    'disk_bytes' => (int) (is_array($stats['disk_space'] ?? null) ? ($stats['disk_space']['value'] ?? 0) : 0),
                    'domains_used' => (int) (is_array($stats['domains'] ?? null) ? ($stats['domains']['value'] ?? 0) : 0),
                    'subdomains_used' => (int) (is_array($stats['subdomains'] ?? null) ? ($stats['subdomains']['value'] ?? 0) : 0),
                    'mailboxes_used' => (int) (is_array($stats['email_accounts'] ?? null) ? ($stats['email_accounts']['value'] ?? 0) : 0),
                    'databases_used' => (int) (is_array($stats['databases'] ?? null) ? ($stats['databases']['value'] ?? 0) : 0),
                ],
                'stats' => $stats,
            ];
        }

        $legacy = $this->getWebspaceResourceUsageFromDomains($userId, $domain);
        if ($legacy === null) {
            return null;
        }

        return [
            'usage' => $legacy,
            'stats' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array{disk_bytes: int, domains_used: int, subdomains_used: int, mailboxes_used: int, databases_used: int}  $usage
     * @return array<string, mixed>
     */
    public static function resourceUsagePayloadForKeyhelp(HostingPlan $plan, array $usage, array $stats): array
    {
        $diskSlice = is_array($stats['disk_space'] ?? null) ? $stats['disk_space'] : [];
        $diskMaxRaw = (int) ($diskSlice['max'] ?? 0);
        $diskLimitBytes = $diskMaxRaw > 0
            ? $diskMaxRaw
            : ($plan->disk_gb ? (int) ($plan->disk_gb * 1024 * 1024 * 1024) : 0);

        $domainsLimit = self::keyhelpStatLimitInt(is_array($stats['domains'] ?? null) ? $stats['domains'] : [], (int) ($plan->domains ?? 1));
        $subdomainsLimit = self::keyhelpStatLimitInt(is_array($stats['subdomains'] ?? null) ? $stats['subdomains'] : [], (int) ($plan->subdomains ?? 0));
        $mailboxesLimit = self::keyhelpStatLimitInt(is_array($stats['email_accounts'] ?? null) ? $stats['email_accounts'] : [], (int) ($plan->mailboxes ?? 0));
        $databasesLimit = self::keyhelpStatLimitInt(is_array($stats['databases'] ?? null) ? $stats['databases'] : [], (int) ($plan->databases ?? 0));

        $extras = [];
        foreach (['traffic', 'files', 'ftp_users', 'scheduled_tasks', 'email_addresses', 'email_forwardings'] as $key) {
            if (! isset($stats[$key]) || ! is_array($stats[$key])) {
                continue;
            }
            $extras[$key] = [
                'value' => (int) ($stats[$key]['value'] ?? 0),
                'max' => (int) ($stats[$key]['max'] ?? 0),
            ];
        }

        return [
            'disk_used_bytes' => $usage['disk_bytes'],
            'disk_limit_bytes' => $diskLimitBytes,
            'domains_used' => $usage['domains_used'],
            'domains_limit' => $domainsLimit,
            'subdomains_used' => $usage['subdomains_used'],
            'subdomains_limit' => $subdomainsLimit,
            'mailboxes_used' => $usage['mailboxes_used'],
            'mailboxes_limit' => $mailboxesLimit,
            'databases_used' => $usage['databases_used'],
            'databases_limit' => $databasesLimit,
            'keyhelp_extras' => $extras,
        ];
    }

    /**
     * @return array{disk_bytes: int, domains_used: int, subdomains_used: int, mailboxes_used: int, databases_used: int}|null
     */
    public function getWebspaceResourceUsage(int $userId, string $domain): ?array
    {
        $ctx = $this->fetchClientUsageContext($userId, $domain);

        return $ctx === null ? null : $ctx['usage'];
    }

    /**
     * @return array{disk_bytes: int, domains_used: int, subdomains_used: int, mailboxes_used: int, databases_used: int}|null
     */
    protected function getWebspaceResourceUsageFromDomains(int $userId, string $domain): ?array
    {
        if (! $this->server) {
            return null;
        }

        try {
            $response = $this->http()->get($this->apiUrl('/domains'), [
                'id_user' => $userId,
            ]);
            if (! $response->successful()) {
                $response = $this->http()->get($this->apiUrl('/domains'), [
                    'id_client' => $userId,
                ]);
            }
            if (! $response->successful()) {
                return null;
            }
            $payload = $response->json();
            $rows = is_array($payload) ? ($payload['data'] ?? $payload['items'] ?? $payload) : [];
            if (! is_array($rows)) {
                return null;
            }
            $match = null;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $d = $row['domain'] ?? $row['name'] ?? null;
                if (is_string($d) && strcasecmp($d, $domain) === 0) {
                    $match = $row;
                    break;
                }
            }
            if ($match === null) {
                return null;
            }

            $diskBytes = (int) ($match['disk_usage_bytes'] ?? $match['disk_used'] ?? $match['quota_used'] ?? 0);

            return [
                'disk_bytes' => $diskBytes,
                'domains_used' => 1,
                'subdomains_used' => (int) ($match['subdomains_count'] ?? $match['subdomains'] ?? 0),
                'mailboxes_used' => (int) ($match['mailboxes_count'] ?? $match['mailboxes'] ?? 0),
                'databases_used' => (int) ($match['databases_count'] ?? $match['databases'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('KeyHelp getWebspaceResourceUsageFromDomains failed', [
                'server' => $this->server->hostname,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizeClientResourcesPayload(array $payload): array
    {
        $out = [];

        if (isset($payload['domains']) && is_array($payload['domains'])) {
            $out['domains'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'domain' => (string) ($row['domain_utf8'] ?? $row['domain'] ?? ''),
                    'is_subdomain' => (bool) ($row['is_subdomain'] ?? false),
                    'php_version' => (string) ($row['php_version'] ?? ''),
                    'traffic' => (int) ($row['traffic'] ?? 0),
                    'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
                    'is_disabled' => (bool) ($row['is_disabled'] ?? false),
                ];
            }, $payload['domains'])));
        }

        if (isset($payload['certificates']) && is_array($payload['certificates'])) {
            $out['certificates'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'secured_domains' => is_array($row['secured_domains'] ?? null) ? $row['secured_domains'] : [],
                    'valid_till' => isset($row['valid_till']) ? (string) $row['valid_till'] : null,
                    'issuer' => (string) ($row['issuer'] ?? ''),
                ];
            }, $payload['certificates'])));
        }

        if (isset($payload['emails']) && is_array($payload['emails'])) {
            $out['emails'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'email' => (string) ($row['email_utf8'] ?? $row['email'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'size' => (int) ($row['size'] ?? 0),
                    'max_size' => (int) ($row['max_size'] ?? 0),
                    'aliases' => is_array($row['aliases_utf8'] ?? null) ? $row['aliases_utf8'] : (is_array($row['aliases'] ?? null) ? $row['aliases'] : []),
                    'forwardings' => is_array($row['forwardings_utf8'] ?? null) ? $row['forwardings_utf8'] : (is_array($row['forwardings'] ?? null) ? $row['forwardings'] : []),
                ];
            }, $payload['emails'])));
        }

        if (isset($payload['databases']) && is_array($payload['databases'])) {
            $out['databases'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'database_name' => (string) ($row['database_name'] ?? ''),
                    'database_username' => (string) ($row['database_username'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'size' => (int) ($row['size'] ?? 0),
                    'remote_hosts' => is_array($row['remote_hosts'] ?? null) ? $row['remote_hosts'] : [],
                ];
            }, $payload['databases'])));
        }

        if (isset($payload['ftp_users']) && is_array($payload['ftp_users'])) {
            $out['ftp_users'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'username' => (string) ($row['username'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'home_directory' => (string) ($row['home_directory'] ?? ''),
                ];
            }, $payload['ftp_users'])));
        }

        if (isset($payload['scheduled_tasks']) && is_array($payload['scheduled_tasks'])) {
            $out['scheduled_tasks'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }
                $schedule = is_array($row['schedule'] ?? null) ? $row['schedule'] : [];

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'type' => (string) ($row['type'] ?? ''),
                    'command' => (string) ($row['command'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'php_interpreter' => isset($row['php_interpreter']) ? (string) $row['php_interpreter'] : null,
                    'schedule' => [
                        'minute' => (string) ($schedule['minute'] ?? '*'),
                        'hour' => (string) ($schedule['hour'] ?? '*'),
                        'day_of_month' => (string) ($schedule['day_of_month'] ?? '*'),
                        'month' => (string) ($schedule['month'] ?? '*'),
                        'day_of_week' => (string) ($schedule['day_of_week'] ?? '*'),
                    ],
                ];
            }, $payload['scheduled_tasks'])));
        }

        if (isset($payload['directory_protections']) && is_array($payload['directory_protections'])) {
            $out['directory_protections'] = array_values(array_filter(array_map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'path' => (string) ($row['path'] ?? ''),
                    'auth_name' => (string) ($row['auth_name'] ?? ''),
                    'username' => (string) ($row['username'] ?? ''),
                    'exceptions' => is_array($row['exceptions'] ?? null) ? $row['exceptions'] : [],
                ];
            }, $payload['directory_protections'])));
        }

        return $out;
    }

    /**
     * @param  array{value?: mixed, max?: mixed}  $slice
     */
    private static function keyhelpStatLimitInt(array $slice, int $planFallback): int
    {
        $max = (int) ($slice['max'] ?? 0);
        if ($max > 0) {
            return $max;
        }
        if ($max < 0) {
            return max(0, $planFallback);
        }

        return $planFallback;
    }

    /**
     * Single sign-on URL if supported; otherwise null (customer uses manual login).
     */
    public function createCustomerLoginUrl(int $userId, string $clientIp): ?string
    {
        if (! $this->server) {
            return null;
        }

        foreach (["/users/{$userId}/session", "/users/{$userId}/login-session", "/users/{$userId}/sso"] as $path) {
            try {
                $response = $this->http()->post($this->apiUrl($path), [
                    'ip' => $clientIp,
                ]);
                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json)) {
                        $url = $json['url'] ?? $json['login_url'] ?? $json['redirect_url'] ?? null;
                        if (is_string($url) && str_starts_with($url, 'http')) {
                            return $url;
                        }
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public function panelBaseUrl(): string
    {
        if (! $this->server) {
            return '';
        }

        return rtrim($this->getBaseUrl(), '/');
    }

    /**
     * POST /api/v2/clients — KeyHelp „Creating a new client“ (id_hosting_plan, contact_data, …).
     *
     * @see https://app.swaggerhub.com/apis-docs/keyhelp/api/
     */
    protected function createUser(string $username, string $password, string $email, int $idHostingPlan): ?int
    {
        $body = [
            'username' => $username,
            'language' => $this->keyhelpLanguageCode(),
            'email' => $email,
            'password' => $password,
            'id_hosting_plan' => $idHostingPlan,
            'is_suspended' => false,
            'suspend_on' => null,
            'delete_on' => null,
            'send_login_credentials' => false,
            'create_system_domain' => false,
            'contact_data' => $this->buildKeyhelpContactData($username, $email),
        ];

        try {
            $response = $this->http()->post($this->apiUrl('/clients'), $body);
            if ($response->successful()) {
                $id = $this->extractIdFromJson($response->json());
                if ($id !== null) {
                    return $id;
                }
            }
            Log::warning('KeyHelp createUser: unexpected response', [
                'username' => $username,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('KeyHelp createUser error', ['username' => $username, 'message' => $e->getMessage()]);
        }

        Log::error('KeyHelp createUser: failed', ['username' => $username]);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildKeyhelpContactData(string $username, string $email): array
    {
        $local = strstr($email, '@', true);
        $local = is_string($local) && $local !== '' ? $local : $username;
        $nameParts = preg_split('/\s+/u', trim($username), 2, PREG_SPLIT_NO_EMPTY) ?: [$username];
        $firstName = $nameParts[0] ?? $local;
        $lastName = $nameParts[1] ?? $firstName;

        return [
            'first_name' => mb_substr($firstName, 0, 100),
            'last_name' => mb_substr($lastName, 0, 100),
            'company' => '',
            'telephone' => '',
            'address' => '',
            'city' => '',
            'zip' => '',
            'state' => '',
            'country' => 'DE',
        ];
    }

    protected function keyhelpLanguageCode(): string
    {
        $locale = strtolower((string) config('app.locale', 'de'));

        return str_starts_with($locale, 'de') ? 'de' : 'en';
    }

    protected function createDomain(int $clientId, string $domain): bool
    {
        $base = [
            'id_parent_domain' => 0,
            'domain' => $domain,
            'php_version' => '',
            'is_disabled' => false,
            'is_dns_disabled' => false,
            'is_email_domain' => true,
            'create_www_subdomain' => true,
        ];

        $payloads = [
            array_merge($base, ['id_user' => $clientId]),
            array_merge($base, ['id_client' => $clientId]),
        ];

        foreach ($payloads as $payload) {
            try {
                $response = $this->http()->post($this->apiUrl('/domains'), $payload);
                if ($response->successful()) {
                    return true;
                }
            } catch (RequestException $e) {
                Log::debug('KeyHelp createDomain HTTP error', ['message' => $e->getMessage()]);
            } catch (\Throwable $e) {
                Log::debug('KeyHelp createDomain attempt failed', ['message' => $e->getMessage()]);
            }
        }

        return false;
    }

    protected function patchUserDisabled(int $userId, bool $disabled): bool
    {
        if (! $this->server) {
            return false;
        }

        $attempts = [
            ['path' => "/clients/{$userId}", 'body' => ['is_suspended' => $disabled]],
            ['path' => "/users/{$userId}", 'body' => ['is_disabled' => $disabled]],
        ];

        foreach ($attempts as $attempt) {
            try {
                $response = $this->http()->patch($this->apiUrl($attempt['path']), $attempt['body']);
                if ($response->successful()) {
                    return true;
                }
                $response = $this->http()->put($this->apiUrl($attempt['path']), $attempt['body']);
                if ($response->successful()) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::debug('KeyHelp patchUserDisabled attempt failed', [
                    'path' => $attempt['path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::warning('KeyHelp patchUserDisabled failed', ['user_id' => $userId]);

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function extractIdFromJson(?array $json): ?int
    {
        if ($json === null) {
            return null;
        }
        foreach (['id', 'id_client'] as $key) {
            if (isset($json[$key]) && is_numeric($json[$key])) {
                return (int) $json[$key];
            }
        }
        if (isset($json['data']) && is_array($json['data'])) {
            $d = $json['data'];
            foreach (['id', 'id_client'] as $key) {
                if (isset($d[$key]) && is_numeric($d[$key])) {
                    return (int) $d[$key];
                }
            }
        }
        if (isset($json['client']) && is_array($json['client'])) {
            $c = $json['client'];
            foreach (['id', 'id_client'] as $key) {
                if (isset($c[$key]) && is_numeric($c[$key])) {
                    return (int) $c[$key];
                }
            }
        }

        return null;
    }

    /**
     * KeyHelp uses negative integers for unlimited quotas (same idea as traffic bytes).
     *
     * @return array{unlimited: bool, value: int|null}
     */
    private static function keyhelpResourceCount(mixed $raw): array
    {
        $n = (int) $raw;
        if ($n < 0) {
            return ['unlimited' => true, 'value' => null];
        }

        return ['unlimited' => false, 'value' => max(0, $n)];
    }

    protected function apiUrl(string $path): string
    {
        return '/api/v2/'.ltrim($path, '/');
    }

    protected function getBaseUrl(): string
    {
        if (! $this->server) {
            return '';
        }
        $protocol = ($this->server->use_ssl ?? true) ? 'https' : 'http';
        $host = $this->server->hostname;
        $port = $this->server->port;
        $port = $port === null || $port === '' ? null : (int) $port;
        if ($port !== null && $port > 0) {
            return $protocol.'://'.$host.':'.$port;
        }

        return $protocol.'://'.$host;
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        $token = trim((string) ($this->server?->api_token ?? ''));
        if ($token === '') {
            throw new Exception('KeyHelp: API-Token fehlt.');
        }

        return Http::baseUrl($this->getBaseUrl())
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Key' => $token,
            ])
            ->timeout(45)
            ->connectTimeout(15);
    }
}
