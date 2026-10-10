<?php
namespace App\Livewire\Exchange;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Exchange\RefineryBatch;
use App\Services\PhotoCompressionService;
use Livewire\Component;
use Livewire\WithFileUploads;

class RefineryBatchSend extends Component
{
    use WithFileUploads, WithDataTable;

    public string $metal = 'gold';
    public float $weight = 0;
    public $photo = null;

    protected function sortableColumns(): array
    {
        return [
            'id' => 'id',
            'weight' => 'weight',
            'status' => 'status',
            'sent' => 'sent_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['sent', 'desc'];
    }

    public function submit()
    {
        $this->validate([
            'metal' => 'required|in:gold,silver,platinum,titanium',
            'weight' => 'required|numeric|min:0.001',
            'photo' => 'required|image|max:5120',
        ]);

        $path = app(PhotoCompressionService::class)->store($this->photo, 'refinery');

        $batch = RefineryBatch::create([
            'metal' => $this->metal,
            'weight' => $this->weight,
            'photo_path' => $path,
            'status' => 'sent',
            'sent_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $this->reset(['weight', 'photo']);
        $this->dispatch('toast', message: "Batch #{$batch->id} of {$batch->weight}g recorded for send.", type: 'success');
    }

    public function render()
    {
        $query = RefineryBatch::query()
            ->when($this->search, fn ($q) => $q->where('id', 'like', "%{$this->search}%"));

        $query = $this->applySorting($query)->orderByDesc('id');

        return view('livewire.exchange.refinery-batch-send', [
            'batches' => $query->paginate($this->perPageValue()),
            'stats' => [
                'outstanding' => RefineryBatch::where('status', 'sent')->count(),
                'outstandingWeight' => (float) RefineryBatch::where('status', 'sent')->sum('weight'),
                'returned' => RefineryBatch::where('status', 'returned')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Refinery — Send — Radharani Jewellery']);
    }
}
