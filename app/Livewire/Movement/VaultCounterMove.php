<?php
namespace App\Livewire\Movement;

use App\Models\Movement\Movement;
use App\Models\Stock\Item;
use App\Support\StockLookup;
use App\Support\Trackables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The morning / evening screen. Staff pick a direction, scan everything that
 * is moving (pieces, packets or whole boxes) into a tray, then confirm once.
 * Each tray line becomes its own vault_out / vault_in movement row.
 * In the evening the right-hand list shows what is still recorded on the
 * counter, so anything not scanned back is visible before confirming.
 */
class VaultCounterMove extends Component
{
    use \App\Livewire\Movement\Concerns\HasDoneBy;

    #[Url(except: 'to_counter')]
    public string $direction = 'to_counter'; // to_counter | to_vault

    /** @var array<int, array{type: string, id: int, code: string, detail: string, weight: ?float, warning: ?string}> */
    public array $tray = [];

    /** @var array{tone: string, code: string, message: string}|null */
    public ?array $feedback = null;

    public function setDirection(string $direction): void
    {
        if (! in_array($direction, ['to_counter', 'to_vault'], true) || $direction === $this->direction) {
            return;
        }

        $this->direction = $direction;
        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    // The code arrives as an argument (the field is cleared in the browser on
    // Enter), so fast consecutive scans queue up instead of overwriting each other.
    public function scan(string $raw): void
    {
        // Several boxes at once: codes pasted or scanned together, separated by spaces, commas or new lines.
        $codes = preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        if (count($codes) > 1) {
            foreach ($codes as $one) {
                $this->scanOne($one);
            }
            $this->dispatch('scan-ready');

            return;
        }

        $this->scanOne($raw);
    }

    private function scanOne(string $raw): void
    {
        $code = StockLookup::normalize($raw);
        if ($code === '') {
            return;
        }

        $this->dispatch('scan-ready');

        [$type, $model] = $this->resolve($code);
        if (! $model) {
            $this->feedback('error', $code, 'Nothing found with this code.');
            return;
        }

        $label = $type === 'item' ? $model->label : $model->code;

        if (collect($this->tray)->contains(fn ($t) => $t['type'] === $type && $t['id'] === $model->id)) {
            $this->feedback('info', $label, 'Already in the tray.');
            return;
        }

        if ($type === 'item' && ($blocked = $this->blockedReason($model))) {
            $this->feedback('error', $label, $blocked);
            return;
        }

        $last = $this->lastVaultMove($type, $model->id);
        $warning = null;

        if ($this->direction === 'to_counter' && $last?->movement_type === 'vault_out') {
            $this->feedback('info', $label, 'Already on the counter since ' . $last->created_at->format('g:i a') . '.');
            return;
        }
        if ($this->direction === 'to_vault' && $last?->movement_type !== 'vault_out') {
            $warning = 'Not recorded as going to the counter';
        }

        $desc = Trackables::describe([$type => collect([$model->id => $model])] + ['item' => collect(), 'packet' => collect(), 'box' => collect()], $type, $model->id);

        array_unshift($this->tray, [
            'type' => $type,
            'id' => $model->id,
            'code' => $label,
            'detail' => $desc['detail'],
            'weight' => $type === 'item' ? (float) $model->weight : null,
            'warning' => $warning,
        ]);

        $this->feedback($warning ? 'warning' : 'success', $label, $warning ? "Added. {$warning} today." : 'Added to the tray.');
    }

    // One click from the "still on the counter" list: record the return straight away.
    public function returnNow(string $type, int $id): void
    {
        if (! in_array($type, ['item', 'packet', 'box'], true)) {
            return;
        }

        $last = $this->lastVaultMove($type, $id);
        if ($last?->movement_type !== 'vault_out') {
            $this->dispatch('toast', message: 'That is already recorded as back in the vault.', type: 'info');

            return;
        }

        $model = match ($type) {
            'item' => Item::find($id),
            'packet' => \App\Models\Stock\Packet::find($id),
            'box' => \App\Models\Stock\Box::find($id),
        };
        if (! $model) {
            return;
        }

        Movement::create([
                ...$this->doneByAttributes(),
            'trackable_type' => $type,
            'trackable_id' => $id,
            'movement_type' => 'vault_in',
            'purpose_label' => 'Closing stock',
            'user_id' => Auth::id(),
            'weight_at_dispatch' => $type === 'item' ? (float) $model->weight : null,
        ]);

        $this->dispatch('toast', message: ($type === 'item' ? $model->label : $model->code) . ' returned to the vault.', type: 'success');
    }

    public function removeFromTray(int $index): void
    {
        unset($this->tray[$index]);
        $this->tray = array_values($this->tray);
        $this->dispatch('scan-ready');
    }

    public function clearTray(): void
    {
        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    public function confirmMove(): void
    {
        if (! $this->tray) {
            return;
        }

        $type = $this->direction === 'to_counter' ? 'vault_out' : 'vault_in';
        $skipped = [];

        DB::transaction(function () use ($type, &$skipped) {
            foreach ($this->tray as $t) {
                // Re-check at confirm time: a piece may have been sold or dispatched meanwhile.
                if ($t['type'] === 'item' && ($item = Item::find($t['id'])) && $this->blockedReason($item)) {
                    $skipped[] = $t['code'];
                    continue;
                }

                Movement::create([
                ...$this->doneByAttributes(),
                    'trackable_type' => $t['type'],
                    'trackable_id' => $t['id'],
                    'movement_type' => $type,
                    'purpose_label' => $type === 'vault_out' ? 'Counter display' : 'Closing stock',
                    'user_id' => Auth::id(),
                    'weight_at_dispatch' => $t['weight'],
                ]);
            }
        });

        $moved = count($this->tray) - count($skipped);
        $where = $this->direction === 'to_counter' ? 'sent to the counter' : 'returned to the vault';
        $this->dispatch('toast', message: "{$moved} " . \Illuminate\Support\Str::plural('entry', $moved) . " {$where}.", type: 'success');
        if ($skipped) {
            $this->dispatch('toast', message: 'Skipped ' . implode(', ', $skipped) . ': no longer available.', type: 'warning');
        }

        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    private function resolve(string $code): array
    {
        if ($item = StockLookup::item($code)) {
            return ['item', $item];
        }
        if ($found = StockLookup::container($code)) {
            return [$found['type'], $found['model']];
        }

        return [null, null];
    }

    private function blockedReason(Item $item): ?string
    {
        return match ($item->status) {
            'sold' => 'This piece is sold.',
            'dispatched' => 'This piece is out with a karigar, hallmarking or photo trip.',
            'pending_review' => 'This piece is waiting for admin review.',
            default => null,
        };
    }

    private function lastVaultMove(string $type, int $id): ?Movement
    {
        return Movement::where('trackable_type', $type)->where('trackable_id', $id)
            ->whereIn('movement_type', Movement::PAIRS['vault'])
            ->latest('id')->first();
    }

    private function feedback(string $tone, string $code, string $message): void
    {
        $this->feedback = compact('tone', 'code', 'message');
    }

    public function render()
    {
        $latest = Movement::query()->selectRaw('MAX(id)')
            ->whereIn('movement_type', Movement::PAIRS['vault'])
            ->groupBy('trackable_type', 'trackable_id');

        $onCounter = Movement::whereIn('id', $latest)->where('movement_type', 'vault_out')
            ->with('user:id,name')->orderByDesc('id')->get();

        $today = Movement::whereIn('movement_type', Movement::PAIRS['vault'])
            ->whereDate('created_at', today())
            ->with('user:id,name')->orderByDesc('id')->limit(60)->get();

        $loaded = Trackables::load($onCounter->concat($today));

        $inTray = collect($this->tray)->map(fn ($t) => $t['type'] . ':' . $t['id'])->flip();

        $counterRows = $onCounter->map(function ($m) use ($loaded, $inTray) {
            $d = Trackables::describe($loaded, $m->trackable_type, $m->trackable_id);
            $status = $m->trackable_type === 'item' ? $d['model']?->status : null;

            return $d + [
                'type' => $m->trackable_type,
                'since' => $m->created_at,
                'by' => $m->user?->name,
                'sold' => in_array($status, ['sold', 'reserved'], true),
                'scanned' => $inTray->has($m->trackable_type . ':' . $m->trackable_id),
            ];
        });

        // A box or packet with sold pieces inside shows it on the row.
        $counterRows = $counterRows->map(function ($r) {
            $r['soldInside'] = match ($r['type']) {
                'packet' => Item::where('packet_id', $r['model']?->id)->whereIn('status', ['sold', 'reserved'])->count(),
                'box' => Item::whereIn('packet_id', \App\Models\Stock\Packet::where('box_id', $r['model']?->id)->select('id'))
                    ->whereIn('status', ['sold', 'reserved'])->count(),
                default => 0,
            };

            return $r;
        });

        $expected = $counterRows->where('sold', false);

        // Today's out/in times side by side, one row per box / packet / piece.
        $pairs = $today->groupBy(fn ($m) => $m->trackable_type . ':' . $m->trackable_id)->map(function ($rows) use ($loaded) {
            $first = $rows->first();

            return Trackables::describe($loaded, $first->trackable_type, $first->trackable_id) + [
                'out' => $rows->where('movement_type', 'vault_out')->sortBy('id')->first(),
                'in' => $rows->where('movement_type', 'vault_in')->sortByDesc('id')->first(),
                'key' => $first->trackable_type . ':' . $first->trackable_id,
            ];
        })->values();

        return view('livewire.movement.vault-counter-move', [
            'counterRows' => $counterRows,
            'expectedCount' => $expected->count(),
            'missingCount' => $expected->where('scanned', false)->count(),
            'pairs' => $pairs,
            'movesToday' => $today->count(),
            'byUser' => $counterRows->where('sold', false)->groupBy('by')->map->count()->sortDesc(),
            'boxesOut' => $counterRows->where('type', 'box')->where('sold', false)->count(),
            'stats' => [
                'sent' => $today->where('movement_type', 'vault_out')->count(),
                'returned' => $today->where('movement_type', 'vault_in')->count(),
            ],
            'trayWeight' => collect($this->tray)->sum('weight'),
        ])->layout('components.layouts.app', ['title' => 'Vault ↔ Counter · Radharani Jewellery']);
    }
}
