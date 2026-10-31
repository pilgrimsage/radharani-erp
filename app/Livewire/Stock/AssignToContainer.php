<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Support\SpreadsheetReader;
use App\Support\StockLookup;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The working screen for keeping the system matching the stock room (28 Oct: the move works exactly as it does on a piece's own page):
 *  - move:  scan pieces (or packets) into a list, choose where they go the way the piece page does (scan the packet or box, or pick the
 *           packet from the list grouped by box), then move them all at once. A piece already on the list is not scanned again.
 *  - sheet: upload a two-column spreadsheet (code, destination) and apply the valid rows
 * Every reassignment is a per-model save, so it lands in the activity history.
 */
class AssignToContainer extends Component
{
    use WithFileUploads;

    #[Url(except: 'move')]
    public string $mode = 'move'; // move | sheet

    // ---- move
    public string $kind = 'items'; // items (into a packet) | packets (into a box)
    /** @var list<array{type: string, id: int, code: string, detail: string, from: ?string}> */
    public array $tray = [];
    public string $moveTo = '';    // '' = not chosen yet, 'none' = no packet / no box, else the packet or box id
    public ?string $moveBox = null; // narrows the packet list to one box, as on the piece page
    public ?string $moveScanError = null;
    /** @var array{tone: string, code: string, message: string}|null */
    public ?array $feedback = null;
    /** @var array{kind: string, count: int, dest: string, undo: list<array{id: int, prev: ?int}>, undone: bool}|null */
    public ?array $lastMove = null;

    // ---- sheet
    public $sheet = null;
    public array $sheetRows = [];
    public ?string $sheetName = null;
    public ?int $sheetApplied = null;

    public function mount(): void
    {
        // The old addresses (?mode=scan, ?mode=pick) open the move screen.
        $this->mode = $this->mode === 'sheet' ? 'sheet' : 'move';
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'sheet' ? 'sheet' : 'move';
        $this->resetValidation();
    }

    // ================================================================ move

    public function setKind(string $kind): void
    {
        $kind = $kind === 'packets' ? 'packets' : 'items';
        if ($kind !== $this->kind) {
            $this->kind = $kind;
            $this->reset(['tray', 'moveTo', 'moveBox', 'moveScanError', 'feedback']);
        }
    }

    // The code arrives as an argument (the field is cleared in the browser on Enter), so fast scans queue up instead of overwriting each other.
    public function scan(string $raw): void
    {
        // Several at once: codes pasted or scanned together. Codes can hold spaces, so the whole input is tried first.
        $codes = StockLookup::splitCodes($raw, fn ($c) => $this->kind === 'items' ? StockLookup::item($c) : StockLookup::packet($c));
        foreach ($codes as $one) {
            $this->scanOne($one);
        }
        $this->dispatch('scan-ready');
    }

    private function scanOne(string $raw): void
    {
        $code = StockLookup::normalize($raw);
        if ($code === '') {
            return;
        }

        if ($this->kind === 'items') {
            $model = StockLookup::item($code);
            if (! $model) {
                $this->say('error', $code, 'No piece found with this code.');

                return;
            }
            if ($model->status === 'sold') {
                $this->say('error', $model->label, 'This piece is sold. Sold pieces are not regrouped.');

                return;
            }
            $label = $model->label_with_tag;
            $from = $model->packet?->code;
            $currentId = $model->packet_id;
            $detail = trim($model->category . ' · ' . number_format($model->weight, 3) . ' g');
            $type = 'item';
        } else {
            $model = StockLookup::packet($code);
            if (! $model) {
                $this->say('error', $code, 'No packet found with this code. A box can only hold packets.');

                return;
            }
            $label = $model->code;
            $from = $model->box?->code;
            $currentId = $model->box_id;
            $detail = $model->custodyItems()->count() . ' pieces';
            $type = 'packet';
        }

        // Already on the list: nothing is added a second time.
        if (collect($this->tray)->contains(fn ($t) => $t['type'] === $type && $t['id'] === $model->id)) {
            $this->say('info', $label, 'This item is already scanned.');

            return;
        }
        if (ctype_digit($this->moveTo) && (int) $this->moveTo === (int) $currentId) {
            $this->say('info', $label, 'Already there.');

            return;
        }

        array_unshift($this->tray, ['type' => $type, 'id' => $model->id, 'code' => $label, 'detail' => $detail, 'from' => $from]);
        $this->say('success', $label, 'Added to the list.');
    }

    public function removeFromList(int $index): void
    {
        unset($this->tray[$index]);
        $this->tray = array_values($this->tray);
        $this->dispatch('scan-ready');
    }

    public function clearList(): void
    {
        $this->reset(['tray', 'feedback']);
        $this->dispatch('scan-ready');
    }

    // Scan (or type) the destination, as on the piece page: a packet is chosen, a box narrows the packet list (pieces) or is chosen (packets).
    public function scanDestination(string $raw): void
    {
        $this->moveScanError = null;
        $found = StockLookup::container($raw);
        if (! $found) {
            $this->moveScanError = 'No packet or box has that code.';

            return;
        }

        if ($this->kind === 'packets') {
            if ($found['type'] !== 'box') {
                $this->moveScanError = 'Packets go into a box. Scan a box.';

                return;
            }
            $this->moveTo = (string) $found['model']->id;

            return;
        }

        if ($found['type'] === 'packet') {
            $this->moveTo = (string) $found['model']->id;
            $this->moveBox = $found['model']->box?->code;

            return;
        }
        $this->moveBox = $found['model']->code;
        $inside = $found['model']->packets()->pluck('id');
        $this->moveTo = $inside->count() === 1 ? (string) $inside->first() : '';
    }

    public function move(): void
    {
        if (! $this->tray) {
            $this->say('error', '', $this->kind === 'items' ? 'Scan at least one piece first.' : 'Scan at least one packet first.');

            return;
        }
        $this->resetValidation();
        $isId = ctype_digit($this->moveTo) && ($this->kind === 'items' ? Packet::whereKey($this->moveTo)->exists() : Box::whereKey($this->moveTo)->exists());
        if ($this->moveTo !== 'none' && ! $isId) {
            $this->addError('moveTo', $this->kind === 'items' ? 'Choose the packet they are going into.' : 'Choose the box they are going into.');

            return;
        }

        $destId = $this->moveTo === 'none' ? null : (int) $this->moveTo;
        $dest = $destId ? ($this->kind === 'items' ? Packet::find($destId) : Box::find($destId)) : null;
        $moved = [];
        $skipped = 0;

        DB::transaction(function () use ($destId, &$moved, &$skipped) {
            foreach ($this->tray as $t) {
                if ($t['type'] === 'item') {
                    $item = Item::find($t['id']);
                    if (! $item || $item->status === 'sold' || $item->packet_id === $destId) {
                        $skipped++;
                        continue;
                    }
                    $moved[] = ['id' => $item->id, 'prev' => $item->packet_id];
                    $item->update(['packet_id' => $destId]); // per piece, so each piece's history shows it
                } else {
                    $packet = Packet::find($t['id']);
                    if (! $packet || $packet->box_id === $destId) {
                        $skipped++;
                        continue;
                    }
                    $moved[] = ['id' => $packet->id, 'prev' => $packet->box_id];
                    $packet->update(['box_id' => $destId]);
                }
            }
        });

        $where = $dest ? $dest->code : ($this->kind === 'items' ? 'no packet' : 'no box');
        $this->lastMove = ['kind' => $this->kind, 'count' => count($moved), 'dest' => $where, 'undo' => $moved, 'undone' => false];
        $this->reset(['tray', 'feedback']);
        $this->dispatch('toast', message: count($moved) . ' ' . ($this->kind === 'items' ? \Illuminate\Support\Str::plural('piece', count($moved)) : \Illuminate\Support\Str::plural('packet', count($moved))) . " moved to {$where}." . ($skipped ? " {$skipped} already there or no longer available." : ''), type: $moved ? 'success' : 'warning');
        $this->dispatch('scan-ready');
    }

    public function undoLast(): void
    {
        if (! $this->lastMove || $this->lastMove['undone']) {
            return;
        }
        foreach ($this->lastMove['undo'] as $u) {
            $this->lastMove['kind'] === 'items'
                ? Item::find($u['id'])?->update(['packet_id' => $u['prev']])
                : Packet::find($u['id'])?->update(['box_id' => $u['prev']]);
        }
        $this->lastMove['undone'] = true;
        $this->dispatch('toast', message: 'Put back where they were.', type: 'info');
    }

    private function say(string $tone, string $code, string $message): void
    {
        $this->feedback = ['tone' => $tone, 'code' => $code, 'message' => $message];
    }

    // =============================================================== sheet

    public function updatedSheet(): void
    {
        $this->validate(['sheet' => 'required|file|mimes:csv,txt,xlsx|max:5120'], [], ['sheet' => 'spreadsheet']);
        $this->sheetApplied = null;
        $this->sheetName = $this->sheet->getClientOriginalName();

        [$headers, $rows] = SpreadsheetReader::read($this->sheet->getRealPath(), $this->sheet->getClientOriginalExtension(), 1000);

        // A header row is expected, but tolerate a sheet whose first row is already data.
        if ($headers && ! preg_match('/code|item|piece|huid|packet|box|dest/i', implode(' ', $headers))) {
            array_unshift($rows, $headers);
        }

        $this->sheetRows = [];
        foreach ($rows as $i => $row) {
            $this->sheetRows[] = $this->checkRow($i + 2, $row[0] ?? '', $row[1] ?? '');
        }
    }

    // Validates one spreadsheet row without writing anything.
    private function checkRow(int $line, string $code, string $destCode): array
    {
        $row = ['line' => $line, 'code' => StockLookup::normalize($code), 'dest' => StockLookup::normalize($destCode),
            'status' => 'ok', 'message' => '', 'kind' => null, 'id' => null, 'destId' => null, 'from' => null];

        $dest = StockLookup::container($destCode);
        if ($row['code'] === '' || $row['dest'] === '') {
            return ['status' => 'error', 'message' => 'Code or destination is empty'] + $row;
        }
        if (! $dest) {
            return ['status' => 'error', 'message' => 'Destination not found'] + $row;
        }

        if ($dest['type'] === 'packet') {
            $item = StockLookup::item($code);
            if (! $item) {
                return ['status' => 'error', 'message' => 'Piece not found'] + $row;
            }
            if ($item->status === 'sold') {
                return ['status' => 'error', 'message' => 'Piece is sold'] + $row;
            }
            $row = ['kind' => 'item', 'id' => $item->id, 'destId' => $dest['model']->id, 'from' => $item->packet?->code, 'code' => $item->label] + $row;
            if ($item->packet_id === $dest['model']->id) {
                return ['status' => 'same', 'message' => 'Already there'] + $row;
            }
        } else {
            $packet = StockLookup::packet($code);
            if (! $packet) {
                return ['status' => 'error', 'message' => 'Packet not found (a box can only hold packets)'] + $row;
            }
            $row = ['kind' => 'packet', 'id' => $packet->id, 'destId' => $dest['model']->id, 'from' => $packet->box?->code, 'code' => $packet->code] + $row;
            if ($packet->box_id === $dest['model']->id) {
                return ['status' => 'same', 'message' => 'Already there'] + $row;
            }
        }

        return $row;
    }

    public function applySheet(): void
    {
        $count = 0;
        foreach ($this->sheetRows as $i => $row) {
            if ($row['status'] !== 'ok') {
                continue;
            }
            // Re-check at apply time: the preview may be stale.
            $fresh = $this->checkRow($row['line'], $row['code'], $row['dest']);
            if ($fresh['status'] !== 'ok') {
                $this->sheetRows[$i] = $fresh;
                continue;
            }
            $fresh['kind'] === 'item'
                ? Item::find($fresh['id'])->update(['packet_id' => $fresh['destId']])
                : Packet::find($fresh['id'])->update(['box_id' => $fresh['destId']]);
            $this->sheetRows[$i]['status'] = 'done';
            $count++;
        }

        $this->sheetApplied = $count;
        $this->dispatch('toast', message: "{$count} row(s) applied from the spreadsheet.", type: $count ? 'success' : 'warning');
    }

    public function resetSheet(): void
    {
        $this->reset(['sheet', 'sheetRows', 'sheetName', 'sheetApplied']);
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'destination']);
            fputcsv($out, ['HUID123456', 'PKT-1-1']);
            fputcsv($out, ['WGY2E', 'PKT-1-2']);
            fputcsv($out, ['PKT-3-1', 'BOX-02']);
            fclose($out);
        }, 'assign-template.csv', ['Content-Type' => 'text/csv']);
    }

    // ============================================================== render

    public function render()
    {
        $inTray = collect($this->tray);

        return view('livewire.stock.assign-to-container', [
            'trayWeight' => $this->kind === 'items' ? Item::whereIn('id', $inTray->where('type', 'item')->pluck('id'))->sum('weight') : 0,
            // The same lists as the piece page: packets grouped by their box, narrowed to one box when a box was scanned.
            'packetsByBox' => $this->kind === 'items'
                ? Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])
                    ->when($this->moveBox, fn ($c) => $c->filter(fn ($p) => $p->box?->code === $this->moveBox))
                    ->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box')
                : collect(),
            'boxes' => $this->kind === 'packets' ? Box::orderBy('code')->get(['id', 'code', 'label']) : collect(),
            'sheetCounts' => [
                'ok' => collect($this->sheetRows)->where('status', 'ok')->count(),
                'same' => collect($this->sheetRows)->where('status', 'same')->count(),
                'error' => collect($this->sheetRows)->where('status', 'error')->count(),
                'done' => collect($this->sheetRows)->where('status', 'done')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Change Item location · Radharani Jewellery']);
    }
}
