<?php
namespace App\Livewire\Sales;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Sales\Sale;
use Livewire\Attributes\Url;
use Livewire\Component;

class SalesHistory extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = ''; // '' | verified | reserved

    protected function sortableColumns(): array
    {
        return [
            'invoice' => 'id',
            'created' => 'created_at',
            'total' => 'total',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function render()
    {
        $query = Sale::with('customer')->withSum('payments', 'amount')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhere('id', ltrim($this->search, '#'))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%"))))
            ->when($this->status === 'verified', fn ($q) => $q->where('confirmed_by_accountant', true))
            ->when($this->status === 'reserved', fn ($q) => $q->where('confirmed_by_accountant', false))
            ->when($this->status === 'balance', fn ($q) => $q->whereRaw('total - COALESCE((select sum(amount) from sale_payments where sale_payments.sale_id = sales.id), 0) > 0.005'));

        return view('livewire.sales.sales-history', [
            'sales' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'total' => Sale::count(),
                'verified' => Sale::where('confirmed_by_accountant', true)->count(),
                'reserved' => Sale::where('confirmed_by_accountant', false)->count(),
                'withBalance' => Sale::whereRaw('total - COALESCE((select sum(amount) from sale_payments where sale_payments.sale_id = sales.id), 0) > 0.005')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Sales History — Radharani Jewellery']);
    }
}
