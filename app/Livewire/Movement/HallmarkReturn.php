<?php
namespace App\Livewire\Movement;

use App\Models\Movement\Movement;
use App\Models\Stock\Item;
use App\Models\User;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * A piece back from hallmarking: record the HUID the centre assigned (or use
 * the shop's own internal code when none was given), who did the tagging
 * (#8, free text, may be centre staff) and the weight lost, typed by hand (#6).
 * The piece then waits in Pending Review (#9).
 */
class HallmarkReturn extends Component
{
    use \App\Livewire\Movement\Concerns\HasDoneBy;

    public string $search = '';
    public ?int $selectedId = null; // the open hallmark_out movement

    public string $idMode = 'huid'; // huid | internal
    public string $huidCode = '';
    public string $taggedBy = '';

    public $weightReturned = '';
    public $weightLoss = '';
    public ?string $returnDate = null;
    public string $note = '';

    public function mount(): void
    {
        $this->returnDate = today()->toDateString();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->resetValidation();
        $this->reset(['huidCode', 'weightReturned', 'weightLoss', 'note']);
        $this->returnDate = today()->toDateString();
        $this->idMode = 'huid';

        // A piece that already had a HUID (re-hallmarked) keeps it unless changed.
        $item = Movement::with('item')->find($id)?->item;
        if ($item?->huid_code) {
            $this->huidCode = $item->huid_code;
        }
    }

    // A scanned tag that matches a piece at hallmarking opens it straight away.
    public function updatedSearch(): void
    {
        $item = StockLookup::item($this->search);
        $open = $item ? Movement::openItemDispatches(['hallmark'])->where('trackable_id', $item->id)->first() : null;

        if ($open) {
            $this->search = '';
            $this->select($open->id);
        }
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->resetValidation();
    }

    public function confirm(): void
    {
        $out = Movement::openItemDispatches(['hallmark'])->with('item')->find($this->selectedId);
        if (! $out || ! $out->item) {
            $this->dispatch('toast', message: 'That piece is no longer at hallmarking.', type: 'warning');
            $this->clearSelection();
            return;
        }
        $item = $out->item;

        $this->huidCode = $this->idMode === 'huid' ? strtoupper(trim($this->huidCode)) : '';
        // A new HUID must be the 6-character BIS format; the piece's existing HUID is accepted as it is.
        $huidRules = $this->idMode !== 'huid' ? ['nullable'] : array_filter([
            'required',
            $this->huidCode !== $item->huid_code ? 'regex:/^[A-Z0-9]{6}$/' : null,
            Rule::unique('items', 'huid_code')->ignore($item->id),
        ]);

        $this->validate([
            'idMode' => ['required', Rule::in(['huid', 'internal'])],
            'huidCode' => $huidRules,
            'taggedBy' => ['required', 'string', 'max:100'],
            'weightReturned' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'weightLoss' => ['required', 'numeric', 'min:0', 'max:99999'],
            'returnDate' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'huidCode.required' => 'Enter the HUID from the centre, or choose the shop code instead.',
            'huidCode.regex' => 'A HUID is 6 letters or digits.',
            'huidCode.unique' => 'Another piece already has this HUID.',
            'taggedBy.required' => 'Enter who did the tagging.',
            'weightReturned.required' => 'Weigh it and enter the weight.',
            'weightLoss.required' => 'Enter the loss, or 0 if nothing was lost.',
            'returnDate.before_or_equal' => 'The return date can not be in the future.',
        ], ['weightReturned' => 'weight', 'weightLoss' => 'weight loss']);

        DB::transaction(function () use ($out, $item) {
            if ($this->idMode === 'huid') {
                $item->huid_code = $this->huidCode;
            } elseif (! $item->internal_code && ! $item->huid_code) {
                // One active ID per piece: only create a shop code when it has none at all.
                $item->internal_code = Item::generateInternalCode();
            }
            // #9: waits for an admin, never straight back to in_stock.
            $item->status = 'pending_review';
            $item->save();

            Movement::create([
                ...$this->doneByAttributes(),
                'trackable_type' => 'item',
                'trackable_id' => $item->id,
                'movement_type' => 'hallmark_in',
                'purpose_label' => $out->purpose_label,
                'user_id' => Auth::id(),
                'counterparty' => $out->counterparty,
                'actual_return' => $this->returnDate,
                'weight_at_return' => $this->weightReturned,
                'weight_loss' => $this->weightLoss,
                'tagged_by' => $this->taggedBy,
                'note' => $this->note ?: null,
            ]);
        });

        $this->dispatch('toast', message: "{$item->label} is back from hallmarking. It waits in Pending Review.", type: 'success');
        $this->reset(['selectedId', 'huidCode', 'weightReturned', 'weightLoss', 'note']);
    }

    public function render()
    {
        $term = trim($this->search);

        $open = Movement::openItemDispatches(['hallmark'])->with('item', 'user:id,name')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('counterparty', 'like', "%{$term}%")
                ->orWhereHas('item', fn ($i) => $i->where('huid_code', 'like', "%{$term}%")
                    ->orWhere('internal_code', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"))))
            ->orderByRaw('expected_return IS NULL, expected_return')->get();

        $selected = $open->firstWhere('id', $this->selectedId);
        $sent = $selected?->weight_at_dispatch !== null ? (float) $selected->weight_at_dispatch : null;

        return view('livewire.movement.hallmark-return', [
            'open' => $open,
            'selected' => $selected,
            'sentWeight' => $sent,
            'scaleDiff' => $sent !== null && is_numeric($this->weightReturned) ? round($sent - (float) $this->weightReturned, 3) : null,
            'taggers' => User::where('is_active', true)->orderBy('name')->pluck('name')
                ->when($selected?->counterparty, fn ($c) => $c->prepend($selected->counterparty))->unique()->values(),
        ])->layout('components.layouts.app', ['title' => 'Hallmarking Return · Radharani Jewellery']);
    }
}
