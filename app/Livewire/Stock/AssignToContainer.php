<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Support\SpreadsheetReader;
use App\Support\StockLookup;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The working screen for keeping the system matching the stock room:
 *  - scan:  scan a packet/box once, then scan pieces (or packets) into it, one after another
 *  - pick:  choose a destination, tick pieces/packets from a filtered list
 *  - sheet: upload a two-column spreadsheet (code, destination) and apply the valid rows
 * Every reassignment is a per-model save, so it lands in the activity history.
 */
class AssignToContainer extends Component
{
    use WithFileUploads;

    #[Url(except: 'scan')]
    public string $mode = 'scan'; // scan | pick | sheet

    // ---- scan
    public string $destCode = '';
    public ?string $destType = null; // packet | box
    public ?int $destId = null;
    public ?string $destError = null;
    public array $log = [];

    // ---- pick
    public string $pickKind = 'items'; // items (into a packet) | packets (into a box)
    public ?int $pickDestId = null;
    public string $pickSearch = '';
    public bool $pickUnassigned = true;
    public string $pickCategory = '';
    public array $pickSelected = [];

    // ---- sheet
    public $sheet = null;
    public array $sheetRows = [];
    public ?string $sheetName = null;
    public ?int $sheetApplied = null;

    public function setMode(string $mode): void
    {
        if (in_array($mode, ['scan', 'pick', 'sheet'], true)) {
            $this->mode = $mode;
            $this->resetValidation();
        }
    }

    // ================================================================ scan

    public function setDestination(): void
    {
        $this->destError = null;
        $found = StockLookup::container($this->destCode);

        if (! $found) {
            $this->destError = 'No packet or box has the code "' . StockLookup::normalize($this->destCode) . '".';
            return;
        }

        $this->destType = $found['type'];
        $this->destId = $found['model']->id;
        $this->destCode = '';
        $this->dispatch('scan-ready');
    }

    public function clearDestination(): void
    {
        $this->reset(['destType', 'destId', 'destError', 'destCode']);
        $this->dispatch('dest-cleared');
    }

    // The code arrives as an argument (the field is cleared in the browser on Enter),
    // so fast consecutive scans queue up instead of overwriting each other.
    public function scan(string $raw): void
    {
        $code = StockLookup::normalize($raw);

        if ($code === '' || ! $this->destId) {
            return;
        }

        $this->destType === 'packet' ? $this->scanPieceIntoPacket($code) : $this->scanPacketIntoBox($code);
        $this->dispatch('scan-ready');
    }

    private function scanPieceIntoPacket(string $code): void
    {
        $packet = Packet::findOrFail($this->destId);
        $item = StockLookup::item($code);

        if (! $item) {
            $this->logEntry('error', $code, 'No piece found with this code.');
            return;
        }
        if ($item->status === 'sold') {
            $this->logEntry('error', $item->label, 'This piece is sold. Sold pieces are not regrouped.');
            return;
        }
        if ($item->packet_id === $packet->id) {
            $this->logEntry('info', $item->label, "Already in {$packet->code}.");
            return;
        }

        $from = $item->packet?->code;
        $prev = $item->packet_id;
        $item->update(['packet_id' => $packet->id]);
        $this->logEntry('success', $item->label, $from ? "Moved from {$from}" : 'Placed in packet', ['type' => 'item', 'id' => $item->id, 'prev' => $prev],
            trim($item->category . ' · ' . number_format($item->weight, 3) . ' g'));
    }

    private function scanPacketIntoBox(string $code): void
    {
        $box = Box::findOrFail($this->destId);
        $packet = StockLookup::packet($code);

        if (! $packet) {
            $this->logEntry('error', $code, 'No packet found with this code.');
            return;
        }
        if ($packet->box_id === $box->id) {
            $this->logEntry('info', $packet->code, "Already in {$box->code}.");
            return;
        }

        $from = $packet->box?->code;
        $prev = $packet->box_id;
        $packet->update(['box_id' => $box->id]);
        $this->logEntry('success', $packet->code, $from ? "Moved from {$from}" : 'Placed in box', ['type' => 'packet', 'id' => $packet->id, 'prev' => $prev],
            $packet->items()->count() . ' pieces');
    }

    public function undo(int $index): void
    {
        $entry = $this->log[$index] ?? null;
        if (! $entry || empty($entry['undo']) || ! empty($entry['undone'])) {
            return;
        }

        ['type' => $type, 'id' => $id, 'prev' => $prev] = $entry['undo'];
        $type === 'item'
            ? Item::find($id)?->update(['packet_id' => $prev])
            : Packet::find($id)?->update(['box_id' => $prev]);

        $this->log[$index]['undone'] = true;
        $this->dispatch('toast', message: "Undid {$entry['code']}.", type: 'info');
        $this->dispatch('scan-ready');
    }

    public function clearLog(): void
    {
        $this->log = [];
    }

    private function logEntry(string $tone, string $code, string $message, ?array $undo = null, ?string $detail = null): void
    {
        array_unshift($this->log, [
            'tone' => $tone, 'code' => $code, 'message' => $message, 'detail' => $detail,
            'undo' => $undo, 'undone' => false, 'at' => now()->format('g:i:s a'),
        ]);
        $this->log = array_slice($this->log, 0, 60);
    }

    // ================================================================ pick

    public function updatedPickKind(): void
    {
        $this->reset(['pickDestId', 'pickSelected', 'pickSearch', 'pickCategory']);
        $this->pickUnassigned = true;
    }

    public function updatedPickDestId(): void
    {
        $this->pickSelected = [];
    }

    public function assignPicked(): void
    {
        $destRule = $this->pickKind === 'items' ? 'exists:packets,id' : 'exists:boxes,id';
        $this->validate([
            'pickDestId' => ['required', $destRule],
            'pickSelected' => ['required', 'array', 'min:1'],
        ], [
            'pickDestId.required' => $this->pickKind === 'items' ? 'Choose the packet they are going into.' : 'Choose the box they are going into.',
            'pickSelected.required' => 'Tick at least one row.',
        ]);

        if ($this->pickKind === 'items') {
            $dest = Packet::findOrFail($this->pickDestId);
            $rows = Item::whereIn('id', $this->pickSelected)->where('status', '!=', 'sold')->get();
            $rows->each(fn ($i) => $i->update(['packet_id' => $dest->id]));
        } else {
            $dest = Box::findOrFail($this->pickDestId);
            $rows = Packet::whereIn('id', $this->pickSelected)->get();
            $rows->each(fn ($p) => $p->update(['box_id' => $dest->id]));
        }

        $this->pickSelected = [];
        $this->dispatch('toast', message: "{$rows->count()} " . ($this->pickKind === 'items' ? 'piece(s)' : 'packet(s)') . " assigned to {$dest->code}.", type: 'success');
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
        $dest = match ($this->destType) {
            'packet' => Packet::withCount('items')->with('box')->find($this->destId),
            'box' => Box::withCount('packets')->find($this->destId),
            default => null,
        };

        $pickRows = collect();
        if ($this->mode === 'pick') {
            $pickRows = $this->pickKind === 'items'
                ? Item::with('packet:id,code')
                    ->where('status', '!=', 'sold')
                    ->when($this->pickUnassigned, fn ($q) => $q->whereNull('packet_id'))
                    ->when($this->pickDestId, fn ($q) => $q->where(fn ($q) => $q->whereNull('packet_id')->orWhere('packet_id', '!=', $this->pickDestId)))
                    ->when($this->pickCategory, fn ($q) => $q->where('category', $this->pickCategory))
                    ->when($this->pickSearch, fn ($q) => $q->where(fn ($q) => $q
                        ->where('huid_code', 'like', "%{$this->pickSearch}%")
                        ->orWhere('internal_code', 'like', "%{$this->pickSearch}%")
                        ->orWhere('category', 'like', "%{$this->pickSearch}%")
                        ->orWhere('description', 'like', "%{$this->pickSearch}%")))
                    ->orderByDesc('id')->limit(100)->get()
                : Packet::with('box:id,code')->withCount('items')
                    ->when($this->pickUnassigned, fn ($q) => $q->whereNull('box_id'))
                    ->when($this->pickDestId, fn ($q) => $q->where(fn ($q) => $q->whereNull('box_id')->orWhere('box_id', '!=', $this->pickDestId)))
                    ->when($this->pickSearch, fn ($q) => $q->where(fn ($q) => $q
                        ->where('code', 'like', "%{$this->pickSearch}%")->orWhere('label', 'like', "%{$this->pickSearch}%")))
                    ->orderBy('code')->limit(100)->get();
        }

        $sheet = collect($this->sheetRows);

        return view('livewire.stock.assign-to-container', [
            'dest' => $dest,
            'recent' => $dest && $this->destType === 'packet'
                ? $dest->items()->latest('updated_at')->limit(6)->get()
                : ($dest ? $dest->packets()->latest('updated_at')->limit(6)->get() : collect()),
            'pickRows' => $pickRows,
            'packets' => Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id'])->groupBy(fn ($p) => $p->box?->code ?? 'Not in a box'),
            'boxes' => Box::orderBy('code')->get(['id', 'code', 'label']),
            'categories' => Item::query()->distinct()->orderBy('category')->pluck('category'),
            'sheetCounts' => [
                'ok' => $sheet->where('status', 'ok')->count(),
                'same' => $sheet->where('status', 'same')->count(),
                'error' => $sheet->where('status', 'error')->count(),
                'done' => $sheet->where('status', 'done')->count(),
            ],
            'logCounts' => [
                'success' => collect($this->log)->where('tone', 'success')->where('undone', false)->count(),
                'error' => collect($this->log)->where('tone', 'error')->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Change Item location · Radharani Jewellery']);
    }
}
