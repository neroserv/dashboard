<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['productable', 'brand:id,key,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $products->through(function (Product $product) {
            $product->productable_name = $product->productable?->name ?? '-';
            $product->edit_url = $product->productable_type === \App\Models\Template::class
                ? route('admin.templates.edit', ['template' => $product->productable_id])
                : ($product->productable_type === \App\Models\HostingPlan::class
                    ? route('admin.hosting-plans.edit', ['hosting_plan' => $product->productable_id])
                    : null);
        });

        return Inertia::render('admin/products/Index', [
            'products' => $products,
        ]);
    }
}
