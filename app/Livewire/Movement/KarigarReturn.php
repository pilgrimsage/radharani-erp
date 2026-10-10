<?php
namespace App\Livewire\Movement;

use App\Models\Customer\CustomerMaterialJob;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\Notification\PendingNotification;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What comes back from a karigar, one tab per dispatch situation:
 *  - pieces:   tagged pieces back from repair (karigar_in movement)
 *  - customer: a customer's own material back (customer_material_jobs row closed)
 *  - raw:      a new piece made from a raw-material batch, tagged right here
 * Weight at return and the loss are always typed in by staff (#6), never
 * worked out. Returned stock waits in Pending Review (#9), unless it is
 * chained straight into a hallmarking dispatch (#7).
 */
class KarigarReturn extends Component
{
    use \App\Livewire\Movement\Concerns\HasDoneBy;

    #[Url(except: 'pieces')]
    public string $tab = 'pieces'; // pieces | customer | raw

    public string $search = '';
    public ?int $selectedId = null; // movement id | customer_material_jobs id | karigar_raw_batches id

    public $weightReturned = '';
    public $weightLoss = '';
    public ?string $returnDate = null;
    public string $note = '';

    // #7 chain into hallmarking
    public bool $toHallmark = false;
    public ?int $centreId = null;
    public ?string $hallmarkDue = null;

    // raw: tag the new piece
    public string $category = '';
    public string $purity = '';
    public string $huid = '';
    public string $description = '';

    // customer: queue a "ready to collect" message (#16)
    public bool $notifyCustomer = true;

    public function mount(): void
    {
        $this->returnDate = today()->toDateString();
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['pieces', 'customer'], true)) { // raw batches are received on the main Karigar screen
            $this->tab = $tab;
            $this->reset(['search', 'selectedId']);
            $this->resetForm();
        }
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->resetForm();

        if ($this->tab === 'raw' && ($batch = KarigarRawBatch::find($id))) {
            $this->purity = (string) $batch->purity;
            $this->description = (string) $batch->purpose_label;
        }
    }

    // A scanned tag that matches a piece out for repair opens it straight away.
    public function updatedSearch(): void
    {
        if ($this->tab !== 'pieces') {
            return;
        }
        $item = StockLookup::item($this->search);
        $open = $item ? Movement::openItemDispatches(['karigar'])->where('trackable_id', $item->id)->first() : null;

        if ($open) {
            $this->search = '';
            $this->select($open->id);
        }
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['weightReturned', 'weightLoss', 'note', 'toHallmark', 'centreId', 'category', 'purity', 'huid', 'description']);
        $this->notifyCustomer = true;
        $this->returnDate = today()->toDateString();
        $this->hallmarkDue = today()->addDays(3)->toDateString();
    }

    private function weightRules(): array
    {
        return [
            'weightReturned' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'weightLoss' => ['required', 'numeric', 'min:0', 'max:99999'],
            'returnDate' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'centreId' => ['nullable', 'required_if:toHallmark,true', Rule::exists('vendors', 'id')->where('type', 'hallmark_center')],
            'hallmarkDue' => ['nullable', 'date'],
        ];
    }

    private function validationMessages(): array
    {
        return [
            'weightReturned.required' => 'Weigh it and enter the weight.',
            'weightLoss.required' => 'Enter the loss, or 0 if nothing was lost.',
            'returnDate.before_or_equal' => 'The return date can not be in the future.',
            'centreId.required_if' => 'Choose the hallmarking centre it is going to.',
            'huid.regex' => 'A HUID is 6 letters or digits.',
        ];
    }

    private function fieldNames(): array
    {
        return ['weightReturned' => 'weight', 'weightLoss' => 'weight loss', 'huid' => 'HUID', 'centreId' => 'hallmarking centre'];
    }

    public function confirm(): void
    {
        match ($this->tab) {
            'customer' => $this->returnCustomerMaterial(),
            'raw' => $this->returnRawMaterial(),
            default => $this->returnPiece(),
        };
    }

    // ------------------------------------------------------------------ pieces

    private function returnPiece(): void
    {
        $this->validate($this->weightRules(), $this->validationMessages(), $this->fieldNames());

        $out = Movement::openItemDispatches(['karigar'])->with('item')->find($this->selectedId);
        if (! $out || ! $out->item) {
            $this->dispatch('toast', message: 'That piece is no longer out with a karigar.', type: 'warning');
            $this->clearSelection();
            return;
        }
        $item = $out->item;

        DB::transaction(function () use ($out, $item) {
            Movement::create([
                ...$this->doneByAttributes(),
                'trackable_type' => 'item',
                'trackable_id' => $item->id,
                'movement_type' => 'karigar_in',
                'purpose_label' => $out->purpose_label,
                'user_id' => Auth::id(),
                'counterparty' => $out->counterparty,
                'actual_return' => $this->returnDate,
                'weight_at_return' => $this->weightReturned,
                'weight_loss' => $this->weightLoss,
                'note' => $this->note ?: null,
            ]);

            $this->chainOrHold($item, (float) $this->weightReturned);
        });

        $this->dispatch('toast', message: $this->toHallmark
            ? "{$item->label} is back and on its way to hallmarking."
            : "{$item->label} is back. It waits in Pending Review until an admin confirms it.", type: 'success');
        $this->clearSelection();
    }

    // --------------------------------------------------------------- customer

    private function returnCustomerMaterial(): void
    {
        $rules = $this->weightRules();
        unset($rules['centreId'], $rules['hallmarkDue']);
        $this->validate($rules, $this->validationMessages(), $this->fieldNames());

        $job = CustomerMaterialJob::with('customer')->where('status', 'out')->find($this->selectedId);
        if (! $job) {
            $this->dispatch('toast', message: 'That job has already been returned.', type: 'warning');
            $this->clearSelection();
            return;
        }

        DB::transaction(function () use ($job) {
            $job->update([
                'weight_in' => $this->weightReturned,
                'weight_loss' => $this->weightLoss,
                'actual_return' => $this->returnDate,
                'status' => 'returned',
                'returned_by' => Auth::id(),
                'note' => $this->note ? trim(($job->note ? $job->note . ' | ' : '') . 'Return: ' . $this->note) : $job->note,
            ]);

            if ($this->notifyCustomer && $job->customer) {
                PendingNotification::create([
                    'customer_id' => $job->customer_id,
                    'type' => 'other',
                    'recipient_name' => $job->customer->name,
                    'recipient_phone' => $job->customer->phone,
                    'message' => "Your {$job->description} is back from the karigar and ready to collect.",
                    'status' => 'pending',
                    'related_type' => 'customer_material_job',
                    'related_id' => $job->id,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        $this->dispatch('toast', message: "{$job->customer?->name}'s material is back" . ($this->notifyCustomer ? '. A message is waiting in Messages.' : '.'), type: 'success');
        $this->clearSelection();
    }

    // -------------------------------------------------------------------- raw

    // Single piece per return: the requirements doc still has "does one
    // raw-material dispatch always split cleanly" open with the client, so
    // this doesn't build a multi-line splitter yet.
    private function returnRawMaterial(): void
    {
        $this->huid = strtoupper(trim($this->huid));

        $this->validate($this->weightRules() + [
            'category' => ['required', 'string', 'max:50'],
            'purity' => ['required', 'string', 'max:10'],
            'huid' => ['nullable', 'regex:/^[A-Z0-9]{6}$/', Rule::unique('items', 'huid_code')],
            'description' => ['nullable', 'string', 'max:100'],
        ], $this->validationMessages(), $this->fieldNames());

        $batch = KarigarRawBatch::with('vendor')->whereIn('status', ['dispatched', 'partially_returned'])->find($this->selectedId);
        if (! $batch) {
            $this->dispatch('toast', message: 'That batch has already been returned.', type: 'warning');
            $this->clearSelection();
            return;
        }

        $item = DB::transaction(function () use ($batch) {
            $item = Item::create([
                'metal' => $batch->metal,
                'huid_code' => $this->huid ?: null,
                'internal_code' => $this->huid ? null : Item::generateInternalCode(),
                'category' => trim($this->category),
                'purity' => trim($this->purity),
                'weight' => $this->weightReturned,
                'description' => $this->description ?: null,
                // Making charge isn't known at return time. The admin sets it
                // while reviewing the piece in Pending Review.
                'making_type' => 'flat_per_piece',
                'making_value' => 0,
                'source_karigar_batch_id' => $batch->id,
                'status' => 'pending_review',
            ]);

            // The new piece's history starts with where it came from.
            Movement::create([
                ...$this->doneByAttributes(),
                'trackable_type' => 'item',
                'trackable_id' => $item->id,
                'movement_type' => 'karigar_in',
                'purpose_label' => 'Made from raw batch #' . $batch->id,
                'user_id' => Auth::id(),
                'counterparty' => $batch->vendor?->name,
                'actual_return' => $this->returnDate,
                'weight_at_dispatch' => $batch->weight_out,
                'weight_at_return' => $this->weightReturned,
                'weight_loss' => $this->weightLoss,
                'note' => $this->note ?: null,
            ]);

            $batch->update([
                'status' => 'returned',
                'actual_return' => $this->returnDate,
                'weight_returned' => $this->weightReturned,
                'weight_loss' => $this->weightLoss,
                'returned_by' => Auth::id(),
            ]);

            $this->chainOrHold($item, (float) $this->weightReturned);

            return $item;
        });

        $this->dispatch('toast', message: "New piece {$item->label} created" . ($this->toHallmark ? ' and sent to hallmarking.' : '. It waits in Pending Review.'), type: 'success');
        $this->clearSelection();
    }

    // #7: straight on to hallmarking, or #9: hold for admin review.
    private function chainOrHold(Item $item, float $weight): void
    {
        if (! $this->toHallmark) {
            $item->update(['status' => 'pending_review']);
            return;
        }

        $centre = Vendor::find($this->centreId);
        Movement::create([
                ...$this->doneByAttributes(),
            'trackable_type' => 'item',
            'trackable_id' => $item->id,
            'movement_type' => 'hallmark_out',
            'purpose_label' => 'Straight from karigar',
            'user_id' => Auth::id(),
            'counterparty' => $centre?->name,
            'expected_return' => $this->hallmarkDue ?: null,
            'weight_at_dispatch' => $weight,
        ]);
        $item->update(['status' => 'dispatched']);
    }

    // ------------------------------------------------------------------ render

    public function render()
    {
        $term = trim($this->search);

        $pieces = Movement::openItemDispatches(['karigar'])->with('item', 'user:id,name')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('counterparty', 'like', "%{$term}%")
                ->orWhereHas('item', fn ($i) => $i->where('huid_code', 'like', "%{$term}%")
                    ->orWhere('internal_code', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"))))
            ->orderByRaw('expected_return IS NULL, expected_return')->get();

        $customerJobs = CustomerMaterialJob::with(['customer:id,name,phone', 'vendor:id,name', 'user:id,name'])->where('status', 'out')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('description', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%"))))
            ->orderByRaw('expected_return IS NULL, expected_return')->get();

        $rawBatches = KarigarRawBatch::with(['vendor:id,name', 'user:id,name'])->whereIn('status', ['dispatched', 'partially_returned'])
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('id', ltrim($term, '#'))
                ->orWhere('purpose_label', 'like', "%{$term}%")
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$term}%"))))
            ->orderByRaw('expected_return IS NULL, expected_return')->get();

        $selected = match ($this->tab) {
            'customer' => $customerJobs->firstWhere('id', $this->selectedId),
            'raw' => $rawBatches->firstWhere('id', $this->selectedId),
            default => $pieces->firstWhere('id', $this->selectedId),
        };

        $sent = match ($this->tab) {
            'customer' => $selected?->weight_out,
            'raw' => $selected?->weight_out,
            default => $selected?->weight_at_dispatch,
        };

        return view('livewire.movement.karigar-return', [
            'pieces' => $pieces,
            'customerJobs' => $customerJobs,
            'rawBatches' => $rawBatches,
            'counts' => [
                'pieces' => Movement::openItemDispatches(['karigar'])->count(),
                'customer' => CustomerMaterialJob::where('status', 'out')->count(),
                'raw' => KarigarRawBatch::whereIn('status', ['dispatched', 'partially_returned'])->count(),
            ],
            'selected' => $selected,
            'sentWeight' => $sent !== null ? (float) $sent : null,
            'scaleDiff' => $sent !== null && is_numeric($this->weightReturned) ? round((float) $sent - (float) $this->weightReturned, 3) : null,
            'centres' => Vendor::where('type', 'hallmark_center')->orderBy('name')->get(),
            'categories' => $this->tab === 'raw' ? Item::query()->distinct()->orderBy('category')->pluck('category') : collect(),
            'purities' => $this->tab === 'raw' && $selected ? (\App\Livewire\Stock\ItemForm::PURITIES[$selected->metal] ?? []) : [],
        ])->layout('components.layouts.app', ['title' => 'Karigar Return · Radharani Jewellery']);
    }
}
