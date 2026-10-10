<?php
namespace App\Services;

use App\Models\Movement\RateLog;
use App\Models\Pricing\GstRate;
use App\Models\Stock\Item;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontCollection;
use App\Models\Storefront\StorefrontSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds window.RJ_DATA for the public storefront: the exact shape the
 * site's scripts (public/storefront/js) were designed around, filled from
 * the ERP. Prices are never stored or computed in the browser: each piece
 * carries its live PricingService breakdown plus GST at its category's
 * rate (the same GST rule Sales > New Sale applies).
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

        $categories = StorefrontCategory::lookup();
        $gst = GstRate::pluck('rate_percent', 'category')->mapWithKeys(fn ($r, $c) => [mb_strtolower($c) => (float) $r]);
        $newSince = now()->subDays((int) config('storefront.new_days', 30));

        return $this->pieces = Item::onWebsite()
            ->with(['images', 'storefrontCollection'])
            ->orderByDesc('listed_at')->orderByDesc('id')
            ->get()
            ->map(function (Item $item) use ($categories, $gst, $newSince) {
                $category = $categories[mb_strtolower(trim($item->category))] ?? null;
                if (! $category) {
                    return null; // its stock category isn't on the website yet
                }

                return $this->piece($item, $category, $gst[mb_strtolower($item->category)] ?? 3.0, $newSince);
            })
            ->filter()
            // A metal whose rate is set to 0 is not shown on the display.
            ->filter(fn ($p) => ($p['pr']['rate'] ?? 0) > 0)
            ->values();
    }

    private function piece(Item $item, StorefrontCategory $category, float $gstPct, Carbon $newSince): array
    {
        $b = $this->pricing->breakdown($item);
        $gst = round($b['total'] * $gstPct / 100, 2);
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
            'price' => round($b['total'] + $gst),
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
                'gstPct' => $gstPct,
                'gst' => $gst,
                'total' => round($b['total'] + $gst, 2),
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

    public function categories(): Collection
    {
        return StorefrontCategory::active()->get()->map(fn ($c) => [
            'slug' => $c->slug,
            'name' => $c->name,
            'img' => $c->image_url,
            'blurb' => $c->blurb,
            'inMenu' => $c->in_menu,
        ]);
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
