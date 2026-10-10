<?php
namespace App\Livewire\Pricing;

use App\Models\Movement\RateLog;
use App\Models\Pricing\PricingRule;
use App\Models\Stock\ItemCategory;
use App\Services\PricingService;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pricing rules (8 Oct change list, 11.2): making charge, additional charge, discount and
 * hallmarking charge, for a product, a category, a price range, a metal or everything, by carat.
 * The most specific rule wins. The price range is measured on the metal value only.
 */
class PricingRules extends Component
{
    #[Url(except: 'making')]
    public string $kind = 'making'; // making | additional | discount | hallmark | categories

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $scope = 'category';
    public string $productCode = '';
    public string $category = '';
    public string $metal = '';
    public string $purity = '';
    public $minValue = '';
    public $maxValue = '';
    public string $name = '';
    public string $calc = 'percentage';
    public $value = '';
    public ?string $validFrom = null;
    public ?string $validTo = null;

    // By category: the making charge set for each subcategory (any carat).
    /** @var array<int, array{calc: string, value: string}> */
    public array $categoryMaking = [];

    public function mount(): void
    {
        $this->loadCategoryMaking();
    }

    private function loadCategoryMaking(): void
    {
        $this->categoryMaking = [];
        foreach (ItemCategory::active()->get() as $c) {
            $rule = $this->categoryRule($c);
            $this->categoryMaking[$c->id] = ['calc' => $rule?->calc ?? 'percentage', 'value' => $rule ? (string) (float) $rule->value : ''];
        }
    }

    private function categoryRule(ItemCategory $c): ?PricingRule
    {
        return PricingRule::where('kind', 'making')->where('scope', 'category')->where('category', $c->name)->where('metal', $c->metal)
            ->whereNull('purity')->latest('id')->first();
    }

    public function setKind(string $kind): void
    {
        if (in_array($kind, ['making', 'additional', 'discount', 'hallmark', 'categories'], true)) {
            $this->kind = $kind;
            $this->showForm = false;
            $this->loadCategoryMaking();
        }
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'productCode', 'category', 'metal', 'purity', 'minValue', 'maxValue', 'name', 'value', 'validFrom', 'validTo']);
        $this->scope = PricingRule::SCOPES[$this->kind][1] ?? 'category';
        $this->calc = 'percentage';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $r = PricingRule::with('item')->findOrFail($id);
        $this->kind = $r->kind;
        $this->editingId = $r->id;
        $this->scope = $r->scope;
        $this->productCode = (string) ($r->item?->label ?? '');
        $this->category = (string) $r->category;
        $this->metal = (string) $r->metal;
        $this->purity = (string) $r->purity;
        $this->minValue = $r->min_value !== null ? (string) (float) $r->min_value : '';
        $this->maxValue = $r->max_value !== null ? (string) (float) $r->max_value : '';
        $this->name = (string) $r->name;
        $this->calc = $r->calc;
        $this->value = (string) (float) $r->value;
        $this->validFrom = $r->valid_from?->toDateString();
        $this->validTo = $r->valid_to?->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'scope' => ['required', Rule::in(PricingRule::SCOPES[$this->kind] ?? [])],
            'productCode' => ['nullable', 'required_if:scope,product'],
            'category' => ['nullable', 'required_if:scope,category', 'string', 'max:50'],
            'metal' => ['nullable', 'required_if:scope,metal', Rule::in(array_keys(RateLog::CARATS))],
            'purity' => ['nullable', 'string', 'max:10'],
            'minValue' => ['nullable', 'numeric', 'min:0'],
            'maxValue' => ['nullable', 'numeric', 'min:0', 'gt:minValue'],
            'name' => ['nullable', 'required_if:kind,additional', 'string', 'max:60'],
            'calc' => ['required', 'in:percentage,per_gram,per_piece'],
            'value' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'validFrom' => ['nullable', 'date'],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
        ], [
            'productCode.required_if' => 'Enter the piece\'s HUID or code.',
            'category.required_if' => 'Choose the category.',
            'metal.required_if' => 'Choose the metal.',
            'maxValue.gt' => 'The top of the range must be above the bottom.',
            'name.required_if' => 'Say what the charge is for.',
        ], ['minValue' => 'bottom of the range', 'maxValue' => 'top of the range']);

        if ($this->scope === 'price_range' && $this->minValue === '' && $this->maxValue === '') {
            $this->addError('minValue', 'Enter at least one end of the price range.');

            return;
        }

        $item = null;
        if ($this->scope === 'product') {
            $item = StockLookup::item($this->productCode);
            if (! $item) {
                $this->addError('productCode', 'No piece has that code.');

                return;
            }
        }

        $data = [
            'kind' => $this->kind,
            'scope' => $this->scope,
            'item_id' => $item?->id,
            'category' => $this->scope === 'category' ? $this->category : null,
            'metal' => $this->metal ?: null,
            'purity' => $this->purity ?: null,
            'min_value' => $this->scope === 'price_range' && $this->minValue !== '' ? $this->minValue : null,
            'max_value' => $this->scope === 'price_range' && $this->maxValue !== '' ? $this->maxValue : null,
            'name' => $this->name ?: null,
            'calc' => $this->calc,
            'value' => $this->value,
            'valid_from' => $this->validFrom ?: null,
            'valid_to' => $this->validTo ?: null,
        ];

        $this->editingId ? PricingRule::findOrFail($this->editingId)->update($data) : PricingRule::create($data + ['active' => true, 'created_by' => Auth::id()]);
        app(PricingService::class)->forgetRules();

        $this->showForm = false;
        $this->dispatch('toast', message: 'Rule saved.', type: 'success');
    }

    public function toggle(int $id): void
    {
        $r = PricingRule::findOrFail($id);
        $r->update(['active' => ! $r->active]);
        app(PricingService::class)->forgetRules();
    }

    public function remove(int $id): void
    {
        PricingRule::findOrFail($id)->delete();
        app(PricingService::class)->forgetRules();
        $this->dispatch('toast', message: 'Rule removed. It stays in the audit log.', type: 'success');
    }

    // ---- By category: set or change the making charge of a subcategory in one place.

    public function saveCategoryMaking(int $categoryId): void
    {
        $c = ItemCategory::findOrFail($categoryId);
        $row = $this->categoryMaking[$categoryId] ?? ['calc' => 'percentage', 'value' => ''];

        if ($row['value'] === '' || ! is_numeric($row['value']) || (float) $row['value'] < 0) {
            $this->addError("categoryMaking.{$categoryId}.value", 'Enter a number.');

            return;
        }
        if (! in_array($row['calc'], ['percentage', 'per_gram', 'per_piece'], true)) {
            return;
        }

        $data = ['kind' => 'making', 'scope' => 'category', 'category' => $c->name, 'metal' => $c->metal, 'purity' => null, 'calc' => $row['calc'], 'value' => $row['value'], 'active' => true];
        $existing = $this->categoryRule($c);
        $existing ? $existing->update($data) : PricingRule::create($data + ['created_by' => Auth::id()]);
        app(PricingService::class)->forgetRules();
        $this->resetErrorBag();
        $this->dispatch('toast', message: "{$c->name} making charge saved.", type: 'success');
    }

    public function render()
    {
        $rules = $this->kind === 'categories' ? collect()
            : PricingRule::with('item')->where('kind', $this->kind)->orderByDesc('active')->orderBy('scope')->latest('id')->get();

        return view('livewire.pricing.pricing-rules', [
            'rules' => $rules,
            'kinds' => PricingRule::KINDS,
            'scopes' => PricingRule::SCOPES[$this->kind] ?? [],
            'scopeLabels' => PricingRule::SCOPE_LABELS,
            'calcLabels' => PricingRule::CALC_LABELS,
            'carats' => collect(RateLog::CARATS)->flatten()->unique()->values(),
            'metals' => array_keys(RateLog::CARATS),
            'categoryNames' => ItemCategory::active()->orderBy('metal')->orderBy('name')->get(['id', 'metal', 'name']),
            'tree' => ItemCategory::active()->orderBy('metal')->orderBy('sort_order')->orderBy('name')->get()->groupBy('metal'),
        ])->layout('components.layouts.app', ['title' => 'Pricing rules · Radharani Jewellery']);
    }
}
