<?php

namespace App\Jobs;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Brand;
use App\Models\CustomerBalance;
use App\Models\GameServerAccount;
use App\Models\TeamSpeakServerAccount;
use App\Models\WebspaceAccount;
use App\Services\BalancePaymentService;
use App\Services\ControlPanels\PleskClient;
use App\Services\ControlPanels\PterodactylClient;
use App\Services\ControlPanels\TeamSpeakClient;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoRenewPrepaidWithBalanceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job: renew prepaid accounts that have auto_renew_with_balance
     * and expire tomorrow, if the user has enough balance.
     */
    public function handle(): void
    {
        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();

        $this->renewGameServers($tomorrowStart, $tomorrowEnd);
        $this->renewWebspaces($tomorrowStart, $tomorrowEnd);
        $this->renewTeamSpeakServers($tomorrowStart, $tomorrowEnd);
    }

    /**
     * @param  array<string, mixed>  $brandFeatures
     */
    private function getPeriodMonths(array $brandFeatures): int
    {
        return max(1, min(24, (int) ($brandFeatures['balance_period_months'] ?? config('billing.balance_period_months', 1))));
    }

    private function renewGameServers(Carbon $tomorrowStart, Carbon $tomorrowEnd): void
    {
        $accounts = GameServerAccount::query()
            ->where('auto_renew_with_balance', true)
            ->where('status', 'active')
            ->whereBetween('current_period_ends_at', [$tomorrowStart, $tomorrowEnd])
            ->with(['user.brand', 'hostingPlan', 'hostingServer'])
            ->get();

        foreach ($accounts as $account) {
            $this->renewGameServer($account);
        }
    }

    private function renewGameServer(GameServerAccount $account): void
    {
        $brand = $account->user?->brand ?? Brand::getDefault();
        $brandFeatures = $brand?->getFeaturesArray() ?? [];
        if (! ($brandFeatures['prepaid_balance'] ?? false)) {
            return;
        }

        $plan = $account->hostingPlan;
        if (! $plan || ! $plan->is_active || $plan->price === null || (float) $plan->price <= 0) {
            return;
        }

        $periodMonths = $this->getPeriodMonths($brandFeatures);
        $amount = (float) $plan->price * $periodMonths;

        $balance = CustomerBalance::where('user_id', $account->user_id)->first();
        $customerBalance = $balance ? (float) $balance->balance : 0.0;
        if ($customerBalance < $amount) {
            Log::info('AutoRenewPrepaidWithBalance: insufficient balance for game server', [
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'required' => $amount,
                'balance' => $customerBalance,
            ]);

            return;
        }

        try {
            app(BalancePaymentService::class)->pay(
                $account->user,
                $amount,
                'game_server_renewal',
                'Game-Server Auto-Verlängerung: '.$account->name,
                ['description' => 'Auto-Verlängerung Game-Server '.$account->name.' (ID '.$account->id.')']
            );
        } catch (InsufficientBalanceException $e) {
            Log::warning('AutoRenewPrepaidWithBalance: balance payment failed for game server', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $from = $account->current_period_ends_at && $account->current_period_ends_at->isFuture()
            ? $account->current_period_ends_at
            : now();
        $account->update([
            'current_period_ends_at' => $from->copy()->addMonths($periodMonths),
            'status' => 'active',
        ]);

        if ($account->hostingServer && $account->pterodactyl_server_id) {
            try {
                app(PterodactylClient::class)->unsuspendServer($account->fresh());
            } catch (\Throwable $e) {
                Log::debug('AutoRenewPrepaidWithBalance: Pterodactyl unsuspend skipped', ['account_id' => $account->id, 'message' => $e->getMessage()]);
            }
        }

        Log::info('AutoRenewPrepaidWithBalance: game server renewed', ['account_id' => $account->id]);
    }

    private function renewWebspaces(Carbon $tomorrowStart, Carbon $tomorrowEnd): void
    {
        $accounts = WebspaceAccount::query()
            ->where('auto_renew_with_balance', true)
            ->where('status', 'active')
            ->whereBetween('current_period_ends_at', [$tomorrowStart, $tomorrowEnd])
            ->with(['user.brand', 'hostingPlan', 'hostingServer'])
            ->get();

        foreach ($accounts as $account) {
            $this->renewWebspace($account);
        }
    }

    private function renewWebspace(WebspaceAccount $account): void
    {
        $brand = $account->user?->brand ?? Brand::getDefault();
        $brandFeatures = $brand?->getFeaturesArray() ?? [];
        if (! ($brandFeatures['prepaid_balance'] ?? false)) {
            return;
        }

        $plan = $account->hostingPlan;
        if (! $plan || ! $plan->is_active || $plan->price === null || (float) $plan->price <= 0) {
            return;
        }

        $periodMonths = $this->getPeriodMonths($brandFeatures);
        $amount = (float) $plan->price * $periodMonths;

        $balance = CustomerBalance::where('user_id', $account->user_id)->first();
        $customerBalance = $balance ? (float) $balance->balance : 0.0;
        if ($customerBalance < $amount) {
            Log::info('AutoRenewPrepaidWithBalance: insufficient balance for webspace', [
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'required' => $amount,
                'balance' => $customerBalance,
            ]);

            return;
        }

        try {
            app(BalancePaymentService::class)->pay(
                $account->user,
                $amount,
                'webspace_renewal',
                'Webspace Auto-Verlängerung: '.$account->domain,
                ['description' => 'Auto-Verlängerung Webspace '.$account->domain.' (ID '.$account->id.')']
            );
        } catch (InsufficientBalanceException $e) {
            Log::warning('AutoRenewPrepaidWithBalance: balance payment failed for webspace', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $from = $account->current_period_ends_at && $account->current_period_ends_at->isFuture()
            ? $account->current_period_ends_at
            : now();
        $wasSuspended = $account->status === 'suspended';
        $account->update([
            'current_period_ends_at' => $from->copy()->addMonths($periodMonths),
            'status' => 'active',
        ]);

        if ($wasSuspended && $account->hostingServer) {
            try {
                $plesk = app(PleskClient::class);
                $plesk->setServer($account->hostingServer);
                $plesk->unsuspendAccount($account->plesk_username);
            } catch (\Throwable $e) {
                Log::debug('AutoRenewPrepaidWithBalance: Plesk unsuspend skipped', ['account_id' => $account->id, 'message' => $e->getMessage()]);
            }
        }

        Log::info('AutoRenewPrepaidWithBalance: webspace renewed', ['account_id' => $account->id]);
    }

    private function renewTeamSpeakServers(Carbon $tomorrowStart, Carbon $tomorrowEnd): void
    {
        $accounts = TeamSpeakServerAccount::query()
            ->where('auto_renew_with_balance', true)
            ->where('status', 'active')
            ->whereBetween('current_period_ends_at', [$tomorrowStart, $tomorrowEnd])
            ->with(['user.brand', 'hostingPlan', 'hostingServer'])
            ->get();

        foreach ($accounts as $account) {
            $this->renewTeamSpeakServer($account);
        }
    }

    private function renewTeamSpeakServer(TeamSpeakServerAccount $account): void
    {
        $brand = $account->user?->brand ?? Brand::getDefault();
        $brandFeatures = $brand?->getFeaturesArray() ?? [];
        if (! ($brandFeatures['prepaid_balance'] ?? false)) {
            return;
        }

        $plan = $account->hostingPlan;
        if (! $plan || ! $plan->is_active || $plan->price === null || (float) $plan->price <= 0) {
            return;
        }

        $periodMonths = $this->getPeriodMonths($brandFeatures);
        $amount = (float) $plan->price * $periodMonths;

        $balance = CustomerBalance::where('user_id', $account->user_id)->first();
        $customerBalance = $balance ? (float) $balance->balance : 0.0;
        if ($customerBalance < $amount) {
            Log::info('AutoRenewPrepaidWithBalance: insufficient balance for TeamSpeak server', [
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'required' => $amount,
                'balance' => $customerBalance,
            ]);

            return;
        }

        try {
            app(BalancePaymentService::class)->pay(
                $account->user,
                $amount,
                'teamspeak_renewal',
                'TeamSpeak-Server Auto-Verlängerung: '.$account->name,
                ['description' => 'Auto-Verlängerung TeamSpeak-Server '.$account->name.' (ID '.$account->id.')']
            );
        } catch (InsufficientBalanceException $e) {
            Log::warning('AutoRenewPrepaidWithBalance: balance payment failed for TeamSpeak server', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $from = $account->current_period_ends_at && $account->current_period_ends_at->isFuture()
            ? $account->current_period_ends_at
            : now();
        $account->update([
            'current_period_ends_at' => $from->copy()->addMonths($periodMonths),
            'status' => 'active',
        ]);

        if ($account->hostingServer && $account->virtual_server_id) {
            try {
                $client = app(TeamSpeakClient::class);
                $client->setServer($account->hostingServer);
                $client->startVirtualServer($account->virtual_server_id);
            } catch (\Throwable $e) {
                Log::debug('AutoRenewPrepaidWithBalance: TeamSpeak start skipped', ['account_id' => $account->id, 'message' => $e->getMessage()]);
            }
        }

        Log::info('AutoRenewPrepaidWithBalance: TeamSpeak server renewed', ['account_id' => $account->id]);
    }
}
