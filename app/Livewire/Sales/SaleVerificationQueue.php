<?php
namespace App\Livewire\Sales;

use App\Livewire\Concerns\WithDataTable;
use App\Support\MessageTemplates;
use Illuminate\Validation\Rule;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sale Verification Queue (admin only). Verifying confirms the sale, takes the original Tally
 * bill number the admin types in (it replaces the holding reference, 8 Oct change list, 12.5),
 * flips the pieces from reserved to sold and queues the customer's confirmation message.
 * invoice_number is set once, here, together with confirmed_by_accountant: the narrow, documented
 * exception to rule 1. A sale with a balance can still be verified.
 */
class SaleVerificationQueue extends Component
{
    use WithDataTable;

    protected function sortableColumns(): array
    {
        return [
            'invoice' => 'id',
            'total' => 'total',
            'created' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    public ?int $verifyingId = null;
    public bool $showVerify = false;
    public string $tallyNumber = '';

    public function startVerify(int $saleId): void
    {
        abort_unless(Auth::user()?->can('sale.approve'), 403);
        $this->verifyingId = Sale::where('confirmed_by_accountant', false)->findOrFail($saleId)->id;
        $this->tallyNumber = '';
        $this->resetValidation();
        $this->showVerify = true;
    }

    public function verify(): void
    {
        abort_unless(Auth::user()?->can('sale.approve'), 403);

        $this->tallyNumber = trim($this->tallyNumber);
        $this->validate([
            'tallyNumber' => ['required', 'string', 'max:50', Rule::unique('sales', 'invoice_number')],
        ], [
            'tallyNumber.required' => 'Enter the bill number from Tally.',
            'tallyNumber.unique' => 'This Tally bill number is already on another sale.',
        ], ['tallyNumber' => 'Tally bill number']);

        $sale = Sale::with('items', 'customer')->findOrFail($this->verifyingId);
        if ($sale->confirmed_by_accountant) {
            $this->showVerify = false;

            return;
        }

        $sale->update(['confirmed_by_accountant' => true, 'invoice_number' => $this->tallyNumber]);

        // Per-item update (not a mass whereIn) so each item's own activity-log timeline picks up reserved -> sold.
        $sale->items->where('status', 'reserved')->each(fn ($item) => $item->update(['status' => 'sold']));

        // A sale that delivers a custom order completes that order.
        \App\Models\Orders\Order::where('converted_sale_id', $sale->id)->whereIn('status', ['placed', 'confirmed', 'ready'])->get()->each->update(['status' => 'delivered']);

        MessageTemplates::queue('sale_confirmation', $sale->customer, MessageTemplates::saleConfirmation($sale->fresh('payments')), 'sale', $sale->id);

        $this->showVerify = false;
        $this->dispatch('toast', message: "Sale verified as Tally bill {$this->tallyNumber}.", type: 'success');
        $this->reset(['verifyingId', 'tallyNumber']);
    }

    public function render()
    {
        $query = Sale::with('customer', 'creator', 'items')->withSum('payments', 'amount')
            ->where('confirmed_by_accountant', false)
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('id', ltrim($this->search, '#'))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%"))));

        return view('livewire.sales.sale-verification-queue', [
            'pending' => $this->applySorting($query)->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Sale Verification Queue — Radharani Jewellery']);
    }
}
