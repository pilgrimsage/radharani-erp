<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\StockAudit;
use App\Support\StockLookup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Stock audit (8 Oct change list, 4.6): take a box, scan every piece in it. Pieces the system
 * expects and you scanned are present; expected but not scanned are missing; scanned but not
 * expected are extra. Saving keeps the result as an audit record (who, when, discrepancies).
 */
class StockAuditPage extends Component
{
    #[Url(as: 'box', except: null)]
    public ?int $boxId = null;

    /** @var array<int, string> item_id => code, for expected pieces that were scanned */
    public array $present = [];

    /** @var array<int, array{item_id: ?int, code: string}> */
    public array $extra = [];

    public ?array $feedback = null;
    public string $note = '';
    public ?int $viewAuditId = null;
    public bool $showView = false;

    public function start(int $id): void
    {
        $this->boxId = Box::findOrFail($id)->id;
        $this->reset(['present', 'extra', 'feedback', 'note']);
        $this->dispatch('scan-ready');
    }

    public function cancel(): void
    {
        $this->reset(['boxId', 'present', 'extra', 'feedback', 'note']);
    }

    // Pieces the system believes are in this box right now (in stock, in any of its packets).
    private function expected()
    {
        return Item::whereIn('packet_id', Box::findOrFail($this->boxId)->packets()->select('id'))
            ->where('status', 'in_stock')->orderBy('category')->get();
    }

    public function scan(string $raw): void
    {
        $raw = trim($raw);
        if (! $this->boxId || $raw === '') {
            return;
        }
        $this->dispatch('scan-ready');

        $item = StockLookup::item($raw);
        $expected = $this->expected()->keyBy('id');

        if ($item && $expected->has($item->id)) {
            if (isset($this->present[$item->id])) {
                $this->feedback = ['tone' => 'info', 'code' => $item->label, 'message' => 'Already scanned.'];

                return;
            }
            $this->present[$item->id] = $item->label;
            $this->feedback = ['tone' => 'success', 'code' => $item->label, 'message' => 'Present.'];

            return;
        }

        $code = $item?->label ?? strtoupper($raw);
        foreach ($this->extra as $e) {
            if ($e['code'] === $code) {
                $this->feedback = ['tone' => 'info', 'code' => $code, 'message' => 'Already marked extra.'];

                return;
            }
        }
        array_unshift($this->extra, ['item_id' => $item?->id, 'code' => $code]);
        $this->feedback = ['tone' => 'warning', 'code' => $code, 'message' => $item ? 'Extra: the system has it somewhere else.' : 'Extra: this code is not in the system.'];
    }

    public function unmarkExtra(int $index): void
    {
        unset($this->extra[$index]);
        $this->extra = array_values($this->extra);
    }

    public function save()
    {
        if (! $this->boxId) {
            return;
        }
        $expected = $this->expected();
        $missing = $expected->reject(fn ($i) => isset($this->present[$i->id]));

        $audit = DB::transaction(function () use ($expected, $missing) {
            $audit = StockAudit::create([
                'box_id' => $this->boxId,
                'user_id' => Auth::id(),
                'expected_count' => $expected->count(),
                'present_count' => count($this->present),
                'missing_count' => $missing->count(),
                'extra_count' => count($this->extra),
                'note' => trim($this->note) ?: null,
            ]);
            $lines = [];
            foreach ($this->present as $id => $code) {
                $lines[] = ['item_id' => $id, 'code' => $code, 'result' => 'present'];
            }
            foreach ($missing as $i) {
                $lines[] = ['item_id' => $i->id, 'code' => $i->label, 'result' => 'missing'];
            }
            foreach ($this->extra as $e) {
                $lines[] = ['item_id' => $e['item_id'], 'code' => $e['code'], 'result' => 'extra'];
            }
            $audit->lines()->createMany($lines);

            return $audit;
        });

        $this->cancel();
        $this->viewAuditId = $audit->id;
        $this->showView = true;
        $this->dispatch('toast', message: $audit->clean ? 'Audit saved: everything matched.' : 'Audit saved with ' . ($audit->missing_count + $audit->extra_count) . ' difference(s).', type: $audit->clean ? 'success' : 'warning');
    }

    public function view(int $id): void
    {
        $this->viewAuditId = $id;
        $this->showView = true;
    }

    public function render()
    {
        $expected = $this->boxId ? $this->expected() : collect();
        $viewing = $this->viewAuditId ? StockAudit::with(['box:id,code', 'user:id,name', 'lines'])->find($this->viewAuditId) : null;

        return view('livewire.stock.stock-audit', [
            'box' => $this->boxId ? Box::find($this->boxId) : null,
            'expected' => $expected,
            'missing' => $expected->reject(fn ($i) => isset($this->present[$i->id])),
            'boxes' => $this->boxId ? collect() : Box::withMax('audits', 'created_at')->orderBy('code')->get(),
            'history' => StockAudit::with(['box:id,code', 'user:id,name'])->latest('id')->limit(15)->get(),
            'viewing' => $viewing,
        ])->layout('components.layouts.app', ['title' => 'Stock audit · Radharani Jewellery ERP']);
    }
}
