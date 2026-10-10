<?php
namespace App\Livewire\Movement;

use App\Livewire\Movement\Concerns\HasDoneBy;
use App\Livewire\Stock\ItemForm;
use App\Models\Movement\KarigarPayment;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\KarigarReceipt;
use App\Models\Movement\RawMetalEntry;
use App\Models\Orders\Order;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Services\PhotoCompressionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The one Karigar screen (8 Oct change list, section 6): issue a batch (with or without an
 * advance), receive it in as many parts as it takes, pay in cash or metal, and see every batch
 * with what is still pending. Wastage and weight loss are typed in, never worked out.
 * Returned pieces still wait in Pending Review before they become normal stock.
 */
class KarigarDesk extends Component
{
    use HasDoneBy, WithFileUploads;

    #[Url(except: 'issue')]
    public string $tab = 'issue'; // issue | receive | payments | batches | repairs

    public $photo = null;

    // ---- issue
    public ?int $vendorId = null;
    public string $metal = 'gold';
    public string $description = '';
    public array $categories = [];
    public $piecesExpected = '';
    public $estimatedWeight = '';
    public ?string $expectedReturn = null;
    public string $note = '';
    public bool $withAdvance = false;
    public $advanceCash = '';
    public $advanceMetalWeight = '';
    public string $advanceMetalPurity = '';
    public ?int $orderId = null;

    // ---- receive
    public ?int $batchId = null;
    public string $disposition = 'stock'; // stock | hallmark
    public string $weights = '';          // one weight per piece, for pieces going to stock
    public $hallmarkPieces = '';
    public $hallmarkWeight = '';
    public $lossWeight = '';
    public ?int $receiveCategoryId = null;
    public string $receivePurity = '';
    public string $receiveNote = '';
    public string $closeNote = '';

    // ---- payments
    public ?int $payVendorId = null;
    public ?int $payBatchId = null;
    public string $payKind = 'cash';
    public $payAmount = '';
    public string $payMetal = 'gold';
    public string $payPurity = '';
    public $payWeight = '';
    public string $payNote = '';

    #[Url(except: 'open')]
    public string $batchFilter = 'open';

    public function mount(): void
    {
        $this->expectedReturn = today()->toDateString();
        $this->advanceMetalPurity = $this->payPurity = $this->receivePurity = (ItemForm::PURITIES['gold'] ?? [''])[0];
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['issue', 'receive', 'payments', 'batches', 'repairs'], true)) {
            $this->tab = $tab;
            $this->reset('photo');
            $this->resetValidation();
        }
    }

    public function removePhoto(): void
    {
        $this->reset('photo');
    }

    public function updatedMetal(): void
    {
        $this->advanceMetalPurity = (ItemForm::PURITIES[$this->metal] ?? [''])[0];
    }

    public function updatedPayMetal(): void
    {
        $this->payPurity = (ItemForm::PURITIES[$this->payMetal] ?? [''])[0];
    }

    public function selectBatch(int $id): void
    {
        $batch = KarigarRawBatch::open()->findOrFail($id);
        $this->batchId = $batch->id;
        $this->disposition = 'stock';
        $this->reset(['weights', 'hallmarkPieces', 'hallmarkWeight', 'lossWeight', 'receiveNote', 'closeNote', 'photo']);
        $this->receivePurity = (string) ($batch->advance_metal_purity ?: (ItemForm::PURITIES[$batch->metal] ?? [''])[0]);
        $this->receiveCategoryId = ItemCategory::active()->forMetal($batch->metal)
            ->whereIn('name', $batch->categories ?? [])->orderBy('sort_order')->value('id');
        $this->resetValidation();
        $this->tab = 'receive';
    }

    public function toggleCategory(string $name): void
    {
        $this->categories = in_array($name, $this->categories, true)
            ? array_values(array_diff($this->categories, [$name]))
            : [...$this->categories, $name];
    }

    // ================================================================ issue

    public function issue(): void
    {
        $this->validate([
            'vendorId' => ['required', Rule::exists('vendors', 'id')->where('type', 'karigar')],
            'metal' => ['required', Rule::in(array_keys(ItemForm::METALS))],
            'description' => ['required', 'string', 'max:150'],
            'piecesExpected' => ['required', 'integer', 'min:1', 'max:5000'],
            'estimatedWeight' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'expectedReturn' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'advanceCash' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'advanceMetalWeight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'advanceMetalPurity' => ['nullable', 'string', 'max:10'],
            'orderId' => ['nullable', 'exists:orders,id'],
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'vendorId.required' => 'Choose the karigar.',
            'photo.required' => 'Add a photo of what is going out.',
            'piecesExpected.required' => 'Say how many pieces to expect.',
            'expectedReturn.after_or_equal' => 'The expected date can be today or later.',
        ], ['description' => 'description', 'piecesExpected' => 'number of pieces', 'estimatedWeight' => 'estimated weight']);

        $cash = $this->withAdvance ? (float) $this->advanceCash : 0.0;
        $metalWeight = $this->withAdvance ? (float) $this->advanceMetalWeight : 0.0;
        if ($this->withAdvance && $cash <= 0 && $metalWeight <= 0) {
            $this->addError('advanceCash', 'Enter the advance (cash, metal or both), or switch it off.');

            return;
        }
        if ($metalWeight > 0) {
            $available = RawMetalEntry::balanceFor($this->metal, $this->advanceMetalPurity);
            if ($metalWeight > $available + 0.0005) {
                $this->addError('advanceMetalWeight', 'Only ' . number_format($available, 3) . ' g of ' . $this->metal . ' ' . $this->advanceMetalPurity . ' is in the raw-metal balance.');

                return;
            }
        }

        $vendor = Vendor::findOrFail($this->vendorId);
        $photoPath = app(PhotoCompressionService::class)->store($this->photo, 'movements/karigar');

        $batch = DB::transaction(function () use ($vendor, $cash, $metalWeight, $photoPath) {
            $batch = KarigarRawBatch::create([
                'vendor_id' => $vendor->id,
                'metal' => $this->metal,
                'weight_out' => $this->estimatedWeight,
                'description' => $this->description,
                'purpose_label' => mb_substr($this->description, 0, 50),
                'categories' => $this->categories ?: null,
                'pieces_expected' => (int) $this->piecesExpected,
                'advance_cash' => $cash,
                'advance_metal_weight' => $metalWeight,
                'advance_metal_purity' => $metalWeight > 0 ? $this->advanceMetalPurity : null,
                'purity' => $metalWeight > 0 ? $this->advanceMetalPurity : null,
                'order_id' => $this->orderId,
                'expected_return' => $this->expectedReturn ?: null,
                'status' => 'dispatched',
                'note' => $this->note ?: null,
                'photo_path' => $photoPath,
                'user_id' => Auth::id(),
            ]);

            if ($metalWeight > 0) {
                RawMetalEntry::record($this->metal, $this->advanceMetalPurity, -$metalWeight, 'karigar_advance', $batch->id, "Advance to {$vendor->name}");
            }

            return $batch;
        });

        $this->dispatch('toast', message: "Batch of {$batch->pieces_expected} pieces issued to {$vendor->name}.", type: 'success');
        $this->reset(['description', 'categories', 'piecesExpected', 'estimatedWeight', 'note', 'withAdvance', 'advanceCash', 'advanceMetalWeight', 'orderId', 'photo']);
        $this->redirectRoute('movements.karigar.print', $batch);
    }

    // ================================================================ receive

    private function parseWeights(): array
    {
        return collect(preg_split('/[\s,;]+/', trim($this->weights), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($w) => str_replace('g', '', strtolower($w)))->all();
    }

    public function receive(): void
    {
        $batch = KarigarRawBatch::open()->with('vendor')->findOrFail($this->batchId);
        $stock = $this->disposition === 'stock';

        $this->validate([
            'lossWeight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'receiveNote' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:5120'],
            'receiveCategoryId' => $stock ? ['required', Rule::exists('item_categories', 'id')->where('metal', $batch->metal)] : ['nullable'],
            'receivePurity' => $stock ? ['required', 'string', 'max:10'] : ['nullable'],
            'weights' => $stock ? ['required', 'string'] : ['nullable'],
            'hallmarkPieces' => $stock ? ['nullable'] : ['required', 'integer', 'min:1', 'max:5000'],
            'hallmarkWeight' => $stock ? ['nullable'] : ['required', 'numeric', 'min:0.001', 'max:99999'],
        ], [
            'photo.required' => 'Add a photo of what came back.',
            'receiveCategoryId.required' => 'Choose the subcategory these pieces belong to.',
            'weights.required' => 'Type the weight of each piece, separated by spaces or new lines.',
        ], ['receiveCategoryId' => 'subcategory', 'hallmarkPieces' => 'number of pieces', 'hallmarkWeight' => 'total weight']);

        if ($stock) {
            $weights = $this->parseWeights();
            foreach ($weights as $w) {
                if (! is_numeric($w) || (float) $w <= 0) {
                    $this->addError('weights', "\"{$w}\" is not a weight.");

                    return;
                }
            }
            $pieces = count($weights);
            $total = round(array_sum(array_map('floatval', $weights)), 3);
        } else {
            $pieces = (int) $this->hallmarkPieces;
            $total = (float) $this->hallmarkWeight;
            $weights = [];
        }

        if ($pieces > $batch->pieces_pending + 0 && $batch->pieces_pending > 0) {
            // More than expected can come back (an extra piece made); allowed, but say so on the note.
        }

        $photoPath = app(PhotoCompressionService::class)->store($this->photo, 'movements/karigar');
        $category = $stock ? ItemCategory::findOrFail($this->receiveCategoryId) : null;

        DB::transaction(function () use ($batch, $stock, $pieces, $total, $weights, $photoPath, $category) {
            KarigarReceipt::create([
                'batch_id' => $batch->id,
                'pieces' => $pieces,
                'weight_received' => $total,
                'weight_loss' => (float) ($this->lossWeight ?: 0),
                'disposition' => $this->disposition,
                'photo_path' => $photoPath,
                'note' => $this->receiveNote ?: null,
                'user_id' => Auth::id(),
                ...$this->doneByAttributes(),
            ]);

            if ($stock) {
                foreach ($weights as $w) {
                    Item::create([
                        'internal_code' => Item::generateInternalCode(),
                        'metal' => $batch->metal,
                        'category' => $category->name,
                        'category_id' => $category->id,
                        'purity' => $this->receivePurity,
                        'weight' => (float) $w,
                        'making_type' => 'flat_per_piece',
                        'making_value' => 0,
                        'status' => 'pending_review', // an admin confirms it into stock (rule 9)
                        'source_karigar_batch_id' => $batch->id,
                    ]);
                }
            }

            $received = (int) $batch->receipts()->sum('pieces');
            $batch->update([
                'status' => $received >= $batch->pieces_expected ? 'returned' : 'partially_returned',
                'actual_return' => today(),
                'weight_returned' => (float) $batch->receipts()->sum('weight_received'),
                'weight_loss' => (float) $batch->receipts()->sum('weight_loss'),
                'returned_by' => Auth::id(),
            ]);
        });

        $batch->refresh();
        $msg = "{$pieces} " . \Illuminate\Support\Str::plural('piece', $pieces) . ' received from ' . $batch->vendor->name . '.';
        $msg .= $batch->is_open ? " {$batch->pieces_pending} still pending." : ' Batch complete.';
        $this->dispatch('toast', message: $msg, type: 'success');

        $this->reset(['weights', 'hallmarkPieces', 'hallmarkWeight', 'lossWeight', 'receiveNote', 'photo']);
        if (! $batch->is_open) {
            $this->batchId = null;
        }
    }

    // The owner or manager closes what has not come back, with a note.
    public function closeRemainder(): void
    {
        abort_unless(Auth::user()?->can('movement.approve'), 403);
        $this->validate(['closeNote' => ['required', 'string', 'max:255']], ['closeNote.required' => 'Say why the remaining pieces are being closed.']);

        $batch = KarigarRawBatch::open()->findOrFail($this->batchId);
        $pending = $batch->pieces_pending;
        $batch->update(['status' => 'closed', 'closed_by' => Auth::id(), 'closed_at' => now(), 'close_note' => $this->closeNote]);

        $this->dispatch('toast', message: "Batch closed with {$pending} " . \Illuminate\Support\Str::plural('piece', $pending) . ' not returned.', type: 'success');
        $this->reset(['batchId', 'closeNote']);
    }

    // ================================================================ payments

    public function pay(): void
    {
        $metal = $this->payKind === 'metal';
        $this->validate([
            'payVendorId' => ['required', Rule::exists('vendors', 'id')->where('type', 'karigar')],
            'payBatchId' => ['nullable', 'exists:karigar_raw_batches,id'],
            'payKind' => ['required', 'in:cash,metal'],
            'payAmount' => $metal ? ['nullable'] : ['required', 'numeric', 'min:1', 'max:99999999'],
            'payMetal' => $metal ? ['required', Rule::in(array_keys(ItemForm::METALS))] : ['nullable'],
            'payPurity' => $metal ? ['required', 'string', 'max:10'] : ['nullable'],
            'payWeight' => $metal ? ['required', 'numeric', 'min:0.001', 'max:99999'] : ['nullable'],
            'payNote' => ['nullable', 'string', 'max:255'],
        ], ['payVendorId.required' => 'Choose the karigar.'], ['payAmount' => 'amount', 'payWeight' => 'weight', 'payPurity' => 'carat']);

        if ($metal) {
            $available = RawMetalEntry::balanceFor($this->payMetal, $this->payPurity);
            if ((float) $this->payWeight > $available + 0.0005) {
                $this->addError('payWeight', 'Only ' . number_format($available, 3) . ' g of ' . $this->payMetal . ' ' . $this->payPurity . ' is in the raw-metal balance.');

                return;
            }
        }

        DB::transaction(function () use ($metal) {
            $payment = KarigarPayment::create([
                'vendor_id' => $this->payVendorId,
                'batch_id' => $this->payBatchId,
                'kind' => $this->payKind,
                'amount' => $metal ? null : $this->payAmount,
                'metal' => $metal ? $this->payMetal : null,
                'purity' => $metal ? $this->payPurity : null,
                'weight' => $metal ? $this->payWeight : null,
                'note' => $this->payNote ?: null,
                'user_id' => Auth::id(),
            ]);
            if ($metal) {
                RawMetalEntry::record($this->payMetal, $this->payPurity, -(float) $this->payWeight, 'karigar_payment', $payment->id, 'Paid to ' . Vendor::find($this->payVendorId)?->name);
            }
        });

        $this->dispatch('toast', message: $metal ? number_format((float) $this->payWeight, 3) . " g {$this->payMetal} paid." : '₹' . number_format((float) $this->payAmount) . ' cash payment recorded.', type: 'success');
        $this->reset(['payAmount', 'payWeight', 'payNote']);
    }

    // ================================================================ render

    public function render()
    {
        $open = KarigarRawBatch::open()->with('vendor:id,name')->withSum('receipts', 'pieces')->orderBy('expected_return')->orderBy('id')->get();
        $batch = $this->batchId ? $open->firstWhere('id', $this->batchId) : null;

        $batches = $this->tab === 'batches'
            ? KarigarRawBatch::with('vendor:id,name', 'user:id,name')->withSum('receipts', 'pieces')
                ->when($this->batchFilter === 'open', fn ($q) => $q->open())
                ->when($this->batchFilter === 'closed', fn ($q) => $q->whereIn('status', ['returned', 'closed']))
                ->latest('id')->limit(100)->get()
            : collect();

        return view('livewire.movement.karigar-desk', [
            'karigars' => Vendor::where('type', 'karigar')->orderBy('name')->get(['id', 'name']),
            'metals' => ItemForm::METALS,
            'purities' => ItemForm::PURITIES,
            'subcategories' => ItemCategory::active()->orderBy('metal')->orderBy('sort_order')->orderBy('name')->get(['id', 'metal', 'name']),
            'orders' => Order::whereIn('status', ['placed', 'confirmed'])->with('customer:id,name')->latest('id')->limit(50)->get(),
            'open' => $open,
            'batch' => $batch,
            'batches' => $batches,
            'balances' => RawMetalEntry::balances(),
            'payments' => $this->tab === 'payments' ? KarigarPayment::with('vendor:id,name', 'user:id,name')->latest('id')->limit(15)->get() : collect(),
            'canClose' => Auth::user()?->can('movement.approve'),
            'receiveCategories' => $batch ? ItemCategory::active()->forMetal($batch->metal)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : collect(),
        ])->layout('components.layouts.app', ['title' => 'Karigar · Radharani Jewellery']);
    }
}
