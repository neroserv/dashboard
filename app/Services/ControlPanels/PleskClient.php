<?php

namespace App\Services\ControlPanels;

use App\Models\HostingServer;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PleskClient
{
    protected Client $client;

    protected ?HostingServer $server = null;

    protected ?string $apiKey = null;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    public function setServer(HostingServer $server): void
    {
        $this->server = $server;
        $this->apiKey = $server->api_token;
    }

    /**
     * Create a Plesk webspace account. Password is passed in and used for owner and FTP.
     * When using REST API, optional email defaults to a placeholder if not provided.
     */
    public function createAccount(string $username, string $domain, string $package, string $password, ?string $email = null): bool
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        if ($this->server->usesRestApi()) {
            return $this->createAccountRest($username, $domain, $package, $password, $email ?? 'webspace@placeholder.local');
        }

        return $this->createAccountXml($username, $domain, $package, $password);
    }

    /**
     * Create a session token for the customer to log into Plesk panel (server.create_session).
     * Returns the session ID or null on failure.
     */
    public function createCustomerSession(string $pleskUsername, string $clientIp): ?string
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        $userIpBase64 = base64_encode($clientIp);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<packet version="1.6.9.1">';
        $xml .= '<server>';
        $xml .= '<create_session>';
        $xml .= '<login>'.htmlspecialchars($pleskUsername, ENT_XML1).'</login>';
        $xml .= '<data>';
        $xml .= '<user_ip>'.htmlspecialchars($userIpBase64, ENT_XML1).'</user_ip>';
        $xml .= '<source_server></source_server>';
        $xml .= '</data>';
        $xml .= '</create_session>';
        $xml .= '</server>';
        $xml .= '</packet>';

        $url = $this->getBaseUrl().'/api/v2/cli/server/';
        $headers = ['Content-Type' => 'text/xml'];
        if ($this->server->usesRestApi()) {
            $headers['Authorization'] = 'Basic '.base64_encode($this->server->api_username.':'.$this->server->api_token);
        } else {
            $headers['HTTP_AUTH_KEY'] = $this->apiKey;
            $headers['KEY'] = $this->apiKey;
        }

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers,
                'body' => $xml,
            ]);

            $result = simplexml_load_string($response->getBody()->getContents());
            $createSession = $result->server->create_session->result ?? null;
            if ($createSession && (string) $createSession->status === 'ok' && isset($createSession->id)) {
                return (string) $createSession->id;
            }

            Log::warning('Plesk create_session failed', [
                'server' => $this->server->hostname,
                'response' => $response->getBody()->getContents(),
            ]);

            return null;
        } catch (GuzzleException $e) {
            Log::error('Plesk create_session error', [
                'server' => $this->server->hostname,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function suspendAccount(string $username): bool
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        if ($this->server->usesRestApi()) {
            return $this->suspendAccountRest($username);
        }

        $xml = $this->buildXmlRequest('customer.set', [
            'filter' => ['login' => $username],
            'values' => ['status' => '16'],
            'general' => ['status' => 'suspended'],
        ]);

        return $this->makeXmlApiCall($xml);
    }

    public function unsuspendAccount(string $username): bool
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        if ($this->server->usesRestApi()) {
            return $this->unsuspendAccountRest($username);
        }

        $xml = $this->buildXmlRequest('customer.set', [
            'filter' => ['login' => $username],
            'values' => ['status' => '0'],
            'general' => ['status' => 'active'],
        ]);

        return $this->makeXmlApiCall($xml);
    }

    public function changePackage(string $username, string $newPackage): bool
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        if ($this->server->usesRestApi()) {
            return $this->changePackageRest($username, $newPackage);
        }

        $xml = $this->buildXmlRequest('service-plan.set', [
            'filter' => ['owner-login' => $username],
            'values' => ['name' => $newPackage],
        ]);

        return $this->makeXmlApiCall($xml);
    }

    public function terminateAccount(string $username): bool
    {
        if (! $this->server) {
            throw new Exception('Server not configured');
        }

        if ($this->server->usesRestApi()) {
            return $this->terminateAccountRest($username);
        }

        $xml = $this->buildXmlRequest('webspace.del', [
            'filter' => ['owner-login' => $username],
        ]);

        return $this->makeXmlApiCall($xml);
    }

    public function addAddon(string $username, string $addon): bool
    {
        $xml = $this->buildXmlRequest('site-addon.add', [
            'filter' => ['owner-login' => $username],
            'addon' => ['name' => $addon],
        ]);

        return $this->makeXmlApiCall($xml);
    }

    public function removeAddon(string $username, string $addon): bool
    {
        $xml = $this->buildXmlRequest('site-addon.del', [
            'filter' => ['owner-login' => $username],
            'addon' => ['name' => $addon],
        ]);

        return $this->makeXmlApiCall($xml);
    }

    /**
     * Test connection (REST API only). Returns ['success' => bool, 'message' => string, 'info' => array|null].
     */
    public function testConnection(): array
    {
        if (! $this->server || ! $this->server->usesRestApi()) {
            return ['success' => false, 'message' => 'REST API (api_username) required for connection test'];
        }

        try {
            $result = $this->makeRestRequest('GET', '/server');

            return [
                'success' => true,
                'message' => 'Connection successful',
                'info' => [
                    'hostname' => $result['hostname'] ?? null,
                    'platform' => $result['platform'] ?? null,
                    'panel_version' => $result['panel_version'] ?? null,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List service plans (REST API only). Returns array of plans with name, id, limits.
     *
     * @return array<int, array{name: string, id: int, disk_quota: mixed, bandwidth: mixed, domains: mixed, mailboxes: mixed, databases: mixed}>
     */
    public function getPackages(): array
    {
        if (! $this->server || ! $this->server->usesRestApi()) {
            return [];
        }

        $result = $this->makeRestRequest('GET', '/service-plans');
        if (! is_array($result)) {
            return [];
        }
        $plans = [];
        foreach ($result as $plan) {
            $limits = $plan['limits'] ?? [];
            $plans[] = [
                'name' => $plan['name'] ?? '',
                'id' => $plan['id'] ?? 0,
                'disk_quota' => $limits['disk_space'] ?? null,
                'bandwidth' => $limits['traffic'] ?? null,
                'domains' => $limits['domains'] ?? null,
                'mailboxes' => $limits['mailboxes'] ?? null,
                'databases' => $limits['databases'] ?? null,
            ];
        }

        return $plans;
    }

    protected function getBaseUrl(): string
    {
        $protocol = ($this->server->use_ssl ?? true) ? 'https' : 'http';
        $port = (int) ($this->server->port ?? 8443);

        return $protocol.'://'.$this->server->hostname.':'.$port;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    protected function makeRestRequest(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->getBaseUrl().'/api/v2'.$endpoint;
        $options = [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($this->server->api_username.':'.$this->server->api_token),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];
        if ($data !== null) {
            $options['json'] = $data;
        }

        $response = $this->client->request($method, $url, $options);
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: '.$body);
        }

        return $decoded ?? [];
    }

    protected function createAccountRest(string $username, string $domain, string $package, string $password, string $email): bool
    {
        try {
            $customer = $this->makeRestRequest('POST', '/customers', [
                'name' => $username,
                'login' => $username,
                'password' => $password,
                'email' => $email,
            ]);
            $customerId = $customer['id'] ?? null;
            if (! $customerId) {
                Log::error('Plesk REST create customer: no id in response', ['response' => $customer]);

                return false;
            }

            $this->makeRestRequest('POST', '/domains', [
                'name' => $domain,
                'owner' => ['id' => (int) $customerId],
                'plan' => ['name' => $package],
                'hosting_type' => 'virtual',
                'hosting_settings' => [
                    'ftp_login' => $username,
                    'ftp_password' => $password,
                ],
            ]);

            Log::info('Plesk REST account created', ['server' => $this->server->hostname, 'domain' => $domain]);

            return true;
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse() !== null) {
                $body = (string) $e->getResponse()->getBody();
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['message'])) {
                    $message = $decoded['message'];
                }
            }
            Log::error('Plesk REST create account error', [
                'server' => $this->server->hostname,
                'error' => $message,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Plesk REST create account error', [
                'server' => $this->server->hostname,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function createAccountXml(string $username, string $domain, string $package, string $password): bool
    {
        $xml = $this->buildXmlRequest('webspace.add', [
            'gen_setup' => [
                'name' => $domain,
                'owner-login' => $username,
                'owner-password' => $password,
                'ip_address' => $this->server->ip_address,
            ],
            'hosting' => [
                'vrt_hst' => [
                    'property' => [
                        ['name' => 'ftp_login', 'value' => $username],
                        ['name' => 'ftp_password', 'value' => $password],
                        ['name' => 'php', 'value' => 'true'],
                        ['name' => 'ssl', 'value' => 'true'],
                        ['name' => 'webstat', 'value' => 'awstats'],
                        ['name' => 'www-root', 'value' => "/var/www/vhosts/{$domain}"],
                    ],
                ],
            ],
            'limits' => [
                'overuse' => 'block',
                'limit' => [
                    ['name' => 'disk_space', 'value' => 'unlimited'],
                    ['name' => 'max_traffic', 'value' => 'unlimited'],
                    ['name' => 'max_subdom', 'value' => 'unlimited'],
                    ['name' => 'max_dom', 'value' => 'unlimited'],
                    ['name' => 'max_db', 'value' => 'unlimited'],
                    ['name' => 'max_mail', 'value' => 'unlimited'],
                    ['name' => 'max_wu', 'value' => 'unlimited'],
                ],
            ],
            'plan-name' => $package,
        ]);

        return $this->makeXmlApiCall($xml);
    }

    protected function suspendAccountRest(string $username): bool
    {
        try {
            $customers = $this->makeRestRequest('GET', '/customers?login='.urlencode($username));
            $customers = is_array($customers) ? $customers : [];
            if (isset($customers['id'])) {
                $customers = [$customers];
            }
            $customerId = $customers[0]['id'] ?? null;
            if (! $customerId) {
                Log::warning('Plesk REST suspend: customer not found', ['login' => $username]);

                return false;
            }
            $domains = $this->makeRestRequest('GET', '/domains?owner_id='.(int) $customerId);
            $domains = is_array($domains) ? $domains : [];
            if (isset($domains['id'])) {
                $domains = [$domains];
            }
            foreach ($domains as $domain) {
                $id = $domain['id'] ?? null;
                if ($id !== null) {
                    $this->makeRestRequest('PUT', '/domains/'.(int) $id, ['status' => 'disabled']);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Plesk REST suspend error', ['server' => $this->server->hostname, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected function unsuspendAccountRest(string $username): bool
    {
        try {
            $customers = $this->makeRestRequest('GET', '/customers?login='.urlencode($username));
            $customers = is_array($customers) ? $customers : [];
            if (isset($customers['id'])) {
                $customers = [$customers];
            }
            $customerId = $customers[0]['id'] ?? null;
            if (! $customerId) {
                return false;
            }
            $domains = $this->makeRestRequest('GET', '/domains?owner_id='.(int) $customerId);
            $domains = is_array($domains) ? $domains : [];
            if (isset($domains['id'])) {
                $domains = [$domains];
            }
            foreach ($domains as $domain) {
                $id = $domain['id'] ?? null;
                if ($id !== null) {
                    $this->makeRestRequest('PUT', '/domains/'.(int) $id, ['status' => 'active']);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Plesk REST unsuspend error', ['server' => $this->server->hostname, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected function changePackageRest(string $username, string $newPackage): bool
    {
        try {
            $customers = $this->makeRestRequest('GET', '/customers?login='.urlencode($username));
            $customers = is_array($customers) ? $customers : [];
            if (isset($customers['id'])) {
                $customers = [$customers];
            }
            $customerId = $customers[0]['id'] ?? null;
            if (! $customerId) {
                return false;
            }
            $domains = $this->makeRestRequest('GET', '/domains?owner_id='.(int) $customerId);
            $domains = is_array($domains) ? $domains : [];
            if (isset($domains['id'])) {
                $domains = [$domains];
            }
            foreach ($domains as $domain) {
                $id = $domain['id'] ?? null;
                if ($id !== null) {
                    $this->makeRestRequest('PUT', '/domains/'.(int) $id, ['plan' => ['name' => $newPackage]]);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Plesk REST change package error', ['server' => $this->server->hostname, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected function terminateAccountRest(string $username): bool
    {
        try {
            $customers = $this->makeRestRequest('GET', '/customers?login='.urlencode($username));
            $customers = is_array($customers) ? $customers : [];
            if (isset($customers['id'])) {
                $customers = [$customers];
            }
            $customerId = $customers[0]['id'] ?? null;
            if (! $customerId) {
                Log::warning('Plesk REST terminate: customer not found', ['login' => $username]);

                return false;
            }
            $domains = $this->makeRestRequest('GET', '/domains?owner_id='.(int) $customerId);
            $domains = is_array($domains) ? $domains : [];
            if (isset($domains['id'])) {
                $domains = [$domains];
            }
            foreach ($domains as $domain) {
                $id = $domain['id'] ?? null;
                if ($id !== null) {
                    $this->makeRestRequest('DELETE', '/domains/'.(int) $id);
                }
            }
            $this->makeRestRequest('DELETE', '/customers/'.(int) $customerId);

            return true;
        } catch (Exception $e) {
            Log::error('Plesk REST terminate error', ['server' => $this->server->hostname, 'error' => $e->getMessage()]);

            return false;
        }
    }

    protected function makeXmlApiCall(string $xml): bool
    {
        $url = $this->getBaseUrl().'/api/v2/cli/server/';
        $headers = [
            'Content-Type' => 'text/xml',
            'HTTP_AUTH_KEY' => $this->apiKey,
            'KEY' => $this->apiKey,
        ];

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => $headers,
                'body' => $xml,
            ]);

            $result = simplexml_load_string($response->getBody()->getContents());

            if ((string) $result->status === 'ok') {
                Log::info('Plesk API call successful', ['server' => $this->server->hostname]);

                return true;
            }

            Log::error('Plesk API call failed', [
                'server' => $this->server->hostname,
                'error' => (string) $result->errtext,
            ]);

            return false;
        } catch (GuzzleException $e) {
            Log::error('Plesk API call error', [
                'server' => $this->server->hostname,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function buildXmlRequest(string $command, array $params): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<packet version="1.6.9.1">';
        $xml .= "<{$command}>";
        $xml .= $this->arrayToXml($params);
        $xml .= "</{$command}>";
        $xml .= '</packet>';

        return $xml;
    }

    /**
     * @param  array<string, mixed>  $array
     */
    protected function arrayToXml(array $array): string
    {
        $xml = '';
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (isset($value['name']) && isset($value['value'])) {
                    $xml .= '<'.$key.' name="'.htmlspecialchars($value['name'], ENT_XML1).'">'.htmlspecialchars((string) $value['value'], ENT_XML1).'</'.$key.'>';
                } else {
                    $xml .= '<'.$key.'>'.$this->arrayToXml($value).'</'.$key.'>';
                }
            } else {
                $xml .= '<'.$key.'>'.htmlspecialchars((string) $value, ENT_XML1).'</'.$key.'>';
            }
        }

        return $xml;
    }
}
