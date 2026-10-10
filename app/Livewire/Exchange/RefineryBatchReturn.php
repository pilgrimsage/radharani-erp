<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\ExchangeDeductionPreset;
use App\Models\Exchange\RefineryBatch;
use Livewire\Component;

/**
 * Simple weight-out → refined-weight/purity-in round trip. Since more than
 * one batch can be outstanding (status = 'sent') at once, staff pick which
 * one this return is for instead of the component silently guessing —
 * defaults to the oldest outstanding batch (FIFO: whichever was sent
 * first is presumed back first), but any outstanding batch can be picked.
 */
class RefineryBatchReturn extends Component
{
    use WithDataTable;

    public ?int $batchId = null;
    public $refinedWeight = '';
    public $refinedPurity = '';

    protected function sortableColumns(): array
    {
        return [
            'id' => 'id',
            'weight' => 'refined_weight',
            'purity' => 'refined_purity',
            'returned' => 'returned_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['returned', 'desc'];
    }

    public function mount()
    {
        $this->batchId = RefineryBatch::where('status', 'sent')->oldest('sent_at')->value('id');
    }

    public function selectBatch(int $batchId): void
    {
        $this->batchId = $batchId;
    }

    // Weight, purity and the deduction give the resulting figure, the way the exchange works it out:
    // the pure metal in the refined weight, less the deduction set for that metal and carat.
    public function calculation(?RefineryBatch $batch = null): array
    {
        $batch ??= $this->batchId ? RefineryBatch::find($this->batchId) : null;
        if (! $batch || ! is_numeric($this->refinedWeight) || ! is_numeric($this->refinedPurity)) {
            return ['fine' => 0.0, 'percent' => 0.0, 'result' => 0.0];
        }
        $fine = (float) $this->refinedWeight * (float) $this->refinedPurity / 100;
        $percent = ExchangeDeductionPreset::percentFor($batch->metal, (float) $this->refinedPurity);

        return ['fine' => round($fine, 3), 'percent' => $percent, 'result' => round($fine * (1 - $percent / 100), 3)];
    }

    public function submit()
    {
        $this->validate([
            'batchId' => 'required|exists:refinery_batches,id',
            'refinedWeight' => 'required|numeric|min:0.001',
            'refinedPurity' => 'required|numeric|min:0|max:100',
        ]);

        $batch = RefineryBatch::where('status', 'sent')->findOrFail($this->batchId);

        $calc = $this->calculation($batch);

        $batch->update([
            'refined_weight' => $this->refinedWeight,
            'refined_purity' => $this->refinedPurity,
            'deduction_percent' => $calc['percent'],
            'result_weight' => $calc['result'],
            'status' => 'returned',
            'returned_at' => now(),
            'returned_by' => auth()->id(),
        ]);

        $this->dispatch('toast', message: "Return recorded for batch #{$batch->id}: {$calc['result']} g after the deduction.", type: 'success');
        $this->reset(['refinedWeight', 'refinedPurity']);
        $this->batchId = RefineryBatch::where('status', 'sent')->oldest('sent_at')->value('id');
    }

    public function render()
    {
        $query = RefineryBatch::query()
            ->with('returner:id,name')
            ->where('status', 'returned')
            ->when($this->search, fn ($q) => $q->where('id', 'like', "%{$this->search}%"));

        $query = $this->applySorting($query)->orderByDesc('id');

        return view('livewire.exchange.refinery-batch-return', [
            'calc' => $this->calculation(),
            'outstandingBatches' => RefineryBatch::where('status', 'sent')->oldest('sent_at')->get(),
            'returnedBatches' => $query->paginate($this->perPageValue()),
        ])->layout('components.layouts.app', ['title' => 'Refinery — Return — Radharani Jewellery']);
    }
}
