<?php
namespace App\Livewire\Stock;

use App\Models\Movement\RateLog;
use App\Models\Stock\Item;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Product view (8 Oct change list, 4.5): total pieces and weight per metal, then broken
 * down by category, carat (purity) or price range, in whatever order is chosen.
 * Price range is the metal value only (net weight x today's rate), the same basis the
 * pricing bands use, so it never involves making charges.
 */
class ProductSummary extends Component
{
    public const DIMENSIONS = ['category' => 'Category', 'carat' => 'Carat', 'price' => 'Price range'];

    // Metal value bands, in rupees.
    private const BANDS = [[0, 10000, 'Under ₹10,000'], [10000, 25000, '₹10,000 to ₹25,000'], [25000, 50000, '₹25,000 to ₹50,000'],
        [50000, 100000, '₹50,000 to ₹1 lakh'], [100000, null, 'Above ₹1 lakh']];

    #[Url(except: '')]
    public string $metal = '';

    #[Url(except: 'category')]
    public string $level1 = 'category';

    #[Url(except: 'carat')]
    public string $level2 = 'carat';

    #[Url(except: '')]
    public string $level3 = '';

    public function updatedLevel1(): void
    {
        $this->dedupe();
    }

    public function updatedLevel2(): void
    {
        $this->dedupe();
    }

    public function updatedLevel3(): void
    {
        $this->dedupe();
    }

    // A dimension can only be used once.
    private function dedupe(): void
    {
        $seen = [];
        foreach (['level1', 'level2', 'level3'] as $p) {
            if ($this->$p !== '' && in_array($this->$p, $seen, true)) {
                $this->$p = '';
            }
            if ($this->$p !== '') {
                $seen[] = $this->$p;
            }
        }
        if ($this->level1 === '') {
            [$this->level1, $this->level2, $this->level3] = [$this->level2, $this->level3, ''];
        }
    }

    private function key(string $dim, Item $item, array &$rates): string
    {
        return match ($dim) {
            'category' => $item->category ?: 'No category',
            'carat' => $item->purity ?: 'No purity',
            'price' => $this->band(((float) ($item->net_weight ?: $item->weight)) * ($rates[($item->metal ?? 'gold') . '|' . strtoupper(trim((string) $item->purity))] ??= RateLog::rateFor($item->metal ?? 'gold', $item->purity))),
        };
    }

    private function band(float $value): string
    {
        foreach (self::BANDS as [$min, $max, $label]) {
            if ($value >= $min && ($max === null || $value < $max)) {
                return $label;
            }
        }

        return self::BANDS[0][2];
    }

    /** Nested groups: each node has label, pieces, weight, children. */
    private function tree(Collection $items, array $dims, array &$rates): Collection
    {
        if (! $dims) {
            return collect();
        }
        $dim = array_shift($dims);
        $order = $dim === 'price' ? array_column(self::BANDS, 2) : null;

        return $items->groupBy(fn ($i) => $this->key($dim, $i, $rates))
            ->map(fn ($rows, $label) => [
                'label' => $label,
                'pieces' => $rows->count(),
                'weight' => $rows->sum('weight'),
                'children' => $this->tree($rows, $dims, $rates),
            ])
            ->sortBy(fn ($n, $label) => $order ? array_search($label, $order) : mb_strtolower($label))->values();
    }

    private function flatten(Collection $nodes, int $depth = 0): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $out[] = ['depth' => $depth] + collect($n)->except('children')->all();
            array_push($out, ...$this->flatten($n['children'], $depth + 1));
        }

        return $out;
    }

    public function render()
    {
        $rates = []; // metal|carat => rate, filled as pieces are grouped

        $all = Item::whereNotIn('status', ['sold'])->get(['id', 'metal', 'category', 'purity', 'weight', 'net_weight']);
        $metals = $all->groupBy(fn ($i) => $i->metal ?? 'gold');
        $active = $this->metal !== '' && $metals->has($this->metal) ? $this->metal : '';
        $dims = array_values(array_filter([$this->level1, $this->level2, $this->level3]));

        $cards = collect(['gold', 'silver', 'platinum', 'titanium'])->filter(fn ($m) => $metals->has($m))->map(fn ($m) => [
            'metal' => $m, 'pieces' => $metals[$m]->count(), 'weight' => $metals[$m]->sum('weight'),
        ])->values();

        $scope = $active ? $metals[$active] : $all;

        return view('livewire.stock.product-summary', [
            'cards' => $cards,
            'rows' => $this->flatten($this->tree($scope, $dims, $rates)),
            'total' => ['pieces' => $scope->count(), 'weight' => $scope->sum('weight')],
            'active' => $active,
            'dimensions' => self::DIMENSIONS,
        ])->layout('components.layouts.app', ['title' => 'Product view · Radharani Jewellery ERP']);
    }
}
