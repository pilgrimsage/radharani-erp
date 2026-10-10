<?php
namespace App\Livewire\Admin;

use App\Models\Location;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Owner-managed list of in-store locations (8 Oct change list, 3.1). */
class LocationManager extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'counter';
    public int $sort_order = 0;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('locations', 'name')->ignore($this->editingId)],
            'type' => ['required', Rule::in(array_keys(Location::TYPES))],
            'sort_order' => ['integer', 'min:0', 'max:999'],
        ];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name']);
        $this->type = 'counter';
        $this->sort_order = (int) Location::max('sort_order') + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $l = Location::findOrFail($id);
        $this->editingId = $l->id;
        $this->name = $l->name;
        $this->type = $l->type;
        $this->sort_order = $l->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $data = $this->validate();

        // There is exactly one vault; it's where everything starts.
        if ($data['type'] === 'vault' && Location::where('type', 'vault')->where('id', '!=', $this->editingId)->exists()) {
            $this->addError('type', 'There is already a vault.');

            return;
        }

        $this->editingId ? Location::findOrFail($this->editingId)->update($data) : Location::create($data);
        $this->showForm = false;
        $this->dispatch('toast', message: "{$data['name']} saved.", type: 'success');
    }

    public function toggle(int $id): void
    {
        $l = Location::findOrFail($id);
        if ($l->type === 'vault') {
            $this->dispatch('toast', message: 'The vault cannot be switched off.', type: 'error');

            return;
        }
        $l->update(['is_active' => ! $l->is_active]);
        $this->dispatch('toast', message: $l->name . ($l->is_active ? ' is on.' : ' is switched off.'), type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.location-manager', [
            'locations' => Location::withCount('movements')->orderBy('sort_order')->orderBy('id')->get(),
            'types' => Location::TYPES,
        ])->layout('components.layouts.app', ['title' => 'Locations · Radharani Jewellery']);
    }
}
