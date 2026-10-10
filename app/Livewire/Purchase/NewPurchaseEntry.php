<?php
namespace App\Livewire\Purchase;

use App\Livewire\Stock\ItemForm;
use App\Models\Movement\RawMetalEntry;
use App\Models\Orders\Order;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Raw-material purchase (8 Oct change list, section 13): only a reference bill number, a notes
 * box and what came in by metal, carat and weight. No money, no vendor, no payment status.
 * Each entry is a batch identified by its date and time, and adds to the raw-metal balance.
 * Finished goods are not bought here; they come in through import.
 */
class NewPurchaseEntry extends Component
{
    public string $billRef = '';
    public string $notes = '';

    #[Url(as: 'order', except: '')]
    public ?int $orderId = null;

    /** @var array<int, array{metal: string, purity: string, weight: string, description: string}> */
    public array $lines = [];

    public function mount(): void
    {
        $this->addLine();
        if ($this->orderId && ! Order::whereKey($this->orderId)->exists()) {
            $this->orderId = null;
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['metal' => 'gold', 'purity' => '24K', 'weight' => '', 'description' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines) ?: [['metal' => 'gold', 'purity' => '24K', 'weight' => '', 'description' => '']];
    }

    // A different metal has different carats, so a stale pick would be wrong.
    public function updatedLines($value, $key): void
    {
        if (str_ends_with($key, '.metal')) {
            $i = (int) explode('.', $key)[0];
            $this->lines[$i]['purity'] = (ItemForm::PURITIES[$value] ?? [''])[0];
        }
    }

    public function save()
    {
        abort_unless(Auth::user()?->can('purchase.manage'), 403);

        $this->validate([
            'billRef' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'orderId' => ['nullable', 'exists:orders,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.metal' => ['required', Rule::in(array_keys(ItemForm::METALS))],
            'lines.*.purity' => ['required', 'string', 'max:10'],
            'lines.*.weight' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'lines.*.description' => ['nullable', 'string', 'max:100'],
        ], ['lines.*.weight.required' => 'Enter the weight on every line.', 'lines.*.weight.numeric' => 'The weight must be a number.'], ['lines.*.weight' => 'weight']);

        $purchase = DB::transaction(function () {
            $purchase = Purchase::create([
                'type' => 'raw_material',
                'invoice_number' => $this->billRef ?: null,
                'notes' => $this->notes ?: null,
                'order_id' => $this->orderId,
                'total_weight' => round(collect($this->lines)->sum(fn ($l) => (float) $l['weight']), 3),
                'created_by' => Auth::id(),
            ]);

            foreach ($this->lines as $l) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'item_id' => null,
                    'description' => $l['description'] ?: null,
                    'metal' => $l['metal'],
                    'purity' => $l['purity'],
                    'weight' => $l['weight'],
                    'tag_pending' => false,
                ]);
                RawMetalEntry::record($l['metal'], $l['purity'], (float) $l['weight'], 'purchase', $purchase->id, $this->billRef ?: null);
            }

            return $purchase;
        });

        $this->dispatch('toast', message: "Purchase of {$purchase->total_weight} g recorded and added to the raw-metal balance.", type: 'success');
        $this->reset(['billRef', 'notes', 'lines']);
        $this->addLine();
    }

    public function render()
    {
        return view('livewire.purchase.new-purchase-entry', [
            'metals' => ItemForm::METALS,
            'purities' => ItemForm::PURITIES,
            'orders' => Order::whereIn('status', ['placed', 'confirmed'])->with('customer:id,name')->latest('id')->limit(50)->get(),
            'balances' => RawMetalEntry::balances(),
        ])->layout('components.layouts.app', ['title' => 'Raw-material purchase — Radharani Jewellery']);
    }
}
