<?php
namespace App\Livewire\Movement;

use App\Livewire\Movement\Concerns\PicksItems;
use App\Models\Movement\Movement;
use App\Models\Stock\Item;
use App\Services\PhotoCompressionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Any other short trip out of the shop: photos for the website, an
 * appraisal, an exhibition, a customer taking a piece home to decide.
 * Photography trips are photo_out / photo_in; everything else is
 * custom_out / custom_in, with the reason in purpose_label.
 */
class CustomPurposeMove extends Component
{
    use PicksItems;
    use WithFileUploads;

    // Reason => which movement pair it is recorded under.
    public const PURPOSES = [
        'Photography' => 'photo',
        'Website shoot' => 'photo',
        'Appraisal' => 'custom',
        'Exhibition' => 'custom',
        'Customer approval' => 'custom',
        'Other' => 'custom',
    ];

    #[Url(except: 'out')]
    public string $direction = 'out'; // out | in

    // out
    public string $purpose = 'Photography';
    public string $otherPurpose = '';
    public string $counterparty = '';
    public ?string $expectedReturn = null;

    // in
    public string $search = '';
    /** @var array<int, string> open *_out movement ids being returned */
    public array $returning = [];

    // both
    public string $note = '';
    public $photo = null;

    public function mount(): void
    {
        $this->expectedReturn = today()->addDay()->toDateString();
    }

    protected function pickableStatuses(): array
    {
        return ['in_stock'];
    }

    public function setDirection(string $direction): void
    {
        if (in_array($direction, ['out', 'in'], true)) {
            $this->direction = $direction;
            $this->resetValidation();
            $this->reset(['photo', 'note', 'returning']);
        }
    }

    // Returning: each scanned tag that is out ticks its row (the camera stays open for the next one).
    public function updatedSearch(): void
    {
        if ($this->direction !== 'in') {
            return;
        }
        $item = \App\Support\StockLookup::item($this->search);
        $open = $item ? Movement::openItemDispatches(['photo', 'custom'])->where('trackable_id', $item->id)->first() : null;

        if ($open) {
            $this->search = '';
            if (! in_array((string) $open->id, $this->returning, true)) {
                $this->returning[] = (string) $open->id;
            }
            $this->dispatch('toast', message: "{$item->label} ticked as back.", type: 'success');
        }
    }

    public function removePhoto(): void
    {
        $this->reset('photo');
    }

    public function submitOut(): void
    {
        $this->validate([
            'basket' => ['required', 'array', 'min:1'],
            'purpose' => ['required', Rule::in(array_keys(self::PURPOSES))],
            'otherPurpose' => ['required_if:purpose,Other', 'nullable', 'string', 'max:50'],
            'counterparty' => ['nullable', 'string', 'max:100'],
            'expectedReturn' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'photo.required' => 'Add a photo of what is going out.',
            'basket.required' => 'Add at least one piece.',
            'otherPurpose.required_if' => 'Say what it is going out for.',
        ]);

        $label = $this->purpose === 'Other' ? trim($this->otherPurpose) : $this->purpose;
        $type = self::PURPOSES[$this->purpose] . '_out';
        $items = Item::whereIn('id', $this->basket)->where('status', 'in_stock')->get();

        // Rule 4: photos only ever go through PhotoCompressionService. One proof photo covers the whole trip.
        $photoPath = $this->photo ? app(PhotoCompressionService::class)->store($this->photo, 'movements/photo') : null;

        DB::transaction(function () use ($items, $type, $label, $photoPath) {
            foreach ($items as $item) {
                Movement::create([
                    'trackable_type' => 'item',
                    'trackable_id' => $item->id,
                    'movement_type' => $type,
                    'purpose_label' => $label,
                    'user_id' => Auth::id(),
                    'counterparty' => $this->counterparty ?: null,
                    'expected_return' => $this->expectedReturn ?: null,
                    'weight_at_dispatch' => $item->weight,
                    'photo_path' => $photoPath,
                    'note' => $this->note ?: null,
                ]);
                $item->update(['status' => 'dispatched']);
            }
        });

        $this->dispatch('toast', message: "{$items->count()} " . \Illuminate\Support\Str::plural('piece', $items->count()) . " out for {$label}.", type: 'success');
        $this->reset(['basket', 'note', 'photo', 'counterparty', 'otherPurpose']);
    }

    public function submitReturn(): void
    {
        $this->validate([
            'returning' => ['required', 'array', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:5120'],
        ], [
            'returning.required' => 'Tick the pieces that came back.',
            'photo.required' => 'Add a photo of what came back.',
        ]);

        $open = Movement::openItemDispatches(['photo', 'custom'])->with('item')
            ->whereIn('id', array_map('intval', $this->returning))->get();

        $photoPath = $this->photo ? app(PhotoCompressionService::class)->store($this->photo, 'movements/photo') : null;

        DB::transaction(function () use ($open, $photoPath) {
            foreach ($open as $out) {
                Movement::create([
                    'trackable_type' => 'item',
                    'trackable_id' => $out->trackable_id,
                    'movement_type' => str_replace('_out', '_in', $out->movement_type),
                    'purpose_label' => $out->purpose_label,
                    'user_id' => Auth::id(),
                    'counterparty' => $out->counterparty,
                    'actual_return' => today(),
                    'photo_path' => $photoPath,
                    'note' => $this->note ?: null,
                ]);
                $out->item?->update(['status' => 'in_stock']);
            }
        });

        $this->dispatch('toast', message: "{$open->count()} " . \Illuminate\Support\Str::plural('piece', $open->count()) . ' back in stock.', type: 'success');
        $this->reset(['returning', 'note', 'photo']);
    }

    public function render()
    {
        $term = trim($this->search);

        $out = Movement::openItemDispatches(['photo', 'custom'])->with('item', 'user:id,name')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('purpose_label', 'like', "%{$term}%")
                ->orWhere('counterparty', 'like', "%{$term}%")
                ->orWhereHas('item', fn ($i) => $i->where('huid_code', 'like', "%{$term}%")
                    ->orWhere('internal_code', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"))))
            ->orderByRaw('expected_return IS NULL, expected_return')->get();

        return view('livewire.movement.custom-purpose-move', [
            'basketItems' => $this->direction === 'out' ? $this->basketItems() : collect(),
            'pickResults' => $this->direction === 'out' ? $this->pickResults() : collect(),
            'out' => $out,
            'outCount' => Movement::openItemDispatches(['photo', 'custom'])->count(),
            'overdue' => $out->filter->is_overdue->count(),
        ])->layout('components.layouts.app', ['title' => 'Photo / Custom Purpose · Radharani Jewellery']);
    }
}
