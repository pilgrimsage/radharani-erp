<?php
namespace App\Http\Controllers;

use App\Services\StorefrontCatalog;

/**
 * Public website. Plain controllers (no Livewire): each page ships its data
 * as window.RJ_DATA and the storefront scripts render and animate the rest.
 */
class StorefrontController extends Controller
{
    public function __construct(private StorefrontCatalog $catalog) {}

    public function home()
    {
        $rj = $this->catalog->payload();

        return view('storefront.home', [
            'page' => 'home',
            'rj' => $rj,
            'categories' => $rj['categories'],
            'collections' => $rj['collections'],
            'budgets' => config('storefront.budgets'),
            'config' => $rj['config'],
            'bill' => $this->exampleBill($rj['rates']['gold']),
        ]);
    }

    public function shop()
    {
        return view('storefront.shop', [
            'page' => 'shop',
            'rj' => $this->catalog->payload(),
            'title' => 'All jewellery | Radharani Jewellery Works',
        ]);
    }

    public function product(string $slug)
    {
        $rj = $this->catalog->payload($slug);
        $piece = collect($rj['products'])->firstWhere('id', $slug);

        // Sold, reserved or unlisted: the page still renders its friendly
        // "no longer online" state, but with a real 404 for search engines.
        return response()->view('storefront.product', [
            'page' => 'product',
            'rj' => $rj,
            'title' => $piece
                ? $piece['name'].' | '.($rj['purities'][$piece['purity']]['label'] ?? $piece['purity']).' | Radharani Jewellery Works'
                : 'Piece not found | Radharani Jewellery Works',
            'description' => $piece['description'] ?? null,
            'canonical' => $piece ? route('storefront.product', $slug) : null,
            'ogImage' => $piece['images'][0] ?? null,
        ], $piece ? 200 : 404);
    }

    // "How we price" section: a 10 g, 22K chain worked out at this morning's
    // gold rate, 12% making, 3% GST. Illustrative only, so it's computed here
    // rather than through PricingService (there's no real item behind it).
    private function exampleBill(float $rate): array
    {
        $weight = 10;
        $value = round($rate * $weight);
        $making = round($value * 0.12);

        return compact('rate', 'weight', 'value', 'making') + ['total' => $value + $making];
    }
}
