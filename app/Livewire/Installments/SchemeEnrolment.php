<?php
namespace App\Livewire\Installments;

use App\Models\Customer\Customer;
use App\Models\Customer\InstallmentScheme;
use Livewire\Component;

class SchemeEnrolment extends Component
{
    public string $customerSearch = '';
    public ?int $customerId = null;
    public string $monthlyAmount = '';
    public string $startDate = '';

    public function mount()
    {
        $this->startDate = now()->toDateString();
    }

    public function getCustomerObjectProperty()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function pickCustomer(int $id)
    {
        $this->customerId = $id;
        $this->customerSearch = '';
    }

    public function enrol()
    {
        $this->validate([
            'customerId' => 'required|exists:customers,id',
            'monthlyAmount' => 'required|numeric|min:1',
            'startDate' => 'required|date',
        ]);

        $scheme = InstallmentScheme::create([
            'customer_id' => $this->customerId,
            'monthly_amount' => $this->monthlyAmount,
            'months_paid' => 0,
            'start_date' => $this->startDate,
            'status' => 'active',
        ]);

        \App\Support\MessageTemplates::queue('scheme_welcome', $scheme->customer, \App\Support\MessageTemplates::schemeWelcome($scheme), 'installment_scheme', $scheme->id);

        $this->dispatch('toast', message: "{$scheme->customer->name} enrolled in the installment scheme.", type: 'success');
        $this->reset(['customerId', 'monthlyAmount']);
        $this->startDate = now()->toDateString();
    }

    public function render()
    {
        return view('livewire.installments.scheme-enrolment', [
            'customerResults' => $this->customerSearch
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
            'recentEnrolments' => InstallmentScheme::with('customer')->latest('id')->limit(5)->get(),
        ])->layout('components.layouts.app', ['title' => 'Scheme Enrolment — Radharani Jewellery ERP']);
    }
}
