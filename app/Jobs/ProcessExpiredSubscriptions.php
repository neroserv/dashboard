<?php

namespace App\Jobs;

use App\Models\CronDailyStats;
use App\Models\GameServerAccount;
use App\Models\Setting;
use App\Models\SiteSubscription;
use App\Models\WebspaceAccount;
use App\Notifications\GameServerDeletedAfterGraceNotification;
use App\Notifications\GameServerSuspendedNotification;
use App\Notifications\SiteDeletedAfterGraceNotification;
use App\Notifications\SiteSuspendedNotification;
use App\Notifications\WebspaceDeactivatedNotification;
use App\Notifications\WebspaceDeletedAfterGraceNotification;
use App\Services\ControlPanels\PleskClient;
use App\Services\ControlPanels\PterodactylClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $gracePeriodDays = (int) (Setting::get('billing_grace_period_days') ?? config('billing.grace_period_days', 7));
        $now = Carbon::now();
        $graceCutoff = $now->copy()->subDays($gracePeriodDays);

        $this->processSiteSubscriptions($now, $gracePeriodDays, $graceCutoff);
        $this->processWebspaceAccounts($now, $graceCutoff);
        $this->processGameServerAccounts($now, $graceCutoff);
    }

    protected function processSiteSubscriptions(Carbon $now, int $gracePeriodDays, Carbon $graceCutoff): void
    {
        $toSuspend = SiteSubscription::query()
            ->with('site.user')
            ->whereHas('site', fn ($q) => $q->where('is_legacy', false)->where('status', 'active'))
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

        foreach ($toSuspend as $sub) {
            $site = $sub->site;
            $site->update(['status' => 'suspended']);
            $site->user?->notify(new SiteSuspendedNotification($site));
        }

        if ($toSuspend->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_suspended', $toSuspend->count());
        }

        $toTerminate = SiteSubscription::query()
            ->with('site.user')
            ->whereHas('site', fn ($q) => $q->where('is_legacy', false)->where('status', 'suspended'))
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $graceCutoff)
            ->get();

        foreach ($toTerminate as $sub) {
            $site = $sub->site;
            $siteName = $site->name;
            $user = $site->user;
            $sub->delete();
            $site->update(['published_version_id' => null, 'draft_version_id' => null]);
            $site->domains()->delete();
            $site->versions()->delete();
            $site->invitations()->delete();
            $site->collaborators()->detach();
            $site->delete();
            $user?->notify(new SiteDeletedAfterGraceNotification($siteName));
        }

        if ($toTerminate->isNotEmpty()) {
            CronDailyStats::incrementMetric('services_terminated', $toTerminate->count());
        }
    }

    protected function processWebspaceAccounts(Carbon $now, Carbon $graceCutoff): void
    {
        $toSuspend = WebspaceAccount::query()
            ->with('user', 'hostingServer')
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

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
            ->where('current_period_ends_at', '<', $graceCutoff)
            ->get();

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
            ->where('status', 'active')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $now)
            ->get();

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
            ->where('status', 'suspended')
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<', $graceCutoff)
            ->get();

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
}
