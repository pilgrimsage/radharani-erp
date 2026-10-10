<?php
namespace App\Livewire\Admin;

use App\Models\Customer\Customer;
use App\Models\Orders\Order;
use Livewire\Component;

/**
 * Customer Detail (staff-facing) — combined view: purchase history,
 * current custom orders, exchange balance, instalment
 * scheme status.
 */
class CustomerDetail extends Component
{
    public Customer $customer;
    public string $tab = 'purchases';

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
    }

    public function setTab(string $tab)
    {
        $this->tab = $tab;
    }

    public function render()
    {
        return view('livewire.admin.customer-detail', [
            'sales' => $this->customer->sales()->with('items')->withSum('payments', 'amount')->orderByDesc('id')->get(),
            'ledger' => $this->tab === 'ledger' ? app(\App\Services\LedgerService::class)->forCustomer($this->customer) : null,
            'orders' => Order::where('customer_id', $this->customer->id)->orderByDesc('id')->get(),
            'installmentSchemes' => $this->customer->installmentSchemes()->with('payments')->get(),
        ])->layout('components.layouts.app', ['title' => $this->customer->name.' — Radharani Jewellery']);
    }
}
