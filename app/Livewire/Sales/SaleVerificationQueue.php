<?php
namespace App\Livewire\Sales;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Notification\PendingNotification;
use App\Models\Sales\InvoiceCounter;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sale Verification Queue — admin-only.
 *
 * invoice_number is a placeholder ("RESV-...") until this point — GST law
 * requires sequential, gap-free numbering, which can only be known once a
 * sale is actually finalized. verify() assigns the real number here,
 * alongside confirmed_by_accountant, under InvoiceCounter's row lock. This
 * widens rule 1's one documented exception to two fields set once, by an
 * admin, at this same verification moment — see CLAUDE.md rule 1.
 */
class SaleVerificationQueue extends Component
{
    use WithDataTable;

    protected function sortableColumns(): array
    {
        return [
            'invoice' => 'invoice_number',
            'total' => 'total',
            'created' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    public function verify(int $saleId)
    {
        abort_unless(Auth::user()?->can('sale.approve'), 403);

        $sale = Sale::with('items', 'customer')->findOrFail($saleId);

        if ($sale->confirmed_by_accountant) {
            return;
        }

        $sale->update([
            'confirmed_by_accountant' => true,
            'invoice_number' => InvoiceCounter::nextFor(now()),
        ]);

        // Per-item update (not a mass whereIn) so each item's own
        // activity-log timeline picks up the reserved -> sold transition.
        $sale->items->where('status', 'reserved')->each(fn ($item) => $item->update(['status' => 'sold']));

        \App\Support\MessageTemplates::queue('sale_confirmation', $sale->customer, \App\Support\MessageTemplates::saleConfirmation($sale), 'sale', $sale->id);

        $this->dispatch('toast', message: "Sale verified as invoice {$sale->invoice_number}.", type: 'success');
    }

    public function render()
    {
        $query = Sale::with('customer', 'creator', 'items')
            ->where('confirmed_by_accountant', false)
            ->when($this->search, fn ($q) => $q->where('invoice_number', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")));

        return view('livewire.sales.sale-verification-queue', [
            'pending' => $this->applySorting($query)->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Sale Verification Queue — Radharani Jewellery']);
    }
}
