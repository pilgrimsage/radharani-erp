<?php
namespace App\Livewire\Movement;

use App\Livewire\Movement\Concerns\HasDoneBy;
use App\Livewire\Movement\Concerns\PicksItems;
use App\Livewire\Stock\ItemForm;
use App\Models\Movement\HallmarkBatch;
use App\Models\Movement\HallmarkBatchItem;
use App\Models\Movement\HallmarkReceipt;
use App\Models\Movement\KarigarReceipt;
use App\Models\Movement\Movement;
use App\Models\Orders\Order;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Models\Stock\Packet;
use App\Services\PhotoCompressionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The one Hallmarking screen (8 Oct change list, section 7): dispatch a batch to a centre
 * (untagged pieces by count, and/or tagged stock pieces), and receive it back in parts with
 * the HUIDs the centre gave. Returned pieces wait in Pending Review (rule 9).
 */
class HallmarkDesk extends Component
{
    use HasDoneBy, PicksItems, WithFileUploads;

    #[Url(except: 'dispatch')]
    public string $tab = 'dispatch'; // dispatch | receive | batches

    public $photo = null;

    // ---- dispatch
    public ?int $centreId = null;
    public string $source = 'stock'; // stock | order
    public ?int $orderId = null;
    public string $description = '';
    public $piecesCounted = '';
    public $weightCounted = '';
    public $huidExpected = '';
    public array $karigarReceiptIds = [];
    public ?string $expectedReturn = null;
    public string $note = '';

    // ---- receive
    public ?int $batchId = null;
    public string $taggedBy = '';
    public array $lineHuid = [];     // batch_item id => HUID
    public array $lineWeight = [];   // batch_item id => weight
    public array $lineBack = [];     // batch_item id => bool
    public string $withHuid = '';    // lines: HUID weight [description]
    public string $withoutHuid = ''; // lines: weight [description]
    public string $metal = 'gold';
    public ?int $categoryId = null;
    public string $purity = '22K';
    public ?int $packetId = null;
    public $lossWeight = '';
    public string $receiveNote = '';
    public string $closeNote = '';

    #[Url(except: 'open')]
    public string $batchFilter = 'open';

    public function mount(): void
    {
        $this->expectedReturn = today()->addDays(3)->toDateString();
        $centres = Vendor::where('type', 'hallmark_center')->pluck('id');
        $this->centreId = $centres->count() === 1 ? $centres->first() : null;
    }

    protected function pickableStatuses(): array
    {
        return ['in_stock', 'pending_review'];
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['dispatch', 'receive', 'batches'], true)) {
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
        $this->categoryId = null;
        $this->purity = (ItemForm::PURITIES[$this->metal] ?? [''])[0];
    }

    // ================================================================ dispatch

    public function dispatchBatch(): void
    {
        $fromKarigar = KarigarReceipt::where('disposition', 'hallmark')->whereNull('hallmark_batch_id')->whereIn('id', $this->karigarReceiptIds)->get();
        $counted = (int) ($this->piecesCounted ?: 0) + (int) $fromKarigar->sum('pieces');
        $countedWeight = (float) ($this->weightCounted ?: 0) + (float) $fromKarigar->sum('weight_received');

        $this->validate([
            'centreId' => ['required', Rule::exists('vendors', 'id')->where('type', 'hallmark_center')],
            'source' => ['required', 'in:stock,order'],
            'orderId' => ['nullable', 'required_if:source,order', 'exists:orders,id'],
            'description' => ['required', 'string', 'max:150'],
            'piecesCounted' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'weightCounted' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'huidExpected' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'expectedReturn' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'centreId.required' => 'Choose the hallmarking centre.',
            'orderId.required_if' => 'Choose the order.',
            'photo.required' => 'Add a photo of what is going out.',
            'description.required' => 'Give the batch a short description.',
        ]);

        $items = Item::whereIn('id', $this->basket)->whereIn('status', $this->pickableStatuses())->get();
        if ($counted === 0 && $items->isEmpty()) {
            $this->addError('piecesCounted', 'Add pieces by count, tagged pieces from stock, or both.');

            return;
        }
        if ($counted > 0 && $countedWeight <= 0) {
            $this->addError('weightCounted', 'Enter the total weight of the counted pieces.');

            return;
        }

        $centre = Vendor::findOrFail($this->centreId);
        $photoPath = app(PhotoCompressionService::class)->store($this->photo, 'movements/hallmark');
        $total = $counted + $items->count();

        $batch = DB::transaction(function () use ($centre, $items, $counted, $countedWeight, $fromKarigar, $photoPath, $total) {
            $batch = HallmarkBatch::create([
                'vendor_id' => $centre->id,
                'source' => $this->source,
                'order_id' => $this->source === 'order' ? $this->orderId : null,
                'description' => $this->description,
                'pieces_counted' => $counted,
                'weight_counted' => $countedWeight,
                'huid_expected' => $this->huidExpected !== '' ? (int) $this->huidExpected : $total,
                'expected_return' => $this->expectedReturn ?: null,
                'status' => 'dispatched',
                'photo_path' => $photoPath,
                'note' => $this->note ?: null,
                'user_id' => Auth::id(),
                'done_by_employee_id' => $this->doneByAttributes()['done_by_employee_id'],
            ]);

            foreach ($items as $item) {
                HallmarkBatchItem::create(['hallmark_batch_id' => $batch->id, 'item_id' => $item->id, 'weight_out' => $item->weight]);
                Movement::create([
                    ...$this->doneByAttributes(),
                    'trackable_type' => 'item',
                    'trackable_id' => $item->id,
                    'movement_type' => 'hallmark_out',
                    'purpose_label' => 'Hallmarking',
                    'user_id' => Auth::id(),
                    'counterparty' => $centre->name,
                    'expected_return' => $this->expectedReturn ?: null,
                    'weight_at_dispatch' => $item->weight,
                    'photo_path' => $photoPath,
                    'note' => $this->note ?: null,
                ]);
                $item->update(['status' => 'dispatched']);
            }

            // Pieces a karigar sent on untagged are now accounted for in this batch.
            foreach ($fromKarigar as $r) {
                $r->update(['hallmark_batch_id' => $batch->id]);
            }

            return $batch;
        });

        $this->dispatch('toast', message: "{$total} " . \Illuminate\Support\Str::plural('piece', $total) . " sent to {$centre->name}.", type: 'success');
        $this->reset(['description', 'piecesCounted', 'weightCounted', 'huidExpected', 'karigarReceiptIds', 'basket', 'note', 'photo', 'orderId']);
    }

    // ================================================================ receive

    public function selectBatch(int $id): void
    {
        $batch = HallmarkBatch::open()->with('lines.item')->findOrFail($id);
        $this->batchId = $batch->id;
        $this->reset(['withHuid', 'withoutHuid', 'lossWeight', 'receiveNote', 'photo', 'lineHuid', 'lineWeight', 'lineBack', 'closeNote']);
        foreach ($batch->lines->where('returned', false) as $l) {
            $this->lineHuid[$l->id] = (string) $l->item?->huid_code;
            $this->lineWeight[$l->id] = (string) (float) $l->weight_out;
            $this->lineBack[$l->id] = false;
        }
        $this->resetValidation();
        $this->tab = 'receive';
    }

    private function parseLines(string $text): array
    {
        return collect(preg_split('/\R/', trim($text), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($l) => trim($l))->filter()->values()->all();
    }

    public function receive(): void
    {
        $batch = HallmarkBatch::open()->with('lines.item', 'centre')->findOrFail($this->batchId);

        $this->validate([
            'taggedBy' => ['required', 'string', 'max:100'],
            'lossWeight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'receiveNote' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:5120'],
        ], ['taggedBy.required' => 'Enter who did the tagging.', 'photo.required' => 'Add a photo of what came back.']);

        // ---- untagged pieces (count-based)
        $newPieces = [];
        foreach ($this->parseLines($this->withHuid) as $line) {
            $p = preg_split('/\s+/', $line, 3);
            $huid = strtoupper($p[0] ?? '');
            $w = $p[1] ?? '';
            if (! preg_match('/^[A-Z0-9]{6}$/', $huid)) {
                $this->addError('withHuid', "\"{$p[0]}\" is not a HUID. A HUID is 6 letters or digits.");

                return;
            }
            if (! is_numeric($w) || (float) $w <= 0) {
                $this->addError('withHuid', "Add the weight after the HUID on the line \"{$line}\".");

                return;
            }
            $newPieces[] = ['huid' => $huid, 'weight' => (float) $w, 'description' => $p[2] ?? null];
        }
        foreach ($this->parseLines($this->withoutHuid) as $line) {
            $p = preg_split('/\s+/', $line, 2);
            if (! is_numeric($p[0]) || (float) $p[0] <= 0) {
                $this->addError('withoutHuid', "\"{$p[0]}\" is not a weight.");

                return;
            }
            // Under 2 g needs no HUID; heavier pieces must have one.
            if ((float) $p[0] >= 2) {
                $this->addError('withoutHuid', "{$p[0]} g is over 2 g, so it needs a HUID. Put it in the HUID box.");

                return;
            }
            $newPieces[] = ['huid' => null, 'weight' => (float) $p[0], 'description' => $p[1] ?? null];
        }

        $huids = collect($newPieces)->pluck('huid')->filter();
        if ($huids->count() !== $huids->unique()->count()) {
            $this->addError('withHuid', 'The same HUID is listed twice.');

            return;
        }
        if ($huids->isNotEmpty() && Item::whereIn('huid_code', $huids)->exists()) {
            $this->addError('withHuid', 'Another piece already has ' . Item::whereIn('huid_code', $huids)->value('huid_code') . '.');

            return;
        }
        if ($newPieces) {
            $this->validate([
                'categoryId' => ['required', Rule::exists('item_categories', 'id')->where('metal', $this->metal)],
                'purity' => ['required', 'string', 'max:10'],
                'packetId' => ['nullable', 'exists:packets,id,deleted_at,NULL'],
            ], ['categoryId.required' => 'Choose the subcategory for the new pieces.'], ['categoryId' => 'subcategory']);
        }

        // ---- tagged pieces coming back (item-based)
        $back = collect($batch->lines)->filter(fn ($l) => ! $l->returned && ($this->lineBack[$l->id] ?? false));
        foreach ($back as $l) {
            $huid = strtoupper(trim($this->lineHuid[$l->id] ?? ''));
            $w = $this->lineWeight[$l->id] ?? '';
            if (! is_numeric($w) || (float) $w <= 0) {
                $this->addError('lineWeight.' . $l->id, 'Enter the weight.');

                return;
            }
            if ($huid !== '' && $huid !== $l->item->huid_code && (! preg_match('/^[A-Z0-9]{6}$/', $huid) || Item::where('huid_code', $huid)->where('id', '!=', $l->item_id)->exists())) {
                $this->addError('lineHuid.' . $l->id, 'Not a valid, unused HUID.');

                return;
            }
        }

        if (! $newPieces && $back->isEmpty()) {
            $this->addError('withHuid', 'Add the pieces that came back: HUIDs, pieces without a HUID, or tick tagged pieces.');

            return;
        }

        $photoPath = app(PhotoCompressionService::class)->store($this->photo, 'movements/hallmark');
        $category = $newPieces ? ItemCategory::findOrFail($this->categoryId) : null;
        $weightIn = collect($newPieces)->sum('weight') + $back->sum(fn ($l) => (float) $this->lineWeight[$l->id]);

        DB::transaction(function () use ($batch, $newPieces, $back, $category, $photoPath, $weightIn) {
            foreach ($back as $l) {
                $item = $l->item;
                $huid = strtoupper(trim($this->lineHuid[$l->id] ?? ''));
                $w = (float) $this->lineWeight[$l->id];
                $update = ['status' => 'pending_review'];
                if ($huid !== '') {
                    $update['huid_code'] = $huid;
                } elseif (! $item->internal_code && ! $item->huid_code) {
                    $update['internal_code'] = Item::generateInternalCode();
                }
                if (abs($w - (float) $item->weight) > 0.0005) {
                    $update['weight'] = $w; // logged in the piece's history with before and after
                }
                $item->update($update);
                $l->update(['returned' => true]);

                Movement::create([
                    ...$this->doneByAttributes(),
                    'trackable_type' => 'item',
                    'trackable_id' => $item->id,
                    'movement_type' => 'hallmark_in',
                    'purpose_label' => 'Hallmarking',
                    'user_id' => Auth::id(),
                    'counterparty' => $batch->centre->name,
                    'actual_return' => today(),
                    'weight_at_return' => $w,
                    'weight_loss' => max(0, round((float) $l->weight_out - $w, 3)),
                    'tagged_by' => $this->taggedBy,
                    'photo_path' => $photoPath,
                    'note' => $this->receiveNote ?: null,
                ]);
            }

            foreach ($newPieces as $np) {
                Item::create([
                    'huid_code' => $np['huid'],
                    'internal_code' => $np['huid'] ? null : Item::generateInternalCode(),
                    'metal' => $this->metal,
                    'category' => $category->name,
                    'category_id' => $category->id,
                    'purity' => $this->purity,
                    'weight' => $np['weight'],
                    'description' => $np['description'] ? mb_substr($np['description'], 0, 100) : null,
                    'making_type' => 'flat_per_piece',
                    'making_value' => 0,
                    'packet_id' => $this->packetId,
                    'status' => 'pending_review',
                    'source_hallmark_batch_id' => $batch->id,
                ]);
            }

            $count = count($newPieces) + $back->count();
            HallmarkReceipt::create([
                'hallmark_batch_id' => $batch->id,
                'pieces' => $count,
                'with_huid' => collect($newPieces)->whereNotNull('huid')->count() + $back->filter(fn ($l) => strtoupper(trim($this->lineHuid[$l->id] ?? '')) !== '' || $l->item->huid_code)->count(),
                'without_huid' => collect($newPieces)->whereNull('huid')->count(),
                'weight_received' => round($weightIn, 3),
                'weight_loss' => (float) ($this->lossWeight ?: 0),
                'tagged_by' => $this->taggedBy,
                'photo_path' => $photoPath,
                'note' => $this->receiveNote ?: null,
                'user_id' => Auth::id(),
                ...$this->doneByAttributes(),
            ]);

            $batch->update(['status' => $batch->receipts()->sum('pieces') >= $batch->pieces_out ? 'returned' : 'partially_returned']);
        });

        $batch->refresh();
        $n = count($newPieces) + $back->count();
        $this->dispatch('toast', message: "{$n} " . \Illuminate\Support\Str::plural('piece', $n) . ' back from ' . $batch->centre->name . '. They wait in Pending Review.' . ($batch->is_open ? " {$batch->pieces_pending} still out." : ' Batch complete.'), type: 'success');

        $this->reset(['withHuid', 'withoutHuid', 'lossWeight', 'receiveNote', 'photo']);
        if ($batch->is_open) {
            $this->selectBatch($batch->id);
        } else {
            $this->batchId = null;
        }
    }

    public function closeRemainder(): void
    {
        abort_unless(Auth::user()?->can('movement.approve'), 403);
        $this->validate(['closeNote' => ['required', 'string', 'max:255']], ['closeNote.required' => 'Say why the rest is being closed.']);
        $batch = HallmarkBatch::open()->findOrFail($this->batchId);
        $batch->update(['status' => 'closed', 'closed_by' => Auth::id(), 'closed_at' => now(), 'close_note' => $this->closeNote]);
        $this->dispatch('toast', message: 'Batch closed.', type: 'success');
        $this->reset(['batchId', 'closeNote']);
    }

    // ================================================================ render

    public function render()
    {
        $open = HallmarkBatch::open()->with('centre:id,name')->withCount('lines')->withSum('receipts', 'pieces')->orderBy('expected_return')->orderBy('id')->get();
        $batch = $this->batchId ? HallmarkBatch::with('lines.item', 'centre')->withCount('lines')->withSum('receipts', 'pieces')->find($this->batchId) : null;

        return view('livewire.movement.hallmark-desk', [
            'centres' => Vendor::where('type', 'hallmark_center')->orderBy('name')->get(['id', 'name']),
            'orders' => Order::whereIn('status', ['placed', 'confirmed', 'ready'])->with('customer:id,name')->latest('id')->limit(50)->get(),
            'fromKarigar' => KarigarReceipt::where('disposition', 'hallmark')->whereNull('hallmark_batch_id')->with('batch.vendor:id,name')->latest('id')->get(),
            'basketItems' => $this->basketItems(),
            'pickResults' => $this->pickResults(),
            'open' => $open,
            'batch' => $batch,
            'batches' => $this->tab === 'batches'
                ? HallmarkBatch::with('centre:id,name')->withCount('lines')->withSum('receipts', 'pieces')
                    ->when($this->batchFilter === 'open', fn ($q) => $q->open())
                    ->when($this->batchFilter === 'closed', fn ($q) => $q->whereIn('status', ['returned', 'closed']))
                    ->latest('id')->limit(100)->get()
                : collect(),
            'metals' => ItemForm::METALS,
            'purities' => ItemForm::PURITIES,
            'categories' => ItemCategory::active()->forMetal($this->metal)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'packets' => $this->tab === 'receive' ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'box_id']) : collect(),
            'canClose' => Auth::user()?->can('movement.approve'),
        ])->layout('components.layouts.app', ['title' => 'Hallmarking · Radharani Jewellery']);
    }
}
