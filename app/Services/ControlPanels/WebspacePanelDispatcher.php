<?php

namespace App\Services\ControlPanels;

use App\Models\HostingPlan;
use App\Models\HostingServer;
use App\Models\WebspaceAccount;
use Exception;

class WebspacePanelDispatcher
{
    /**
     * KeyHelp accounts must never fall through to Plesk (wrong port 8443, wrong API).
     */
    protected function isKeyhelpWebspace(?HostingPlan $plan, HostingServer $server): bool
    {
        return ($plan?->getAttribute('panel_type') ?? 'plesk') === 'keyhelp'
            || ($server->getAttribute('panel_type') ?? 'plesk') === 'keyhelp';
    }

    /**
     * @return array{success: bool, message: string, info?: array<string, mixed>|null}
     */
    public function testConnection(HostingServer $server): array
    {
        return match ($server->getAttribute('panel_type') ?? 'plesk') {
            'keyhelp' => app(KeyHelpClient::class)->setServer($server)->testConnection(),
            default => app(PleskClient::class)->setServer($server)->testConnection(),
        };
    }

    /**
     * @throws Exception
     */
    public function provisionWebspaceAccount(
        WebspaceAccount $account,
        HostingServer $server,
        HostingPlan $plan,
        string $username,
        string $domain,
        string $password,
        ?string $email = null
    ): bool {
        $panelType = $plan->getAttribute('panel_type') ?? 'plesk';
        $email = $email ?? 'webspace@placeholder.local';
        $package = (string) ($plan->plesk_package_name ?? '');

        if ($panelType === 'keyhelp') {
            $client = app(KeyHelpClient::class);
            $client->setServer($server);
            $userId = $client->createAccount($username, $domain, $package, $password, $email);
            if ($userId === null) {
                return false;
            }
            $account->update(['keyhelp_user_id' => $userId]);

            return true;
        }

        $plesk = app(PleskClient::class);
        $plesk->setServer($server);

        return $plesk->createAccount($username, $domain, $package, $password, $email);
    }

    public function suspendWebspaceAccount(WebspaceAccount $account): bool
    {
        $server = $account->hostingServer;
        if (! $server) {
            return false;
        }
        $plan = $account->hostingPlan;
        if ($this->isKeyhelpWebspace($plan, $server)) {
            if ($account->keyhelp_user_id) {
                return app(KeyHelpClient::class)->setServer($server)->suspendUser((int) $account->keyhelp_user_id);
            }

            return false;
        }

        return app(PleskClient::class)->setServer($server)->suspendAccount($account->plesk_username);
    }

    public function unsuspendWebspaceAccount(WebspaceAccount $account): bool
    {
        $server = $account->hostingServer;
        if (! $server) {
            return false;
        }
        $plan = $account->hostingPlan;
        if ($this->isKeyhelpWebspace($plan, $server)) {
            if ($account->keyhelp_user_id) {
                return app(KeyHelpClient::class)->setServer($server)->unsuspendUser((int) $account->keyhelp_user_id);
            }

            return false;
        }

        return app(PleskClient::class)->setServer($server)->unsuspendAccount($account->plesk_username);
    }

    public function terminateWebspaceAccount(WebspaceAccount $account): bool
    {
        $server = $account->hostingServer;
        if (! $server) {
            return false;
        }
        $plan = $account->hostingPlan;
        if ($this->isKeyhelpWebspace($plan, $server)) {
            if ($account->keyhelp_user_id) {
                return app(KeyHelpClient::class)->setServer($server)->terminateUser((int) $account->keyhelp_user_id);
            }

            return false;
        }

        return app(PleskClient::class)->setServer($server)->terminateAccount($account->plesk_username);
    }

    /**
     * @return array{usage: array{disk_bytes: int, domains_used: int, subdomains_used: int, mailboxes_used: int, databases_used: int}, keyhelp_stats: array<string, mixed>|null}|null
     */
    public function getWebspaceResourceUsageForShow(WebspaceAccount $account): ?array
    {
        $server = $account->hostingServer;
        if (! $server) {
            return null;
        }
        $plan = $account->hostingPlan;
        if ($this->isKeyhelpWebspace($plan, $server)) {
            if (! $account->keyhelp_user_id) {
                return null;
            }
            $ctx = app(KeyHelpClient::class)
                ->setServer($server)
                ->fetchClientUsageContext((int) $account->keyhelp_user_id, $account->domain);
            if ($ctx === null) {
                return null;
            }

            return [
                'usage' => $ctx['usage'],
                'keyhelp_stats' => $ctx['stats'],
            ];
        }

        $plesk = app(PleskClient::class);
        $plesk->setServer($server);
        $usage = $plesk->getWebspaceResourceUsage($account->domain);
        if ($usage === null) {
            return null;
        }

        return [
            'usage' => $usage,
            'keyhelp_stats' => null,
        ];
    }

    /**
     * @return array{disk_bytes: int, domains_used: int, subdomains_used: int, mailboxes_used: int, databases_used: int}|null
     */
    public function getWebspaceResourceUsage(WebspaceAccount $account): ?array
    {
        $wrapped = $this->getWebspaceResourceUsageForShow($account);

        return $wrapped === null ? null : $wrapped['usage'];
    }

    /**
     * Redirect URL for panel login, or null if not available.
     */
    public function panelLoginUrl(WebspaceAccount $account, string $clientIp): ?string
    {
        $server = $account->hostingServer;
        if (! $server) {
            return null;
        }
        $plan = $account->hostingPlan;
        if ($this->isKeyhelpWebspace($plan, $server)) {
            $client = app(KeyHelpClient::class)->setServer($server);
            if ($account->keyhelp_user_id) {
                $url = $client->createCustomerLoginUrl((int) $account->keyhelp_user_id, $clientIp);
                if ($url !== null) {
                    return $url;
                }
            }
            $base = $client->panelBaseUrl();

            return $base !== '' ? $base : null;
        }

        $plesk = app(PleskClient::class);
        $plesk->setServer($server);
        $token = $plesk->createCustomerSession($account->plesk_username, $clientIp);
        if (! $token) {
            return null;
        }
        $protocol = ($server->use_ssl ?? true) ? 'https' : 'http';
        $port = $server->port !== null && (int) $server->port > 0 ? (int) $server->port : 8443;

        return $protocol.'://'.$server->hostname.':'.$port.'/enterprise/rsession_init.php?PHPSESSID='.urlencode($token);
    }
}
