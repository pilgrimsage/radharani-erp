<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Item;
use App\Models\Stock\ItemCategory;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * HUID exporter and the Excel round trip (8 Oct change list, 4.8).
 *  - the government HUID layout, for pieces still waiting for a HUID
 *  - a full data sheet to fill in (HUIDs, making charges...) and upload again
 * Uploaded rows match on the piece's internal code. Every change is saved per piece,
 * so it lands in that piece's history.
 */
class HuidExchange extends Component
{
    use WithFileUploads;

    private const FULL = ['Internal code', 'HUID', 'Category', 'Metal', 'Purity', 'Weight (g)', 'Making type', 'Making value', 'Description', 'Packet', 'Box', 'Status'];

    private const MAKING = ['percentage' => 'percentage', 'per piece' => 'flat_per_piece', 'flat per piece' => 'flat_per_piece', 'flat_per_piece' => 'flat_per_piece',
        'per gram' => 'flat_per_gram', 'flat per gram' => 'flat_per_gram', 'flat_per_gram' => 'flat_per_gram'];

    public string $scope = 'waiting'; // waiting (no HUID yet) | all
    public $sheet = null;
    public ?string $sheetName = null;

    /** @var array<int, array{item_id: int, code: string, changes: array<string, array{0: mixed, 1: mixed}>, values: array<string, mixed>}> */
    public array $plan = [];
    public array $unmatched = [];
    public int $unchanged = 0;
    public ?int $applied = null;

    private function pieces()
    {
        return Item::with('packet.box')->whereNotNull('internal_code')->whereNotIn('status', ['sold'])
            ->when($this->scope === 'waiting', fn ($q) => $q->whereNull('huid_code'))
            ->orderBy('id');
    }

    private function xlsxDownload(string $name, array $header, iterable $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $w = new Writer();
            $w->openToFile('php://output');
            $w->addRow(Row::fromValues($header));
            foreach ($rows as $r) {
                $w->addRow(Row::fromValues($r));
            }
            $w->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    // The layout of the government HUID website's upload file.
    public function exportHuidFormat()
    {
        $n = 0;
        $rows = $this->pieces()->get()->map(fn ($i) => [++$n, 'Mix Ornaments', 1, $i->huid_code ?? '', (float) $i->weight, $i->purity]);

        return $this->xlsxDownload('huid-upload-' . now()->format('Ymd-Hi') . '.xlsx', ['S No.', 'Item Category', 'No Of Unit', 'HUID', 'Weight of Article (Gms)', 'Purity'], $rows);
    }

    public function exportFull()
    {
        $rows = $this->pieces()->get()->map(fn ($i) => [
            $i->internal_code, $i->huid_code ?? '', $i->category, ucfirst((string) $i->metal), $i->purity, (float) $i->weight,
            str_replace('_', ' ', $i->making_type), (float) $i->making_value, $i->description ?? '',
            $i->packet?->code ?? '', $i->packet?->box?->code ?? '', str_replace('_', ' ', $i->status),
        ]);

        return $this->xlsxDownload('stock-data-' . now()->format('Ymd-Hi') . '.xlsx', self::FULL, $rows);
    }

    public function updatedSheet(): void
    {
        $this->reset(['plan', 'unmatched', 'unchanged', 'applied']);
        $this->validate(['sheet' => 'required|file|mimes:csv,txt,xlsx|max:10240'], [], ['sheet' => 'spreadsheet']);
        $this->sheetName = $this->sheet->getClientOriginalName();

        [$headers, $rows] = SpreadsheetReader::read($this->sheet->getRealPath(), $this->sheet->getClientOriginalExtension(), 2000);
        $col = collect($headers)->mapWithKeys(fn ($h, $i) => [mb_strtolower(trim($h)) => $i]);
        if (! $col->has('internal code')) {
            $this->addError('sheet', 'The sheet needs an "Internal code" column so each row can be matched to a piece.');

            return;
        }
        $get = fn (array $row, string $name) => isset($col[$name]) ? trim((string) ($row[$col[$name]] ?? '')) : null;

        $items = Item::whereIn('internal_code', collect($rows)->map(fn ($r) => strtoupper($get($r, 'internal code')))->filter()->all())->get()->keyBy('internal_code');

        foreach ($rows as $row) {
            $code = strtoupper((string) $get($row, 'internal code'));
            if ($code === '') {
                continue;
            }
            $item = $items->get($code);
            if (! $item) {
                $this->unmatched[] = $code;

                continue;
            }

            $values = [];
            if (($v = $get($row, 'huid')) !== null && $v !== '') {
                $values['huid_code'] = strtoupper($v);
            }
            if (($v = $get($row, 'purity')) !== null && $v !== '') {
                $values['purity'] = $v;
            }
            if (($v = $get($row, 'weight (g)')) !== null && is_numeric($v) && (float) $v > 0) {
                $values['weight'] = (float) $v;
            }
            if (($v = $get($row, 'making type')) !== null && $v !== '' && isset(self::MAKING[mb_strtolower($v)])) {
                $values['making_type'] = self::MAKING[mb_strtolower($v)];
            }
            if (($v = $get($row, 'making value')) !== null && is_numeric($v) && (float) $v >= 0) {
                $values['making_value'] = (float) $v;
            }
            if (($v = $get($row, 'description')) !== null && $v !== '') {
                $values['description'] = mb_substr($v, 0, 100);
            }
            if (($v = $get($row, 'category')) !== null && $v !== '' && mb_strtolower($v) !== mb_strtolower((string) $item->category)) {
                $values['category'] = $v;
            }

            $changes = [];
            foreach ($values as $field => $new) {
                $old = $item->$field;
                $same = is_numeric($old) && is_numeric($new) ? abs((float) $old - (float) $new) < 0.0005 : (string) $old === (string) $new;
                if (! $same) {
                    $changes[$field] = [$old, $new];
                }
            }

            // A HUID must be unique across stock.
            if (isset($changes['huid_code']) && Item::where('huid_code', $changes['huid_code'][1])->where('id', '!=', $item->id)->exists()) {
                $this->unmatched[] = "{$code} (HUID {$changes['huid_code'][1]} is already on another piece)";
                unset($changes['huid_code']);
            }

            if ($changes) {
                $this->plan[] = ['item_id' => $item->id, 'code' => $code, 'changes' => $changes, 'values' => array_map(fn ($c) => $c[1], $changes)];
            } else {
                $this->unchanged++;
            }
        }
    }

    public function apply(): void
    {
        $count = 0;
        DB::transaction(function () use (&$count) {
            foreach ($this->plan as $p) {
                $item = Item::find($p['item_id']);
                if (! $item) {
                    continue;
                }
                $values = $p['values'];
                if (isset($values['category'])) {
                    // Follow the owner's category tree: the same metal's subcategory by that name.
                    $values['category_id'] = ItemCategory::firstOrCreate(['metal' => $item->metal ?: 'gold', 'name' => $values['category']])->id;
                }
                $item->update($values); // per piece, so the change lands in its history
                $count++;
            }
        });

        $this->applied = $count;
        $this->reset(['plan', 'unmatched', 'unchanged', 'sheet', 'sheetName']);
        $this->dispatch('toast', message: "{$count} piece(s) updated.", type: 'success');
    }

    public function render()
    {
        return view('livewire.stock.huid-exchange', [
            'waiting' => Item::whereNotNull('internal_code')->whereNull('huid_code')->whereNotIn('status', ['sold'])->count(),
            'labels' => ['huid_code' => 'HUID', 'purity' => 'Purity', 'weight' => 'Weight', 'making_type' => 'Making type', 'making_value' => 'Making value', 'description' => 'Description', 'category' => 'Category'],
        ])->layout('components.layouts.app', ['title' => 'HUID export and update · Radharani Jewellery ERP']);
    }
}
