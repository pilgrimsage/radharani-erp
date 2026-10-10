<?php
namespace App\Livewire\Installments;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer\InstallmentScheme;
use App\Models\Orders\Order;
use App\Models\Stock\Item;
use App\Support\MessageTemplates;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class SchemeList extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $statusFilter = '';

    protected function sortableColumns(): array
    {
        return [
            'amount' => 'monthly_amount',
            'started' => 'start_date',
            'status' => 'status',
        ];
    }

    protected function defaultSort(): array
    {
        return ['started', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['statusFilter'];
    }

    // ---- a scheme that was not kept up (the member is told, by a copy-and-send message)
    public function markDefaulted(int $id): void
    {
        $scheme = InstallmentScheme::with('customer')->where('status', 'active')->findOrFail($id);
        $scheme->update(['status' => 'defaulted']);
        MessageTemplates::queue('scheme_default', $scheme->customer, MessageTemplates::schemeDefault($scheme), 'installment_scheme', $scheme->id);
        $this->dispatch('toast', message: "{$scheme->customer->name}'s scheme is marked defaulted.", type: 'success');
    }

    // ---- when a scheme matures: create an order, make a sale, or reserve a product in stock
    public ?int $outcomeId = null;
    public bool $showOutcome = false;
    public string $reserveSearch = '';
    public ?int $reserveItemId = null;

    public function openOutcome(int $id): void
    {
        InstallmentScheme::where('status', 'active')->findOrFail($id);
        $this->outcomeId = $id;
        $this->reset(['reserveSearch', 'reserveItemId']);
        $this->resetValidation();
        $this->showOutcome = true;
    }

    public function chooseReserve(int $itemId): void
    {
        $this->reserveItemId = $itemId;
        $this->reserveSearch = '';
    }

    // The advance paid into the scheme goes on a stock piece as an order, so nobody else sells it.
    public function reserveProduct(): void
    {
        $this->validate(['reserveItemId' => 'required|exists:items,id'], ['reserveItemId.required' => 'Choose the product to reserve.']);
        $scheme = InstallmentScheme::with('customer')->where('status', 'active')->findOrFail($this->outcomeId);
        $item = Item::where('status', 'in_stock')->findOrFail($this->reserveItemId);

        DB::transaction(function () use ($scheme, $item) {
            $order = Order::create([
                'customer_id' => $scheme->customer_id,
                'product_description' => trim($item->category . ' ' . $item->label) . ' (reserved from the monthly scheme)',
                'category' => $item->category,
                'metal' => $item->metal,
                'estimated_weight' => $item->weight,
                'estimated_value' => 0,
                'advance_amount' => $scheme->paid_in,
                'in_stock_item_id' => $item->id,
                'out_of_stock' => false,
                'sourcing' => 'stock',
                'status' => 'placed',
                'created_by' => auth()->id(),
            ]);
            $this->complete($scheme, 'reserve', $order->id);
        });

        $this->showOutcome = false;
        $this->dispatch('toast', message: "{$item->label} is reserved for {$scheme->customer->name}.", type: 'success');
    }

    /** Recorded once an outcome is chosen; the scheme is then complete. */
    public static function complete(InstallmentScheme $scheme, string $outcome, ?int $ref): void
    {
        $scheme->update(['status' => 'completed', 'maturity_outcome' => $outcome, 'outcome_ref' => $ref, 'outcome_at' => now()]);
        MessageTemplates::queue('scheme_completed', $scheme->customer, MessageTemplates::schemeCompleted($scheme), 'installment_scheme', $scheme->id);
    }

    public function render()
    {
        $query = InstallmentScheme::with('customer')->withSum('payments', 'amount')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")));

        return view('livewire.installments.scheme-list', [
            'outcomeScheme' => $this->outcomeId ? InstallmentScheme::with('customer')->find($this->outcomeId) : null,
            'reserveResults' => $this->reserveSearch !== '' ? Item::where('status', 'in_stock')->searchAnything($this->reserveSearch)->limit(8)->get() : collect(),
            'reserveItem' => $this->reserveItemId ? Item::find($this->reserveItemId) : null,
            'schemes' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'active' => InstallmentScheme::where('status', 'active')->count(),
                'completed' => InstallmentScheme::where('status', 'completed')->count(),
                'defaulted' => InstallmentScheme::where('status', 'defaulted')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Installment Schemes — Radharani Jewellery ERP']);
    }
}
