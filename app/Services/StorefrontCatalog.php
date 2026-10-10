<?php
namespace App\Services;

use App\Models\Movement\RateLog;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontCollection;
use App\Models\Storefront\StorefrontSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds window.RJ_DATA for the public storefront: the exact shape the
 * site's scripts (public/storefront/js) were designed around, filled from
 * the ERP. Prices are never stored or computed in the browser: each piece
 * carries its live PricingService breakdown. No GST is added (8 Oct change
 * list, 5.2). Categories come from the owner's Metal > Subcategory tree.
 */
class StorefrontCatalog
{
    private ?Collection $pieces = null;

    public function __construct(private PricingService $pricing) {}

    public function payload(?string $currentId = null): array
    {
        $pieces = $this->pieces();
        $categoryCounts = $pieces->countBy('category');
        $collectionCounts = $pieces->whereNotNull('collection')->countBy('collection');

        return [
            'urls' => [
                'home' => route('home'),
                'shop' => route('storefront.catalog'),
                'product' => route('storefront.product', '__ID__'),
                'asset' => rtrim(asset('storefront'), '/').'/',
                'signIn' => route('sign-in'),
                // Customer portal: login, or their dashboard once signed in.
                'account' => auth('customer')->check() ? route('portal.dashboard') : route('portal.login'),
            ],
            'customer' => auth('customer')->check() ? ['name' => auth('customer')->user()->name] : null,
            'config' => $this->config(),
            'rates' => $this->rates(),
            'purities' => $this->purities($pieces),
            'metals' => $pieces->pluck('metal')->unique()->values(),
            'categories' => $this->categories()->filter(fn ($c) => $categoryCounts->has($c['slug']))->values(),
            'menu' => $this->menu($categoryCounts),
            'collections' => $this->collections()->filter(fn ($c) => $collectionCounts->has($c['slug']))->values(),
            'budgets' => collect(config('storefront.budgets'))->map(fn ($b) => [
                'slug' => $b['slug'], 'label' => $b['label'], 'min' => $b['min'], 'max' => $b['max'],
            ]),
            'occasions' => $this->pairs(config('storefront.occasions')),
            'audiences' => $this->pairs(config('storefront.audiences')),
            'sizes' => collect(config('storefront.size_types'))->map(fn ($label) => ['label' => $label]),
            'products' => $pieces->values(),
            'currentId' => $currentId,
        ];
    }

    // Every piece the public may see, shaped for the site's scripts.
    public function pieces(): Collection
    {
        if ($this->pieces) {
            return $this->pieces;
        }

        $newSince = now()->subDays((int) config('storefront.new_days', 30));

        return $this->pieces = Item::onWebsite()
            ->with(['images', 'storefrontCollection', 'categoryRow'])
            ->orderByDesc('listed_at')->orderByDesc('id')
            ->get()
            ->map(function (Item $item) use ($newSince) {
                $category = $item->categoryRow;
                if (! $category || ! $category->is_active) {
                    return null; // its subcategory is missing or switched off
                }

                return $this->piece($item, $category, $newSince);
            })
            ->filter()
            // A metal whose rate is set to 0 is not shown on the display.
            ->filter(fn ($p) => ($p['pr']['rate'] ?? 0) > 0)
            ->values();
    }

    private function piece(Item $item, ItemCategory $category, Carbon $newSince): array
    {
        $b = $this->pricing->breakdown($item);
        $collection = $item->storefrontCollection?->is_active ? $item->storefrontCollection : null;

        return [
            'id' => $item->slug,
            'code' => $item->label,
            'hallmarked' => (bool) $item->huid_code,
            'name' => $item->web_name,
            'category' => $category->slug,
            'metal' => $b['metal'],
            'purity' => (string) $item->purity,
            'grossWt' => (float) $item->weight,
            'netWt' => (float) ($item->net_weight ?: $item->weight),
            'stones' => $item->stones ?: 'None',
            'stoneValue' => (float) $item->stone_value,
            'collection' => $collection?->slug,
            'for' => array_values($item->audiences ?? []),
            'occasions' => array_values($item->occasions ?? []),
            'isNew' => $item->listed_at && $item->listed_at->gte($newSince),
            'isBestseller' => (bool) $item->is_bestseller,
            'sizeType' => $item->size_label ? ($item->size_type ?: null) : null,
            'size' => $item->size_label,
            'images' => $item->images->map->url->filter()->values(),
            'dims' => $item->dimensions,
            'description' => $item->web_description ?: (string) $item->description,
            'listedAt' => $item->listed_at?->timestamp ?? 0,
            'price' => round($b['total']),
            'pr' => [
                'rate' => $b['rate'],
                'weight' => $b['weight'],
                'metal' => $b['metal_value'],
                'making' => $b['making'],
                'makingLabel' => $this->makingLabel($item),
                'stones' => $b['stone_value'],
                'huid' => $b['huid_charge'],
                'discount' => $b['discount'],
                'sub' => $b['total'],
                'total' => round($b['total'], 2),
            ],
        ];
    }

    private function makingLabel(Item $item): string
    {
        $v = (float) $item->making_value;

        return match ($item->making_type) {
            'percentage' => rtrim(rtrim(number_format($v, 2), '0'), '.').'%',
            'flat_per_gram' => '₹'.number_format($v).'/g',
            default => 'flat',
        };
    }

    // Website categories are the subcategories in the owner's tree. A website category row
    // with the same name (Website > Categories) can still supply a picture and a line of text.
    public function categories(): Collection
    {
        $extras = StorefrontCategory::active()->get()->keyBy(fn ($c) => mb_strtolower($c->name));
        $cats = ItemCategory::active()->orderBy('sort_order')->orderBy('name')->get();
        $dupes = $cats->countBy(fn ($c) => mb_strtolower($c->name));

        return $cats->map(function (ItemCategory $c) use ($extras, $dupes) {
            $x = $extras->get(mb_strtolower($c->name));
            // Same name under two metals: tell them apart ("Gold Earrings", "Silver Earrings").
            $name = $dupes[mb_strtolower($c->name)] > 1 ? ItemCategory::METALS[$c->metal] . ' ' . $c->name : $c->name;

            return [
                'slug' => $c->slug,
                'name' => $name,
                'metal' => $c->metal,
                'img' => $x?->image_url,
                'blurb' => $x?->blurb,
                'inMenu' => (bool) $x?->in_menu,
            ];
        });
    }

    // Site menu: metals at the top, their subcategories beneath (only ones with live pieces).
    private function menu(Collection $categoryCounts): Collection
    {
        return $this->categories()->filter(fn ($c) => $categoryCounts->has($c['slug']))
            ->groupBy('metal')
            ->map(fn ($rows, $metal) => [
                'metal' => $metal,
                'label' => ItemCategory::METALS[$metal] ?? ucfirst($metal),
                'items' => $rows->map(fn ($c) => ['slug' => $c['slug'], 'name' => str_replace((ItemCategory::METALS[$metal] ?? '') . ' ', '', $c['name'])])->values(),
            ])->sortBy(fn ($m) => array_search($m['metal'], array_keys(ItemCategory::METALS)))->values();
    }

    public function collections(): Collection
    {
        return StorefrontCollection::active()->get()->map(fn ($c) => [
            'slug' => $c->slug,
            'name' => $c->name,
            'img' => $c->image_url,
            'blurb' => $c->blurb,
        ]);
    }

    public function config(): array
    {
        $s = StorefrontSetting::values();
        $digits = preg_replace('/\D/', '', (string) ($s['phone'] ?? ''));

        return [
            'whatsapp' => preg_replace('/\D/', '', (string) ($s['whatsapp'] ?? '')),
            'phone' => $s['phone'] ?? '',
            'tel' => '+'.(strlen($digits) === 10 ? '91'.$digits : $digits),
            'address' => $s['address'] ?? '',
            'hours' => $s['hours'] ?? '',
            'parking' => $s['parking'] ?? '',
            'maps' => 'https://maps.google.com/?q='.urlencode($s['maps_query'] ?? ''),
            'mapsEmbed' => 'https://www.google.com/maps?q='.urlencode($s['maps_query'] ?? '').'&output=embed',
            'instagram' => $s['instagram'] ?? '',
            'facebook' => $s['facebook'] ?? '',
            'youtube' => $s['youtube'] ?? '',
        ];
    }

    // Today's rate per gram for each metal, as entered in Pricing > Rates.
    public function rates(): array
    {
        $out = ['updated' => ''];
        foreach (['gold', 'silver', 'platinum'] as $metal) {
            $log = RateLog::latestFor($metal);
            $out[$metal] = (float) ($log?->rate ?? 0);
            if ($metal === 'gold' && $log?->created_at) {
                $out['updated'] = $log->created_at->isToday()
                    ? $log->created_at->format('g:i A')
                    : $log->created_at->format('j M, g:i A');
            }
        }

        return $out;
    }

    private function purities(Collection $pieces): array
    {
        $order = ['gold' => 1, 'platinum' => 2, 'silver' => 3, 'titanium' => 4];

        return $pieces->unique('purity')
            ->sortBy(fn ($p) => sprintf('%d-%s', $order[$p['metal']] ?? 9, str_pad(99 - (int) $p['purity'], 3, '0', STR_PAD_LEFT)))
            ->mapWithKeys(fn ($p) => [$p['purity'] => [
                'label' => self::purityLabel($p['metal'], $p['purity']),
                'metal' => $p['metal'],
            ]])
            ->all();
    }

    public static function purityLabel(string $metal, string $purity): string
    {
        return match ($metal) {
            'gold' => "{$purity} gold",
            'silver' => "{$purity} silver",
            'platinum' => "Platinum {$purity}",
            default => ucfirst($metal)." {$purity}",
        };
    }

    private function pairs(array $map): array
    {
        return collect($map)->map(fn ($name, $slug) => ['slug' => $slug, 'name' => $name])->values()->all();
    }
}
