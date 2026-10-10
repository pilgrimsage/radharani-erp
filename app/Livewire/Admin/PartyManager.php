<?php
namespace App\Livewire\Admin;

use App\Models\Purchase\Vendor;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Karigars and hallmarking centres (8 Oct change list, 18.1): the named parties their ledgers
 * need. General vendors and suppliers are gone.
 */
class PartyManager extends Component
{
    public const TYPES = ['karigar' => 'Karigar', 'hallmark_center' => 'Hallmarking centre'];

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $type = 'karigar';
    public string $phone = '';
    public string $address = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('vendors', 'name')->where('type', $this->type)->ignore($this->editingId)],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'phone' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function create(string $type = 'karigar'): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'name', 'phone', 'address']);
        $this->type = array_key_exists($type, self::TYPES) ? $type : 'karigar';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $v = Vendor::whereIn('type', array_keys(self::TYPES))->findOrFail($id);
        $this->editingId = $v->id;
        $this->name = $v->name;
        $this->type = $v->type;
        $this->phone = (string) $v->phone;
        $this->address = (string) $v->address;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $data = $this->validate();
        $data['phone'] = $data['phone'] ?: null;
        $data['address'] = $data['address'] ?: null;

        $this->editingId ? Vendor::findOrFail($this->editingId)->update($data) : Vendor::create($data + ['balance' => 0]);
        $this->showForm = false;
        $this->dispatch('toast', message: "{$data['name']} saved.", type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.party-manager', [
            'parties' => Vendor::whereIn('type', array_keys(self::TYPES))->orderBy('type')->orderBy('name')->get(),
            'types' => self::TYPES,
        ])->layout('components.layouts.app', ['title' => 'Karigars & centres · Radharani Jewellery']);
    }
}
