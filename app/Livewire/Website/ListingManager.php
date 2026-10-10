<?php
namespace App\Livewire\Website;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Stock\Item;
use App\Services\PricingService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Website > Listings: every piece in stock and whether the public website
 * shows it. A piece is live when it's ticked for the website, has a web
 * name, sits in a website category and is physically in stock; reserved,
 * dispatched and sold pieces drop off the site on their own. Editing opens
 * the shared Add/Edit Item form on its Website tab.
 */
class ListingManager extends Component
{
    use WithDataTable;

    #[Url(as: 'state', except: '')]
    public string $state = '';

    #[Url(as: 'cat', except: '')]
    public string $stockCategory = '';

    protected function sortableColumns(): array
    {
        return [
            'listed' => 'listed_at',
            'name' => 'web_name',
            'category' => 'category',
            'weight' => 'weight',
            'added' => 'id',
        ];
    }

    protected function defaultSort(): array
    {
        return ['added', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['state', 'stockCategory'];
    }

    // Quick switch from the list. Turning a piece on needs its website
    // details first, so an incomplete piece opens its Website tab instead.
    public function toggle(int $id): void
    {
        $item = Item::findOrFail($id);

        if (! $item->show_on_website && ! $item->web_name) {
            $this->dispatch('open-item-form', id: $item->id, tab: 'website');

            return;
        }

        $item->update(['show_on_website' => ! $item->show_on_website]);
        $this->dispatch('toast', message: $item->show_on_website
            ? "{$item->web_name} is on the website."
            : "{$item->web_name} is off the website.", type: 'success');
    }

    #[On('item-saved')]
    public function refreshList(): void {}

    public function render()
    {
        // A piece can be live when its subcategory in the owner's tree is on.
        $activeIds = \App\Models\Stock\ItemCategory::active()->pluck('id')->all() ?: [0];

        $query = Item::query()
            ->withCount('images')
            ->with(['images' => fn ($q) => $q->limit(1), 'storefrontCollection'])
            ->where('status', '!=', 'sold')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('web_name', 'like', "%{$this->search}%")
                ->orWhere('huid_code', 'like', "%{$this->search}%")
                ->orWhere('internal_code', 'like', "%{$this->search}%")
                ->orWhere('category', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")))
            ->when($this->stockCategory, fn ($q) => $q->where('category', $this->stockCategory))
            ->when($this->state === 'live', fn ($q) => $q->onWebsite()->whereIn('category_id', $activeIds))
            ->when($this->state === 'waiting', fn ($q) => $q->where('show_on_website', true)->where(fn ($q) => $q
                ->where('status', '!=', 'in_stock')->orWhereNull('web_name')
                ->orWhereNotIn('category_id', $activeIds)->orWhereNull('category_id')))
            ->when($this->state === 'off', fn ($q) => $q->where('show_on_website', false));

        $items = $this->applySorting($query)->paginate($this->perPageValue());
        $pricing = app(PricingService::class);

        $rows = $items->getCollection()->map(function (Item $item) use ($pricing) {
            $webCategory = $item->categoryRow?->is_active ? $item->categoryRow : null;
            $reason = match (true) {
                ! $item->show_on_website => null,
                ! $item->web_name => 'Needs a website name',
                ! $webCategory => "“{$item->category}” is missing or switched off in Categories",
                $item->status !== 'in_stock' => 'Back on the site once it is in stock',
                default => null,
            };

            return [
                'item' => $item,
                'webCategory' => $webCategory,
                'live' => $item->show_on_website && ! $reason,
                'reason' => $reason,
                'price' => $pricing->priceFor($item),
            ];
        });

        $counts = [
            'live' => Item::onWebsite()->whereIn('category_id', $activeIds)->count(),
            'photos' => Item::onWebsite()->doesntHave('images')->count(),
        ];

        return view('livewire.website.listing-manager', [
            'items' => $items,
            'rows' => $rows,
            'counts' => $counts,
            'stockCategories' => Item::where('status', '!=', 'sold')->distinct()->orderBy('category')->pluck('category'),
        ])->layout('components.layouts.app', ['title' => 'Website listings — Radharani Jewellery']);
    }
}
