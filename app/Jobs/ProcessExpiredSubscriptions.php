<?php

namespace App\Jobs;

use App\Models\CronDailyStats;
use App\Models\GameServerAccount;
use App\Models\GameserverCloudSubscription;
use App\Models\Setting;
use App\Models\TeamSpeakServerAccount;
use App\Models\WebspaceAccount;
use App\Notifications\GameserverCloudSubscriptionDeletedAfterGraceNotification;
use App\Notifications\GameServerDeletedAfterGraceNotification;
use App\Notifications\GameServerSuspendedNotification;
use App\Notifications\TeamSpeakDeletedAfterGraceNotification;
use App\Notifications\TeamSpeakSuspendedNotification;
use App\Notifications\WebspaceDeactivatedNotification;
use App\Notifications\WebspaceDeletedAfterGraceNotification;
use App\Services\ControlPanels\PleskClient;
use App\Services\ControlPanels\PterodactylClient;
use App\Services\ControlPanels\TeamSpeakClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class ProcessExpiredSubscriptions implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    public function handle(): void
    {
        $gracePeriodDays = max(1, (int) (Setting::get('billing_grace_period_days') ?? config('billing.grace_period_days', 7)));
        $now = Carbon::now(config('app.timezone'));
        // Kulanzfrist in Kalendertagen: Abgelaufen am Tag X → löschbar ab Tag X+7 (Mitternacht)
        $graceCutoff = $now->copy()->subDays($gracePeriodDays)->endOfDay();

        Log::debug('ProcessExpiredSubscriptions: run', [
            'now' => $now->toIso8601String(),
            'grace_period_days' => $gracePeriodDays,
            'grace_cutoff' => $graceCutoff->toIso8601String(),
        ]);

        $this->processWebspaceAccounts($now, $graceCutoff);
        $this->processGameServerAccounts($now, $graceCutoff);
        $this->processGameserverCloudSubscriptions($now, $graceCutoff);
        $this->processTeamSpeakServerAccounts($now, $graceCutoff);
    }

    protected function processWebspaceAccounts(Carbon $now, Carbon $graceCutoff): void
    {
        $toSuspend = WebspaceAccount::query()
            ->with('user', 'hostingServer')
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

        if ($toSuspend->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: webspace accounts to suspend', [
                'count' => $toSuspend->count(),
                'ids' => $toSuspend->pluck('id')->all(),
            ]);
        }

        foreach ($toSuspend as $account) {
            $server = $account->hostingServer;
            if ($server) {
                try {
                    $plesk = app(PleskClient::class);
                    $plesk->setServer($server);
                    $plesk->suspendAccount($account->plesk_username);
                } catch (\Throwable $e) {
                    Log::warning('ProcessExpiredSubscriptions: Webspace Plesk suspend failed', [
                        'webspace_account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $account->update(['status' => 'suspended']);
            $account->user?->notify(new WebspaceDeactivatedNotification($account));
        }

        if ($toSuspend->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_suspended', $toSuspend->count());
        }

        $toTerminate = WebspaceAccount::query()
            ->with('user', 'hostingServer')
            ->where('status', 'suspended')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $graceCutoff->format('Y-m-d H:i:s'))
            ->get();

        if ($toTerminate->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: webspace accounts to terminate (after grace)', [
                'count' => $toTerminate->count(),
                'ids' => $toTerminate->pluck('id')->all(),
            ]);
        }

        foreach ($toTerminate as $account) {
            $domain = $account->domain;
            $user = $account->user;
            $server = $account->hostingServer;
            if ($server) {
                try {
                    $plesk = app(PleskClient::class);
                    $plesk->setServer($server);
                    $plesk->terminateAccount($account->plesk_username);
                } catch (\Throwable $e) {
                    Log::warning('ProcessExpiredSubscriptions: Webspace Plesk terminate failed', [
                        'webspace_account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $account->delete();
            $user?->notify(new WebspaceDeletedAfterGraceNotification($domain));
        }

        if ($toTerminate->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_terminated', $toTerminate->count());
        }
    }

    protected function processGameServerAccounts(Carbon $now, Carbon $graceCutoff): void
    {
        $toSuspend = GameServerAccount::query()
            ->with('user', 'hostingServer')
            ->whereNotNull('hosting_plan_id')
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

        if ($toSuspend->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: game servers to suspend', [
                'count' => $toSuspend->count(),
                'ids' => $toSuspend->pluck('id')->all(),
            ]);
        }

        foreach ($toSuspend as $account) {
            if ($account->hostingServer && $account->pterodactyl_server_id) {
                try {
                    $client = app(PterodactylClient::class);
                    $client->suspendServer($account);
                } catch (\Throwable $e) {
                    Log::warning('ProcessExpiredSubscriptions: Game server Pterodactyl suspend failed', [
                        'game_server_account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $account->update(['status' => 'suspended']);
            $account->user?->notify(new GameServerSuspendedNotification($account));
        }

        if ($toSuspend->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_suspended', $toSuspend->count());
        }

        $toTerminate = GameServerAccount::query()
            ->with('user', 'hostingServer')
            ->whereNotNull('hosting_plan_id')
            ->where('status', 'suspended')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $graceCutoff->format('Y-m-d H:i:s'))
            ->get();

        if ($toTerminate->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: game servers to terminate (after grace)', [
                'count' => $toTerminate->count(),
                'ids' => $toTerminate->pluck('id')->all(),
                'grace_cutoff' => $graceCutoff->toIso8601String(),
            ]);
        }

        foreach ($toTerminate as $account) {
            $name = $account->name;
            $user = $account->user;
            if ($account->hostingServer && $account->pterodactyl_server_id) {
                try {
                    $client = app(PterodactylClient::class);
                    $client->deleteServer($account);
                } catch (\Throwable $e) {
                    Log::warning('ProcessExpiredSubscriptions: Game server Pterodactyl delete failed', [
                        'game_server_account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $account->delete();
            $user?->notify(new GameServerDeletedAfterGraceNotification($name));
        }

        if ($toTerminate->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_terminated', $toTerminate->count());
        }
    }

    protected function processGameserverCloudSubscriptions(Carbon $now, Carbon $graceCutoff): void
    {
        $toSuspend = GameserverCloudSubscription::query()
            ->with(['user', 'gameserverCloudPlan', 'gameServerAccounts.hostingServer'])
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

        if ($toSuspend->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: gameserver cloud subscriptions to suspend', [
                'count' => $toSuspend->count(),
                'ids' => $toSuspend->pluck('id')->all(),
            ]);
        }

        foreach ($toSuspend as $subscription) {
            foreach ($subscription->gameServerAccounts as $account) {
                if ($account->hostingServer && $account->pterodactyl_server_id) {
                    try {
                        $client = app(PterodactylClient::class);
                        $client->suspendServer($account);
                    } catch (\Throwable $e) {
                        Log::warning('ProcessExpiredSubscriptions: Gameserver cloud account Pterodactyl suspend failed', [
                            'game_server_account_id' => $account->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $account->update(['status' => 'suspended']);
                $account->user?->notify(new GameServerSuspendedNotification($account));
            }
            $subscription->update(['status' => 'suspended']);
        }

        if ($toSuspend->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_suspended', $toSuspend->count());
        }

        $toTerminate = GameserverCloudSubscription::query()
            ->with(['user', 'gameserverCloudPlan', 'gameServerAccounts.hostingServer'])
            ->where('status', 'suspended')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $graceCutoff->format('Y-m-d H:i:s'))
            ->get();

        if ($toTerminate->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: gameserver cloud subscriptions to terminate (after grace)', [
                'count' => $toTerminate->count(),
                'ids' => $toTerminate->pluck('id')->all(),
                'grace_cutoff' => $graceCutoff->toIso8601String(),
            ]);
        }

        foreach ($toTerminate as $subscription) {
            $user = $subscription->user;
            foreach ($subscription->gameServerAccounts as $account) {
                if ($account->hostingServer && $account->pterodactyl_server_id) {
                    try {
                        $client = app(PterodactylClient::class);
                        $client->deleteServer($account);
                    } catch (\Throwable $e) {
                        Log::warning('ProcessExpiredSubscriptions: Gameserver cloud account Pterodactyl delete failed', [
                            'game_server_account_id' => $account->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $account->delete();
            }
            $subscription->update(['status' => 'cancelled']);
            $user?->notify(new GameserverCloudSubscriptionDeletedAfterGraceNotification($subscription));
        }

        if ($toTerminate->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_terminated', $toTerminate->count());
        }
    }

    protected function processTeamSpeakServerAccounts(Carbon $now, Carbon $graceCutoff): void
    {
        $toSuspend = TeamSpeakServerAccount::query()
            ->with('user', 'hostingServer')
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

        if ($toSuspend->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: TeamSpeak servers to suspend', [
                'count' => $toSuspend->count(),
                'ids' => $toSuspend->pluck('id')->all(),
            ]);
        }

        foreach ($toSuspend as $account) {
            if ($account->hostingServer && $account->virtual_server_id !== null) {
                try {
                    $client = app(TeamSpeakClient::class);
                    $client->setServer($account->hostingServer);
                    $client->stopVirtualServer((int) $account->virtual_server_id);
                } catch (\Throwable $e) {
                    Log::warning('ProcessExpiredSubscriptions: TeamSpeak server stop failed', [
                        'team_speak_server_account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $account->update(['status' => 'suspended']);
            $account->user?->notify(new TeamSpeakSuspendedNotification($account));
        }

        if ($toSuspend->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_suspended', $toSuspend->count());
        }

        $toTerminate = TeamSpeakServerAccount::query()
            ->with('user', 'hostingServer')
            ->where('status', 'suspended')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $graceCutoff->format('Y-m-d H:i:s'))
            ->get();

        if ($toTerminate->isNotEmpty()) {
            Log::info('ProcessExpiredSubscriptions: TeamSpeak servers to terminate (after grace)', [
                'count' => $toTerminate->count(),
                'ids' => $toTerminate->pluck('id')->all(),
                'grace_cutoff' => $graceCutoff->toIso8601String(),
            ]);
        }

        foreach ($toTerminate as $account) {
            $name = $account->name;
            $user = $account->user;
            if ($account->hostingServer && $account->virtual_server_id !== null) {
                try {
                    $client = app(TeamSpeakClient::class);
                    $client->setServer($account->hostingServer);
                    $client->deleteVirtualServer((int) $account->virtual_server_id);
                } catch (\Throwable $e) {
                    Log::warning('ProcessExpiredSubscriptions: TeamSpeak server delete failed', [
                        'team_speak_server_account_id' => $account->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $account->delete();
            $user?->notify(new TeamSpeakDeletedAfterGraceNotification($name));
        }

        if ($toTerminate->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_terminated', $toTerminate->count());
        }
    }
}
