<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\GameServerAccount;
use App\Models\ResellerDomain;
use App\Models\User;
use App\Models\WebspaceAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends ApiV1Controller
{
    /**
     * Public stats for landing page (no sensitive financial data).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $brand = $this->resolveBrand($request);
        $brandId = $brand?->id;

        $customersCount = User::query()
            ->when($brandId !== null, fn ($q) => $q->where('brand_id', $brandId))
            ->where(function ($q) {
                $q->whereHas('webspaceAccounts')
                    ->orWhereHas('gameServerAccounts')
                    ->orWhereHas('resellerDomains');
            })
            ->distinct()
            ->count('users.id');

        $webspaceAccountsCount = WebspaceAccount::query()
            ->when($brandId !== null, fn ($q) => $q->whereHas('hostingPlan', fn ($p) => $p->where('brand_id', $brandId)))
            ->count();

        $gameServerAccountsCount = GameServerAccount::query()
            ->when($brandId !== null, fn ($q) => $q->whereHas('hostingPlan', fn ($p) => $p->where('brand_id', $brandId)))
            ->count();

        $domainsCount = ResellerDomain::query()
            ->when($brandId !== null, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('brand_id', $brandId)))
            ->count();

        return response()->json([
            'data' => [
                'customers_count' => $customersCount,
                'webspace_accounts_count' => $webspaceAccountsCount,
                'game_server_accounts_count' => $gameServerAccountsCount,
                'domains_count' => $domainsCount,
            ],
        ]);
    }
}
