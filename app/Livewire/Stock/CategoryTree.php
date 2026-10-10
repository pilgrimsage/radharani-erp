<?php
namespace App\Livewire\Stock;

use App\Models\Stock\ItemCategory;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The owner-managed category tree (8 Oct change list, 5.1): Metal, then
 * Subcategory. Renaming carries to every piece in it; categories with pieces
 * are switched off, not deleted.
 */
class CategoryTree extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $metal = 'gold';
    public string $name = '';
    public int $sort_order = 0;

    protected function rules(): array
    {
        return [
            'metal' => ['required', Rule::in(array_keys(ItemCategory::METALS))],
            'name' => ['required', 'string', 'max:50', Rule::unique('item_categories', 'name')->where('metal', $this->metal)->ignore($this->editingId)],
            'sort_order' => ['integer', 'min:0', 'max:999'],
        ];
    }

    protected $validationAttributes = ['name' => 'subcategory name'];

    public function create(string $metal = 'gold'): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name']);
        $this->metal = array_key_exists($metal, ItemCategory::METALS) ? $metal : 'gold';
        $this->sort_order = (int) ItemCategory::where('metal', $this->metal)->max('sort_order') + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $c = ItemCategory::findOrFail($id);
        $this->editingId = $c->id;
        $this->metal = $c->metal;
        $this->name = $c->name;
        $this->sort_order = $c->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $data = $this->validate();

        if ($this->editingId) {
            $category = ItemCategory::findOrFail($this->editingId);
            // Pieces belong to one metal's subcategory; moving it under another metal would orphan them.
            if ($category->metal !== $data['metal'] && $category->items()->exists()) {
                $this->addError('metal', 'This subcategory has pieces, so it cannot move to another metal.');

                return;
            }
            $renamed = $category->name !== $data['name'];
            $category->update($data);
            // Per piece (not a mass update) so each rename lands in the piece's history.
            if ($renamed) {
                $category->items()->each(fn ($item) => $item->update(['category' => $data['name']]));
            }
        } else {
            ItemCategory::create($data);
        }

        $this->showForm = false;
        $this->dispatch('toast', message: "{$data['name']} saved.", type: 'success');
    }

    public function toggle(int $id): void
    {
        $c = ItemCategory::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
        $this->dispatch('toast', message: $c->name . ($c->is_active ? ' is on.' : ' is switched off. New pieces cannot use it.'), type: 'success');
    }

    public function render()
    {
        $all = ItemCategory::withCount('items')->orderBy('sort_order')->orderBy('name')->get()->groupBy('metal');

        return view('livewire.stock.category-tree', [
            'tree' => collect(ItemCategory::METALS)->map(fn ($label, $metal) => ['metal' => $metal, 'label' => $label, 'rows' => $all->get($metal, collect())]),
            'metals' => ItemCategory::METALS,
        ])->layout('components.layouts.app', ['title' => 'Categories · Radharani Jewellery ERP']);
    }
}
