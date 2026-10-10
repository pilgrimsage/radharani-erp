<?php
namespace App\Livewire\Purchase;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use Livewire\Component;

/**
 * New Purchase Entry — admin-only (gated by purchase.manage).
 *
 * Finished-product purchases (vendor already delivers tagged items) attach
 * real items with a rate/weight to purchase_items. Raw-material purchases
 * save a purchases header row (vendor, total weight, total amount, GST,
 * status) plus one or more untagged description lines — each becomes a
 * purchase_items row with item_id = null and tag_pending = true. Those
 * lines are later converted into real Item rows from the "Pending Tags"
 * section on the Stock > Items screen, which fills in item_id and flips
 * tag_pending back to false (a purchase_items update, not a purchases row
 * update, so CLAUDE.md rule 1 still holds).
 */
class NewPurchaseEntry extends Component
{
    public string $purchaseType = 'raw_material';
    public ?int $vendorId = null;
    public string $invoiceNumber = '';
    public string $totalWeight = '';
    public string $totalAmount = '';
    public string $gst = '';
    public string $paymentStatus = 'pending';

    // Finished-product line items
    public array $lines = [];
    public string $itemSearch = '';

    // Raw-material description lines (no item yet — tagged later in Stock)
    public array $rawLines = [];

    public function mount()
    {
        $this->addBlankLine();
        $this->addBlankRawLine();
    }

    public function addBlankLine()
    {
        $this->lines[] = ['item_id' => null, 'label' => '', 'rate' => '', 'weight' => ''];
    }

    public function removeLine(int $index)
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function addBlankRawLine()
    {
        $this->rawLines[] = [
            'description' => '', 'category' => '', 'metal' => 'gold',
            'purity' => '', 'weight' => '', 'rate' => '',
        ];
    }

    public function removeRawLine(int $index)
    {
        unset($this->rawLines[$index]);
        $this->rawLines = array_values($this->rawLines);
    }

    public function pickItem(int $index, int $itemId)
    {
        $item = Item::find($itemId);
        if ($item) {
            $this->lines[$index]['item_id'] = $item->id;
            $this->lines[$index]['label'] = $item->huid_code ?: $item->internal_code;
            $this->lines[$index]['weight'] = (string) $item->weight;
        }
    }

    public function getLineCountProperty(): int
    {
        $lines = $this->purchaseType === 'raw_material' ? $this->rawLines : $this->lines;

        return collect($lines)->filter(fn ($l) => $this->purchaseType === 'raw_material'
            ? ($l['description'] !== '' || $l['weight'] !== '')
            : ($l['item_id'] && $l['rate'] !== '' && $l['weight'] !== ''))->count();
    }

    public function getLineWeightTotalProperty(): float
    {
        $lines = $this->purchaseType === 'raw_material' ? $this->rawLines : $this->lines;

        return round(collect($lines)->sum(fn ($l) => (float) ($l['weight'] ?: 0)), 3);
    }

    public function getSearchResultsProperty()
    {
        if (strlen($this->itemSearch) < 2) {
            return collect();
        }

        return Item::where('huid_code', 'like', "%{$this->itemSearch}%")
            ->orWhere('internal_code', 'like', "%{$this->itemSearch}%")
            ->limit(10)
            ->get();
    }

    protected function rules(): array
    {
        $rules = [
            'vendorId' => 'required|exists:vendors,id',
            'invoiceNumber' => 'nullable|string|max:50',
            'gst' => 'nullable|numeric',
            'paymentStatus' => 'required|in:paid,partial,pending',
        ];

        if ($this->purchaseType === 'raw_material') {
            $rules['totalWeight'] = 'required|numeric|min:0';
            $rules['totalAmount'] = 'required|numeric|min:0';
        } else {
            $rules['totalAmount'] = 'required|numeric|min:0';
        }

        return $rules;
    }

    public function save()
    {
        $this->validate();

        $purchase = Purchase::create([
            'vendor_id' => $this->vendorId,
            'type' => $this->purchaseType,
            'invoice_number' => $this->invoiceNumber ?: null,
            'total_weight' => $this->totalWeight !== '' ? $this->totalWeight : null,
            'total_amount' => $this->totalAmount,
            'gst' => $this->gst !== '' ? $this->gst : null,
            'payment_status' => $this->paymentStatus,
            'created_by' => auth()->id(),
        ]);

        if ($this->purchaseType === 'finished_product') {
            foreach ($this->lines as $line) {
                if ($line['item_id'] && $line['rate'] !== '' && $line['weight'] !== '') {
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'item_id' => $line['item_id'],
                        'rate' => $line['rate'],
                        'weight' => $line['weight'],
                        'tag_pending' => false,
                    ]);
                }
            }
        } else {
            foreach ($this->rawLines as $line) {
                if ($line['description'] !== '' || $line['weight'] !== '') {
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'item_id' => null,
                        'description' => $line['description'] ?: null,
                        'category' => $line['category'] ?: null,
                        'metal' => $line['metal'] ?: null,
                        'purity' => $line['purity'] ?: null,
                        'weight' => $line['weight'] !== '' ? $line['weight'] : null,
                        'rate' => $line['rate'] !== '' ? $line['rate'] : null,
                        'tag_pending' => true,
                    ]);

                    // Raw material bought adds to the raw-metal balance (8 Oct change list, 6.5).
                    if ($line['metal'] && is_numeric($line['weight']) && (float) $line['weight'] > 0) {
                        \App\Models\Movement\RawMetalEntry::record($line['metal'], $line['purity'] ?: null, (float) $line['weight'], 'purchase', $purchase->id);
                    }
                }
            }
        }

        $this->reset(['vendorId', 'invoiceNumber', 'totalWeight', 'totalAmount', 'gst', 'lines', 'rawLines']);
        $this->paymentStatus = 'pending';
        $this->addBlankLine();
        $this->addBlankRawLine();
        $this->dispatch('toast', message: "Purchase #{$purchase->id} recorded.", type: 'success');
    }

    public function render()
    {
        return view('livewire.purchase.new-purchase-entry', [
            'vendors' => Vendor::orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'New Purchase Entry — Radharani Jewellery']);
    }
}
