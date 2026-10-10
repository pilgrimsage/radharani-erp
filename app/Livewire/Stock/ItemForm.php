<?php
namespace App\Livewire\Stock;

use App\Models\Purchase\PurchaseItem;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Models\Stock\ItemImage;
use App\Models\Stock\Packet;
use App\Models\Storefront\StorefrontCollection;
use App\Services\PhotoCompressionService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Add / Edit Item form, rendered as a modal. Embedded on Inventory, Item
 * Detail and Website > Listings, opened from any of them with:
 *     Livewire.dispatch('open-item-form')                        new piece
 *     Livewire.dispatch('open-item-form', { id: 12 })            edit piece 12
 *     Livewire.dispatch('open-item-form', { id: 12, tab: 'website' })  straight to its website listing
 *     Livewire.dispatch('open-item-form', { purchaseItemId: 4 }) tag a raw-material purchase line
 * Emits 'item-saved' so the host page refreshes.
 *
 * The Website tab (name, photos, collection, occasions...) is only shown to,
 * and only saved for, staff with website.manage.
 */
class ItemForm extends Component
{
    use WithFileUploads;

    public const MAX_PHOTOS = 8;
    public const METALS = ['gold' => 'Gold', 'silver' => 'Silver', 'platinum' => 'Platinum', 'titanium' => 'Titanium'];

    public const PURITIES = [
        'gold' => ['24K', '22K', '18K', '14K'],
        'silver' => ['99.9', '92.5'],
        'platinum' => ['950', '900'],
        'titanium' => ['Grade 5', 'Grade 2'],
    ];

    public const STATUSES = [
        'in_stock' => 'In stock',
        'dispatched' => 'Dispatched',
        'pending_review' => 'Pending review',
        'reserved' => 'Reserved',
        'sold' => 'Sold',
    ];

    public bool $showForm = false;
    public ?int $editingId = null;
    public ?int $packet_id = null;
    public string $huid_code = '';
    public string $metal = 'gold';
    public string $category = '';
    public ?int $categoryId = null;
    public string $purity = '';
    public $weight = '';
    public string $description = '';
    public string $hsn_code = '';
    public string $making_type = 'flat_per_piece';
    public $making_value = '';
    public $net_weight = '';
    public string $stones = '';
    public $stone_value = '';

    public string $tab = 'piece'; // piece | website

    // Website listing
    public bool $show_on_website = false;
    public string $web_name = '';
    public string $web_description = '';
    public ?int $storefront_collection_id = null;
    public array $audiences = [];
    public array $occasions = [];
    public string $dimensions = '';
    public string $size_type = '';
    public string $size_label = '';
    public bool $is_bestseller = false;
    public ?string $slug = null;
    public array $photos = [];          // existing: [['id' => 3, 'url' => '...'], ...] in display order
    public array $removedPhotoIds = [];
    public array $newPhotos = [];       // uploads, saved (compressed) on Save

    // Pairing (earrings / bangles): none | new (create the partner piece now) | existing (link to a piece already entered) | keep
    public string $pairMode = 'none';
    public $partnerWeight = '';
    public string $partnerHuid = '';
    public string $pairSearch = '';
    public ?int $pairWithId = null;
    public ?int $currentPairId = null;

    // Set when tagging a pending raw-material purchase line into a new
    // item — links the created item back to purchase_items.id and flips
    // that line's tag_pending off once saved.
    public ?int $taggingPurchaseItemId = null;

    protected function rules(): array
    {
        return [
            'packet_id' => ['nullable', 'exists:packets,id'],
            'huid_code' => ['nullable', 'string', 'max:20', Rule::unique('items', 'huid_code')->ignore($this->editingId)],
            'metal' => ['required', Rule::in(array_keys(self::METALS))],
            'categoryId' => ['required', Rule::exists('item_categories', 'id')->where('metal', $this->metal)],
            'purity' => ['required', 'string', 'max:10'],
            'weight' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'description' => ['nullable', 'string', 'max:100'],
            'hsn_code' => ['nullable', 'string', 'max:10'],
            'making_type' => ['required', Rule::in(['percentage', 'flat_per_piece', 'flat_per_gram'])],
            'making_value' => ['required', 'numeric', 'min:0'],
            'pairMode' => ['required', Rule::in(['none', 'new', 'existing', 'keep'])],
            'partnerWeight' => ['required_if:pairMode,new', 'nullable', 'numeric', 'min:0.001'],
            'partnerHuid' => ['nullable', 'string', 'max:20', 'different:huid_code', Rule::unique('items', 'huid_code')],
            'pairWithId' => ['required_if:pairMode,existing', 'nullable', 'exists:items,id'],
            'net_weight' => ['nullable', 'numeric', 'min:0.001', 'lte:weight'],
            'stones' => ['nullable', 'string', 'max:120'],
            'stone_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ] + ($this->canManageWebsite() ? [
            'web_name' => [Rule::requiredIf($this->show_on_website), 'nullable', 'string', 'max:120'],
            'web_description' => ['nullable', 'string', 'max:2000'],
            'storefront_collection_id' => ['nullable', 'exists:storefront_collections,id'],
            'audiences' => ['array'],
            'audiences.*' => [Rule::in(array_keys(config('storefront.audiences')))],
            'occasions' => ['array'],
            'occasions.*' => [Rule::in(array_keys(config('storefront.occasions')))],
            'dimensions' => ['nullable', 'string', 'max:120'],
            'size_type' => ['nullable', Rule::in(array_keys(config('storefront.size_types')))],
            'size_label' => ['nullable', 'string', 'max:20'],
            'newPhotos' => ['array', 'max:'.max(0, self::MAX_PHOTOS - count($this->photos))],
            'newPhotos.*' => ['image', 'max:8192'],
        ] : []);
    }

    public function canManageWebsite(): bool
    {
        return (bool) auth()->user()?->can('website.manage');
    }

    protected $validationAttributes = [
        'huid_code' => 'HUID',
        'packet_id' => 'packet',
        'making_value' => 'making charge',
        'partnerWeight' => 'partner piece weight',
        'partnerHuid' => 'partner HUID',
        'pairWithId' => 'piece to pair with',
        'net_weight' => 'net weight',
        'stone_value' => 'stone value',
        'web_name' => 'website name',
        'newPhotos' => 'photos',
        'newPhotos.*' => 'photo',
    ];

    protected $messages = [
        'partnerWeight.required_if' => 'Enter the weight of the second piece. Pairs are weighed separately.',
        'pairWithId.required_if' => 'Pick the piece this one pairs with.',
        'net_weight.lte' => 'Net weight can\'t be more than the gross weight.',
        'web_name.required' => 'Give the piece a name for the website, e.g. Meenakari Jhumka.',
        'newPhotos.max' => 'Up to '.self::MAX_PHOTOS.' photos per piece.',
    ];

    #[On('open-item-form')]
    public function open(?int $id = null, ?int $purchaseItemId = null, ?string $tab = null): void
    {
        $this->resetForm();

        if ($id) {
            $this->loadItem($id);
        } elseif ($purchaseItemId) {
            $this->loadPurchaseLine($purchaseItemId);
        }

        $this->tab = $tab === 'website' && $this->canManageWebsite() ? 'website' : 'piece';
        $this->showForm = true;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset([
            'editingId', 'packet_id', 'huid_code', 'category', 'categoryId', 'purity', 'weight', 'description', 'hsn_code',
            'making_value', 'pairMode', 'partnerWeight', 'partnerHuid', 'pairSearch', 'pairWithId', 'currentPairId',
            'taggingPurchaseItemId', 'net_weight', 'stones', 'stone_value', 'tab',
            'show_on_website', 'web_name', 'web_description', 'storefront_collection_id', 'audiences', 'occasions',
            'dimensions', 'size_type', 'size_label', 'is_bestseller', 'slug', 'photos', 'removedPhotoIds', 'newPhotos',
        ]);
        $this->metal = 'gold';
        $this->making_type = 'flat_per_piece';
    }

    private function loadItem(int $id): void
    {
        $item = Item::findOrFail($id);
        $this->editingId = $item->id;
        $this->packet_id = $item->packet_id;
        $this->huid_code = (string) $item->huid_code;
        $this->metal = $item->metal ?? 'gold';
        $this->category = $item->category;
        $this->categoryId = $item->category_id ?? ItemCategory::forMetal($this->metal)->where('name', $item->category)->value('id');
        $this->purity = $item->purity;
        $this->weight = (string) (float) $item->weight;
        $this->description = (string) $item->description;
        $this->hsn_code = (string) $item->hsn_code;
        $this->making_type = in_array($item->making_type, ['percentage', 'flat_per_piece', 'flat_per_gram'], true) ? $item->making_type : 'flat_per_piece';
        $this->making_value = (string) (float) $item->making_value;
        $this->currentPairId = $item->pair_group_id ? $item->pairedWith()->value('id') : null;
        $this->pairMode = $this->currentPairId ? 'keep' : 'none';
        $this->net_weight = $item->net_weight ? (string) (float) $item->net_weight : '';
        $this->stones = (string) $item->stones;
        $this->stone_value = (float) $item->stone_value ? (string) (float) $item->stone_value : '';

        $this->show_on_website = (bool) $item->show_on_website;
        $this->web_name = (string) $item->web_name;
        $this->web_description = (string) $item->web_description;
        $this->storefront_collection_id = $item->storefront_collection_id;
        $this->audiences = $item->audiences ?? [];
        $this->occasions = $item->occasions ?? [];
        $this->dimensions = (string) $item->dimensions;
        $this->size_type = (string) $item->size_type;
        $this->size_label = (string) $item->size_label;
        $this->is_bestseller = (bool) $item->is_bestseller;
        $this->slug = $item->slug;
        $this->photos = $item->images->map(fn (ItemImage $i) => ['id' => $i->id, 'url' => $i->url])->all();
    }

    // Photo order: the first photo is the one on the product card.
    public function movePhoto(int $index, int $direction): void
    {
        $to = $index + $direction;
        if (! isset($this->photos[$index], $this->photos[$to])) {
            return;
        }
        [$this->photos[$index], $this->photos[$to]] = [$this->photos[$to], $this->photos[$index]];
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            $this->removedPhotoIds[] = $this->photos[$index]['id'];
            array_splice($this->photos, $index, 1);
        }
    }

    public function removeNewPhoto(int $index): void
    {
        array_splice($this->newPhotos, $index, 1);
    }

    // Pre-fills the form from a pending raw-material purchase line so staff
    // don't retype the description/category/metal/purity.
    private function loadPurchaseLine(int $purchaseItemId): void
    {
        $line = PurchaseItem::where('tag_pending', true)->findOrFail($purchaseItemId);

        $this->taggingPurchaseItemId = $line->id;
        $this->metal = $line->metal ?? 'gold';
        $this->category = (string) $line->category;
        $this->categoryId = ItemCategory::forMetal($this->metal)->where('name', $line->category)->value('id');
        $this->purity = (string) $line->purity;
        $this->weight = $line->weight ? (string) (float) $line->weight : '';
        $this->description = (string) $line->description;
    }

    // Keep the plain name in step: pricing previews and the pairing search filter on it.
    public function updatedCategoryId(): void
    {
        $this->category = (string) ItemCategory::find($this->categoryId)?->name;
    }

    public function updatedMetal(): void
    {
        // A subcategory belongs to one metal, so the old pick no longer applies.
        if ($this->categoryId && ! ItemCategory::forMetal($this->metal)->whereKey($this->categoryId)->exists()) {
            $this->categoryId = null;
            $this->category = '';
        }
        // Purity formats differ by metal (22K vs 92.5), so a stale value would be wrong.
        if ($this->purity && ! in_array($this->purity, self::PURITIES[$this->metal] ?? [], true)) {
            $this->purity = '';
        }
    }

    public function updatedPairMode(): void
    {
        if ($this->pairMode === 'new' && $this->partnerWeight === '') {
            $this->partnerWeight = $this->weight;
        }
    }

    public function save(): void
    {
        $this->huid_code = strtoupper(trim($this->huid_code));
        $this->partnerHuid = strtoupper(trim($this->partnerHuid));
        $this->packet_id = $this->packet_id ?: null;
        $this->storefront_collection_id = $this->storefront_collection_id ?: null;

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Jump to the tab holding the first problem, so it isn't hidden.
            $websiteFields = ['web_name', 'web_description', 'storefront_collection_id', 'audiences', 'occasions', 'dimensions', 'size_type', 'size_label', 'newPhotos'];
            $first = explode('.', array_key_first($e->errors()))[0];
            $this->tab = in_array($first, $websiteFields, true) ? 'website' : 'piece';
            throw $e;
        }

        if ($this->newPhotos && $this->canManageWebsite() && ! PhotoCompressionService::available()) {
            $this->tab = 'website';
            $this->addError('newPhotos', 'This server can\'t process photos yet (PHP\'s GD extension is off). Remove the new photos to save, or ask for GD to be enabled.');

            return;
        }

        $data = [
            'packet_id' => $this->packet_id,
            'huid_code' => $this->huid_code ?: null,
            'metal' => $this->metal,
            'category' => ItemCategory::findOrFail($this->categoryId)->name,
            'category_id' => $this->categoryId,
            'purity' => trim($this->purity),
            'weight' => $this->weight,
            'description' => $this->description ?: null,
            'hsn_code' => $this->hsn_code ?: null,
            'making_type' => $this->making_type,
            'making_value' => $this->making_value,
            'net_weight' => $this->net_weight !== '' && $this->net_weight !== null ? $this->net_weight : null,
            'stones' => trim($this->stones) ?: null,
            'stone_value' => $this->stone_value !== '' && $this->stone_value !== null ? $this->stone_value : 0,
        ];

        $websiteData = $this->canManageWebsite() ? [
            'show_on_website' => $this->show_on_website,
            'web_name' => trim($this->web_name) ?: null,
            'web_description' => trim($this->web_description) ?: null,
            'storefront_collection_id' => $this->storefront_collection_id,
            'audiences' => array_values($this->audiences),
            'occasions' => array_values($this->occasions),
            'dimensions' => trim($this->dimensions) ?: null,
            'size_type' => $this->size_type ?: null,
            'size_label' => trim($this->size_label) ?: null,
            'is_bestseller' => $this->is_bestseller,
        ] : [];

        $item = DB::transaction(function () use ($data, $websiteData) {
            if ($this->editingId) {
                $item = Item::findOrFail($this->editingId);
                // A piece that lost its HUID still needs a readable code.
                if (! $data['huid_code'] && ! $item->internal_code) {
                    $data['internal_code'] = Item::generateInternalCode();
                }
                $item->update($data + $websiteData);
            } else {
                if ($this->taggingPurchaseItemId) {
                    $data['source_purchase_item_id'] = $this->taggingPurchaseItemId;
                }
                // No HUID -> auto-generate a unique internal code from Item's curated non-ambiguous charset.
                if (! $data['huid_code']) {
                    $data['internal_code'] = Item::generateInternalCode();
                }
                $item = Item::create($data + $websiteData + ['status' => 'in_stock']);
            }

            if ($websiteData) {
                $this->savePhotos($item);
            }

            if ($this->taggingPurchaseItemId) {
                PurchaseItem::where('id', $this->taggingPurchaseItemId)->update([
                    'tag_pending' => false,
                    'item_id' => $item->id,
                ]);
            }

            $this->applyPairing($item, $data);

            return $item;
        });

        $wasEditing = (bool) $this->editingId;
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('item-saved', id: $item->id);
        $this->dispatch('toast', message: $wasEditing ? "{$item->label} updated." : "{$item->label} added to stock.", type: 'success');
    }

    // Catalogue photos: removed ones go (row and file), new uploads are
    // compressed to WebP through PhotoCompressionService, then the order
    // on screen becomes sort_order (first = the photo on the product card).
    private function savePhotos(Item $item): void
    {
        if ($this->removedPhotoIds) {
            $item->images()->whereKey($this->removedPhotoIds)->get()->each(function (ItemImage $image) {
                if (! preg_match('#^https?://#', $image->path)) {
                    Storage::disk('public')->delete($image->path);
                }
                $image->delete();
            });
        }

        $order = collect($this->photos)->pluck('id');
        foreach ($this->newPhotos as $upload) {
            $image = ItemImage::create([
                'item_id' => $item->id,
                'path' => app(PhotoCompressionService::class)->store($upload, 'items'),
                'created_by' => auth()->id(),
            ]);
            $order->push($image->id);
        }

        foreach ($order->values() as $position => $id) {
            ItemImage::whereKey($id)->where('item_id', $item->id)->update(['sort_order' => $position]);
        }
    }

    // Pairs share pair_group_id; each physical piece keeps its own row and weight.
    private function applyPairing(Item $item, array $data): void
    {
        if ($this->pairMode === 'keep') {
            return;
        }

        // Pairing changed on an edit: detach from the old partner first.
        if ($this->editingId && $this->currentPairId) {
            Item::whereKey($this->currentPairId)->first()?->update(['pair_group_id' => null]);
            $item->update(['pair_group_id' => null]);
        }

        if ($this->pairMode === 'new') {
            $item->update(['pair_group_id' => $item->id]);
            $partner = $data;
            $partner['weight'] = $this->partnerWeight;
            $partner['huid_code'] = $this->partnerHuid ?: null;
            $partner['internal_code'] = $this->partnerHuid ? null : Item::generateInternalCode();
            $partner['pair_group_id'] = $item->id;
            unset($partner['source_purchase_item_id']);
            Item::create($partner + ['status' => 'in_stock']);
        }

        if ($this->pairMode === 'existing' && $this->pairWithId && $this->pairWithId !== $item->id) {
            $other = Item::findOrFail($this->pairWithId);
            $group = $other->pair_group_id ?: min($other->id, $item->id);
            $other->update(['pair_group_id' => $group]);
            $item->update(['pair_group_id' => $group]);
        }
    }

    public function render()
    {
        return view('livewire.stock.item-form', [
            'categories' => $this->showForm ? ItemCategory::active()->forMetal($this->metal)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : collect(),
            'packetsByBox' => $this->showForm
                ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box')
                : collect(),
            'pairCandidates' => $this->showForm && $this->pairMode === 'existing'
                ? Item::whereNull('pair_group_id')
                    ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                    ->when($this->pairSearch, fn ($q) => $q->where(fn ($q) => $q
                        ->where('huid_code', 'like', "%{$this->pairSearch}%")
                        ->orWhere('internal_code', 'like', "%{$this->pairSearch}%")
                        ->orWhere('category', 'like', "%{$this->pairSearch}%")),
                        fn ($q) => $q->when($this->category, fn ($q) => $q->where('category', $this->category)))
                    ->where('status', '!=', 'sold')
                    ->orderByDesc('id')->limit(25)->get()
                : collect(),
            'currentPair' => $this->currentPairId ? Item::find($this->currentPairId) : null,
            'estimate' => $this->showForm ? $this->estimate() : null,
            'collections' => $this->showForm && $this->tab === 'website' ? StorefrontCollection::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']) : collect(),
            'webCategory' => $this->showForm && $this->tab === 'website' && $this->categoryId ? ItemCategory::active()->find($this->categoryId) : null,
        ]);
    }

    // Live price preview using today's rate. Nothing is stored.
    private function estimate(): ?array
    {
        if (! is_numeric($this->weight) || $this->weight <= 0 || ! is_numeric($this->making_value)) {
            return null;
        }

        $draft = new Item([
            'metal' => $this->metal,
            'purity' => $this->purity,
            'weight' => $this->weight,
            'category' => $this->category,
            'huid_code' => $this->huid_code ?: null,
            'making_type' => $this->making_type,
            'making_value' => $this->making_value,
            'packet_id' => $this->packet_id,
            'net_weight' => is_numeric($this->net_weight) ? $this->net_weight : null,
            'stone_value' => is_numeric($this->stone_value) ? $this->stone_value : 0,
        ]);
        if ($this->editingId) {
            $draft->id = $this->editingId;
        }

        return app(PricingService::class)->breakdown($draft);
    }
}
