<?php
namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer\Customer;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class CustomerManager extends Component
{
    use WithDataTable;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public string $email = '';
    public string $gstin = '';
    public string $status = 'past_customer';

    public bool $showPasswordFor = false;
    public ?int $passwordCustomerId = null;
    public string $newPassword = '';

    protected function sortableColumns(): array
    {
        return [
            'name' => 'name',
            'created' => 'id',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:15|unique:customers,phone'.($this->editingId ? ','.$this->editingId : ''),
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:100',
            'gstin' => 'nullable|string|max:15',
            'status' => 'required|in:past_customer,order_given,order_pending',
        ];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->cancel();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $c = Customer::findOrFail($id);
        $this->editingId = $c->id;
        $this->name = $c->name;
        $this->phone = $c->phone;
        $this->address = (string) $c->address;
        $this->email = (string) $c->email;
        $this->gstin = (string) $c->gstin;
        $this->status = $c->status;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            'gstin' => $this->gstin,
            'status' => $this->status,
        ];

        // Referral codes are opt-in (8 Oct change list, 15.1): none is issued here.

        Customer::updateOrCreate(['id' => $this->editingId], $data);

        $message = $this->editingId ? 'Customer updated.' : 'Customer added.';
        $this->showForm = false;
        $this->cancel();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'address', 'email', 'gstin']);
        $this->status = 'past_customer';
    }

    // Staff-assigned only — there is no customer self-signup flow.
    // This is the counter-side half of "ask at the counter to set up
    // portal access" shown on the portal login screen.
    public function openPasswordForm(int $id): void
    {
        $this->resetValidation();
        $this->passwordCustomerId = $id;
        $this->newPassword = '';
        $this->showPasswordFor = true;
    }

    public function setPassword(): void
    {
        $this->validate(['newPassword' => 'required|min:8']);

        Customer::whereKey($this->passwordCustomerId)->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->showPasswordFor = false;
        $this->dispatch('toast', message: 'Portal password set. Share it with the customer directly.', type: 'success');
    }

    public function render()
    {
        $query = Customer::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%"));

        return view('livewire.admin.customer-manager', [
            'customers' => $this->applySorting($query)->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Customers — Radharani Jewellery']);
    }
}
