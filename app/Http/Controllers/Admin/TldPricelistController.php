<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncTldPricelistRequest;
use App\Http\Requests\Admin\UpdateTldPricelistBulkRequest;
use App\Http\Requests\Admin\UpdateTldSaleRegistrarBulkRequest;
use App\Http\Requests\Admin\UpdateTldSaleRegistrarRequest;
use App\Jobs\SyncTldPricelistJob;
use App\Models\Brand;
use App\Models\TldPricelist;
use App\Services\TldSaleRoutingService;
use App\Support\DomainRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TldPricelistController extends Controller
{
    public function __construct(
        protected TldSaleRoutingService $saleRoutingService,
    ) {}

    protected function currentBrand(Request $request): ?Brand
    {
        return $request->attributes->get('current_brand') ?? Brand::getDefault();
    }

    public function index(Request $request): Response
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            abort(404, 'Keine Marke zugeordnet.');
        }

        $listRaw = $request->input('list', DomainRegistrar::SKRIME);
        $list = is_string($listRaw) && DomainRegistrar::isValid($listRaw)
            ? $listRaw
            : DomainRegistrar::SKRIME;

        $query = TldPricelist::query()
            ->where('brand_id', $brand->id)
            ->where('price_source', $list)
            ->orderBy('tld');

        if ($request->filled('search')) {
            $search = strtolower(ltrim($request->string('search')->trim()->toString(), '.'));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('tld', $search)
                        ->orWhere('tld', 'like', '%.'.$search);
                });
            }
        }

        $paginator = $query->paginate(25)->withQueryString();

        $defaultSaleRegistrar = (string) ($brand->getFeaturesArray()['domain_sales_registrar'] ?? DomainRegistrar::SKRIME);

        $items = collect($paginator->items())->map(function (TldPricelist $row) {
            return [
                'id' => $row->id,
                'tld' => $row->tld,
                'price_source' => $row->price_source,
                'sale_registrar' => $row->price_source,
                'create_price' => (float) $row->create_price,
                'renew_price' => (float) $row->renew_price,
                'transfer_price' => (float) ($row->transfer_price ?? 0),
                'margin_type' => $row->margin_type ?? 'fixed',
                'margin_value' => (float) $row->margin_value,
                'margin_renew_value' => $row->margin_renew_value !== null ? (float) $row->margin_renew_value : null,
                'margin_transfer_value' => $row->margin_transfer_value !== null ? (float) $row->margin_transfer_value : null,
                'sale_price' => $this->salePriceFor($row, 'create'),
                'sale_price_renew' => $this->salePriceFor($row, 'renew'),
                'sale_price_transfer' => $this->salePriceFor($row, 'transfer'),
            ];
        })->values()->all();

        return Inertia::render('admin/domains/TldPricelist', [
            'items' => $items,
            'links' => $paginator->linkCollection()->toArray(),
            'filters' => [
                'search' => $request->input('search', ''),
                'list' => $list,
            ],
            'default_sale_registrar' => DomainRegistrar::isValid($defaultSaleRegistrar) ? $defaultSaleRegistrar : DomainRegistrar::SKRIME,
        ]);
    }

    protected function salePriceFor(TldPricelist $row, string $type): float
    {
        $purchasePrice = match ($type) {
            'renew' => (float) $row->renew_price,
            'transfer' => (float) ($row->transfer_price ?? 0),
            default => (float) $row->create_price,
        };

        $marginType = $row->margin_type ?? 'fixed';
        $marginValue = (float) match ($type) {
            'renew' => $row->margin_renew_value ?? $row->margin_value ?? 0,
            'transfer' => $row->margin_transfer_value ?? $row->margin_value ?? 0,
            default => $row->margin_value ?? 0,
        };

        if ($marginType === 'percent') {
            return round($purchasePrice * (1 + $marginValue / 100), 2);
        }

        return round($purchasePrice + $marginValue, 2);
    }

    public function sync(SyncTldPricelistRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.domains.tld-pricelist.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $priceSource = $request->validated('price_source');
        SyncTldPricelistJob::dispatch($brand->id, $priceSource);

        return $this->redirectToTldPricelistIndex($request, $priceSource)->with(
            'success',
            'Pricelist-Import in die Warteschlange gestellt. Seite in 1–2 Minuten neu laden. (Queue-Worker: php artisan queue:work)'
        );
    }

    public function updateSaleRegistrar(UpdateTldSaleRegistrarRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.domains.tld-pricelist.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $data = $request->validated();
        $this->saleRoutingService->setSaleRegistrar($brand, $data['tld'], $data['sale_registrar']);

        $list = $data['list'] ?? DomainRegistrar::SKRIME;

        return $this->redirectToTldPricelistIndex($request, $list)->with('success', 'Verkaufs-Registrar für die TLD wurde gespeichert.');
    }

    public function bulk(UpdateTldPricelistBulkRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.domains.tld-pricelist.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $tlds = $request->validated('tlds', []);
        $marginType = $request->validated('margin_type');
        $marginValue = (float) $request->validated('margin_value');
        $marginRenewRaw = $request->input('margin_renew_value');
        $marginTransferRaw = $request->input('margin_transfer_value');
        $marginRenewValue = ($marginRenewRaw !== '' && $marginRenewRaw !== null) ? (float) $marginRenewRaw : null;
        $marginTransferValue = ($marginTransferRaw !== '' && $marginTransferRaw !== null) ? (float) $marginTransferRaw : null;

        $priceSource = $request->validated('price_source');

        $count = 0;
        $tldList = ! empty($tlds) ? array_map('strtolower', $tlds) : TldPricelist::query()
            ->where('brand_id', $brand->id)
            ->where('price_source', $priceSource)
            ->pluck('tld')
            ->all();

        foreach ($tldList as $tld) {
            $query = TldPricelist::query()
                ->where('brand_id', $brand->id)
                ->where('tld', $tld)
                ->where('price_source', $priceSource);
            $data = [
                'margin_type' => $marginType,
                'margin_value' => $marginValue,
            ];
            if ($marginRenewValue !== null) {
                $data['margin_renew_value'] = $marginRenewValue;
            }
            if ($marginTransferValue !== null) {
                $data['margin_transfer_value'] = $marginTransferValue;
            }
            $count += $query->update($data);
        }

        return $this->redirectToTldPricelistIndex($request, $priceSource)->with('success', "Marge für {$count} TLD-Zeile(n) aktualisiert.");
    }

    public function bulkSaleRegistrar(UpdateTldSaleRegistrarBulkRequest $request): RedirectResponse
    {
        $brand = $this->currentBrand($request);
        if ($brand === null) {
            return redirect()->route('admin.domains.tld-pricelist.index')->with('error', 'Keine Marke zugeordnet.');
        }

        $saleRegistrar = $request->validated('sale_registrar');
        $searchRaw = $request->input('search', '');
        $search = is_string($searchRaw) ? strtolower(ltrim(trim($searchRaw), '.')) : '';

        $tlds = $request->validated('tlds', []);
        $tldList = ! empty($tlds)
            ? array_map(fn (string $tld) => strtolower(ltrim(trim($tld), '.')), $tlds)
            : TldPricelist::query()
                ->where('brand_id', $brand->id)
                ->where('price_source', $saleRegistrar)
                ->when($search !== '', function ($q) use ($search) {
                    $q->where('tld', $search)->orWhere('tld', 'like', '%.'.$search);
                })
                ->pluck('tld')
                ->all();

        $count = 0;
        foreach ($tldList as $tld) {
            if ($tld === '') {
                continue;
            }

            $this->saleRoutingService->setSaleRegistrar($brand, $tld, $saleRegistrar);
            $count++;
        }

        return $this->redirectToTldPricelistIndex($request, $saleRegistrar)->with(
            'success',
            "\"Verkauf über\" für {$count} TLD(s) auf ".($saleRegistrar === DomainRegistrar::REALTIME_REGISTER ? 'Realtime Register' : 'Skrime').' gesetzt.'
        );
    }

    protected function redirectToTldPricelistIndex(Request $request, string $list): RedirectResponse
    {
        $list = DomainRegistrar::isValid($list) ? $list : DomainRegistrar::SKRIME;
        $params = ['list' => $list];
        if ($request->filled('search')) {
            $params['search'] = $request->string('search')->trim()->toString();
        }

        return redirect()->route('admin.domains.tld-pricelist.index', $params);
    }
}
