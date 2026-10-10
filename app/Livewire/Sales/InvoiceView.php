<?php
namespace App\Livewire\Sales;

use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * One sale: its pieces, charges and payments, the balance, and a place to record a further
 * payment. No invoice is generated (8 Oct change list, 12.5): a plain printable bill is offered.
 */
class InvoiceView extends Component
{
    public Sale $sale;

    public string $mode = 'cash';
    public $amount = '';
    public string $note = '';

    public function mount(Sale $sale)
    {
        $this->sale = $sale;
    }

    // Further payments can clear a balance later, even after the sale is verified.
    public function addPayment(): void
    {
        abort_unless(Auth::user()?->can('sale.create'), 403);
        $this->validate([
            'mode' => ['required', 'in:cash,upi,card,bank'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = Sale::findOrFail($this->sale->id);
        if ((float) $this->amount > $sale->balance + 0.005) {
            $this->addError('amount', 'That is more than the balance of ₹' . number_format($sale->balance, 2) . '.');

            return;
        }

        SalePayment::create(['sale_id' => $sale->id, 'mode' => $this->mode, 'amount' => round((float) $this->amount, 2), 'note' => $this->note ?: null, 'user_id' => Auth::id()]);
        $this->reset(['amount', 'note']);
        $this->dispatch('toast', message: 'Payment recorded.', type: 'success');
    }

    public function render()
    {
        $sale = Sale::with('customer', 'items', 'creator', 'referrer', 'payments.user')->findOrFail($this->sale->id);

        return view('livewire.sales.invoice-view', ['sale' => $sale, 'modes' => SalePayment::MODES])
            ->layout('components.layouts.app', ['title' => "Sale {$sale->bill_number} — Radharani Jewellery"]);
    }
}
