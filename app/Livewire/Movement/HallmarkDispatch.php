<?php
namespace App\Livewire\Movement;

use App\Livewire\Movement\Concerns\PicksItems;
use App\Models\Movement\Movement;
use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

// Send one or more pieces to a hallmarking centre: one hallmark_out movement per piece.
class HallmarkDispatch extends Component
{
    use PicksItems, \App\Livewire\Movement\Concerns\HasDoneBy;

    public ?int $centreId = null;
    public ?string $expectedReturn = null;
    public string $note = '';

    public function mount(): void
    {
        $this->expectedReturn = today()->addDays(3)->toDateString();
        // Pre-select when there is only one centre, which is the usual case.
        $centres = Vendor::where('type', 'hallmark_center')->pluck('id');
        $this->centreId = $centres->count() === 1 ? $centres->first() : null;
    }

    // New pieces waiting for admin review often go for hallmarking first.
    protected function pickableStatuses(): array
    {
        return ['in_stock', 'pending_review'];
    }

    public function submit(): void
    {
        $this->validate([
            'basket' => ['required', 'array', 'min:1'],
            'centreId' => ['required', Rule::exists('vendors', 'id')->where('type', 'hallmark_center')],
            'expectedReturn' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'basket.required' => 'Add at least one piece.',
            'centreId.required' => 'Choose the hallmarking centre.',
        ]);

        $centre = Vendor::findOrFail($this->centreId);
        $items = Item::whereIn('id', $this->basket)->whereIn('status', $this->pickableStatuses())->get();

        DB::transaction(function () use ($items, $centre) {
            foreach ($items as $item) {
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
                    'note' => $this->note ?: null,
                ]);
                $item->update(['status' => 'dispatched']);
            }
        });

        $this->dispatch('toast', message: "{$items->count()} " . \Illuminate\Support\Str::plural('piece', $items->count()) . " sent to {$centre->name}.", type: 'success');
        $this->reset(['basket', 'note']);
    }

    public function render()
    {
        $atCentre = Movement::openItemDispatches(['hallmark'])->with('item')
            ->orderByRaw('expected_return IS NULL, expected_return')->get();

        return view('livewire.movement.hallmark-dispatch', [
            'centres' => Vendor::where('type', 'hallmark_center')->orderBy('name')->get(),
            'basketItems' => $this->basketItems(),
            'pickResults' => $this->pickResults(),
            'atCentre' => $atCentre,
            'overdue' => $atCentre->filter->is_overdue->count(),
            // Pieces over 2 g of gold without a HUID are the usual candidates.
            'suggested' => Item::where('status', 'in_stock')->where('metal', 'gold')->whereNull('huid_code')
                ->where('weight', '>', 2)->whereNotIn('id', $this->basket)->orderByDesc('weight')->limit(6)->get(),
        ])->layout('components.layouts.app', ['title' => 'Hallmarking Dispatch · Radharani Jewellery']);
    }
}
