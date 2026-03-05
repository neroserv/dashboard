<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\CheckDomainAvailabilityRequest;
use App\Models\TldPricelist;
use App\Services\DomainPricingService;
use App\Services\SkrimeApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends ApiV1Controller
{
    private const TLDs_PER_PAGE = 15;

    /**
     * Priority TLDs shown first (in this order), then all others alphabetically.
     *
     * @var list<string>
     */
    private const PRIORITY_TLDS = ['de', 'net', 'com', 'eu', 'at', 'ch'];

    /**
     * List TLDs with prices (create, renew, transfer, sale_price for create).
     * Paginated (15 per page). Priority TLDs (.de, .net, .com, .eu, .at, .ch) appear first.
     */
    public function tlds(Request $request, DomainPricingService $pricing): JsonResponse
    {
        $cases = [];
        foreach (self::PRIORITY_TLDS as $i => $tld) {
            $cases[] = "WHEN '".addslashes($tld)."' THEN ".($i + 1);
        }
        $orderCase = 'CASE tld '.implode(' ', $cases).' ELSE '.(count(self::PRIORITY_TLDS) + 1).' END';
        $query = TldPricelist::query()
            ->orderByRaw("{$orderCase} ASC")
            ->orderBy('tld');

        $paginator = $query->paginate(self::TLDs_PER_PAGE)->withQueryString();

        $data = $paginator->through(function ($row) use ($pricing) {
            $createPrice = (float) $row->create_price;
            $salePrice = $createPrice > 0
                ? $pricing->calculateSalePrice($createPrice, $row->tld)
                : $pricing->getSalePrice($row->tld, 'create');

            return [
                'tld' => $row->tld,
                'create_price' => round((float) $row->create_price, 2),
                'renew_price' => round((float) $row->renew_price, 2),
                'transfer_price' => round((float) $row->transfer_price, 2),
                'restore_price' => round((float) $row->restore_price, 2),
                'sale_price' => round($salePrice, 2),
            ];
        });

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Check domain availability for given base name and optional TLDs.
     */
    public function checkAvailability(CheckDomainAvailabilityRequest $request, SkrimeApiService $skrime, DomainPricingService $pricing): JsonResponse
    {
        $baseName = strtolower(trim($request->input('domain')));
        $tlds = $request->input('tlds', ['de', 'com', 'net', 'io']);
        $results = [];

        foreach ($tlds as $tld) {
            $tld = strtolower(ltrim((string) $tld, '.'));
            $domain = $baseName.'.'.$tld;
            try {
                $check = $skrime->checkAvailability($domain);
                $pricingInfo = $pricing->getPricingForTld($tld, 'create');
                $results[] = [
                    'domain' => $domain,
                    'available' => $check['available'],
                    'premium' => $check['premium'] ?? false,
                    'sale_price' => $pricingInfo['sale_price'],
                    'purchase_price' => $pricingInfo['purchase_price'],
                ];
            } catch (\Throwable) {
                $results[] = [
                    'domain' => $domain,
                    'available' => false,
                    'premium' => false,
                    'sale_price' => 0,
                    'purchase_price' => 0,
                    'error' => true,
                ];
            }
        }

        return response()->json(['data' => ['results' => $results]]);
    }
}
