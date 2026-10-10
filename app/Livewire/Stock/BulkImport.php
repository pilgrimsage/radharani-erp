<?php
namespace App\Livewire\Stock;

use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\Stock\QrCode;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bulk Import: upload -> match columns -> review (duplicates + problems flagged) -> import.
 * Nothing touches the database until the final confirm, and suspected duplicates
 * start unticked so the same piece isn't entered twice by accident.
 */
class BulkImport extends Component
{
    use WithFileUploads;

    public const FIELDS = [
        'huid_code' => ['HUID', false, ['huid', 'hallmark', 'huid code', 'huid no']],
        'category' => ['Category', true, ['category', 'product', 'ornament', 'item type']],
        'metal' => ['Metal', false, ['metal', 'material']],
        'purity' => ['Purity', true, ['purity', 'karat', 'carat', 'kt', 'fineness', 'touch']],
        'weight' => ['Weight (g)', true, ['weight', 'wt', 'gross', 'net wt', 'grams', 'gm']],
        'description' => ['Description', false, ['description', 'desc', 'details', 'name', 'remarks']],
        'hsn_code' => ['HSN code', false, ['hsn']],
        'making_type' => ['Making type', false, ['making type', 'making_type', 'mc type']],
        'making_value' => ['Making value', false, ['making', 'making value', 'making charge', 'mc', 'labour', 'wastage']],
        'packet' => ['Packet code', false, ['packet', 'pkt', 'packet code', 'location']],
    ];

    public int $step = 1;

    // Step 1
    public $file = null;
    public ?string $fileName = null;
    public array $headers = [];
    public array $rows = [];

    // Step 2: column index => system field
    public array $mapping = [];
    public string $defaultMetal = 'gold';
    public string $defaultMakingType = 'flat_per_piece';
    public $defaultMakingValue = 0;
    public ?int $defaultPacketId = null;

    // Step 3
    public array $reviewRows = [];
    public string $reviewFilter = 'all'; // all | ready | duplicate | error

    // Step 4
    public array $createdIds = [];

    // ---------------------------------------------------------------- step 1

    public function updatedFile(): void
    {
        $this->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:10240'], [], ['file' => 'spreadsheet']);

        [$this->headers, $this->rows] = SpreadsheetReader::read($this->file->getRealPath(), $this->file->getClientOriginalExtension(), 1000);
        $this->fileName = $this->file->getClientOriginalName();

        if (! $this->headers || ! $this->rows) {
            $this->addError('file', 'That file has no rows under a header row.');
            return;
        }

        $this->autoMap();
        $this->step = 2;
    }

    // Best-guess matching of spreadsheet headers to system fields by synonym.
    private function autoMap(): void
    {
        $this->mapping = [];
        $taken = [];
        foreach ($this->headers as $i => $header) {
            $h = strtolower(preg_replace('/[^a-z0-9 ]+/i', ' ', $header));
            $h = trim(preg_replace('/\s+/', ' ', $h));
            foreach (self::FIELDS as $field => [, , $synonyms]) {
                if (in_array($field, $taken, true)) {
                    continue;
                }
                foreach ($synonyms as $syn) {
                    if ($h === $syn || str_contains($h, $syn)) {
                        // "making type" must not be captured by the looser "making" synonym of making_value.
                        if ($field === 'making_value' && str_contains($h, 'type')) {
                            continue 2;
                        }
                        $this->mapping[$i] = $field;
                        $taken[] = $field;
                        continue 3;
                    }
                }
            }
            $this->mapping[$i] = '';
        }
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['HUID', 'Category', 'Metal', 'Purity', 'Weight', 'Description', 'HSN', 'Making type', 'Making value', 'Packet']);
            fputcsv($out, ['', 'Ring', 'gold', '22K', '4.250', 'Floral band', '7113', 'per piece', '650', 'PKT-1-1']);
            fputcsv($out, ['HUID778812', 'Necklace', 'gold', '22K', '28.600', 'Temple design', '7113', 'percentage', '14', 'PKT-1-2']);
            fputcsv($out, ['', 'Anklet', 'silver', '92.5', '31.400', 'Pair, left', '7113', 'per gram', '9', '']);
            fclose($out);
        }, 'stock-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    // ---------------------------------------------------------------- step 2

    public function confirmMapping(): void
    {
        $mapped = array_filter($this->mapping);
        $dupes = array_diff_assoc($mapped, array_unique($mapped));
        if ($dupes) {
            $this->addError('mapping', 'Each system field can only be matched to one column. "' . self::FIELDS[reset($dupes)][0] . '" is used twice.');
            return;
        }
        foreach (['category', 'purity', 'weight'] as $required) {
            if (! in_array($required, $mapped, true)) {
                $this->addError('mapping', 'Match a column to "' . self::FIELDS[$required][0] . '". It is required for every piece.');
                return;
            }
        }
        $this->validate([
            'defaultMetal' => 'required|in:gold,silver,platinum,titanium',
            'defaultMakingType' => 'required|in:percentage,flat_per_piece,flat_per_gram',
            'defaultMakingValue' => 'required|numeric|min:0',
            'defaultPacketId' => 'nullable|exists:packets,id',
        ]);

        $this->buildReview();
        $this->reviewFilter = 'all';
        $this->step = 3;
    }

    private function buildReview(): void
    {
        $fieldIndex = array_flip(array_filter($this->mapping));
        $packets = Packet::pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id]);
        $existingHuids = Item::whereNotNull('huid_code')->pluck('huid_code')->map(fn ($h) => strtoupper($h))->flip();
        $seenHuids = [];
        $this->reviewRows = [];

        foreach ($this->rows as $n => $row) {
            $get = fn ($field) => isset($fieldIndex[$field]) ? trim((string) ($row[$fieldIndex[$field]] ?? '')) : '';

            $data = [
                'huid_code' => strtoupper($get('huid_code')) ?: null,
                'category' => ucwords(strtolower($get('category'))),
                'metal' => $this->parseMetal($get('metal')),
                'purity' => $get('purity'),
                'weight' => str_replace([',', 'g', 'G', ' '], '', $get('weight')),
                'description' => $get('description') ?: null,
                'hsn_code' => $get('hsn_code') ?: null,
                'making_type' => $this->parseMakingType($get('making_type')),
                'making_value' => $get('making_value') !== '' ? str_replace([',', '₹', '%', ' '], '', $get('making_value')) : $this->defaultMakingValue,
                'packet_code' => strtoupper($get('packet')),
            ];

            $errors = [];
            if ($data['category'] === '') $errors[] = 'Category missing';
            if ($data['purity'] === '') $errors[] = 'Purity missing';
            if (! is_numeric($data['weight']) || (float) $data['weight'] <= 0) $errors[] = 'Weight is not a number';
            if (! $data['metal']) $errors[] = 'Unknown metal "' . $get('metal') . '"';
            if (! $data['making_type']) $errors[] = 'Unknown making type "' . $get('making_type') . '"';
            if (! is_numeric($data['making_value'])) $errors[] = 'Making value is not a number';
            if (mb_strlen((string) $data['description']) > 100) $errors[] = 'Description over 100 characters';

            // A blank packet is genuinely unassigned: never fall back to a packet nobody chose.
            $packetId = $this->defaultPacketId ?: null;
            if ($data['packet_code'] !== '') {
                $packetId = $packets[$data['packet_code']] ?? null;
                if (! $packetId) $errors[] = 'Packet ' . $data['packet_code'] . ' not found';
            }

            $duplicate = null;
            if ($data['huid_code']) {
                if (isset($existingHuids[$data['huid_code']])) {
                    $duplicate = 'HUID already in stock';
                } elseif (isset($seenHuids[$data['huid_code']])) {
                    $duplicate = 'Same HUID as row ' . $seenHuids[$data['huid_code']];
                }
                $seenHuids[$data['huid_code']] ??= $n + 2;
            } elseif (! $errors) {
                // No HUID to compare, so look for an untagged piece that matches on everything that identifies it.
                $match = Item::whereNull('huid_code')
                    ->where('category', $data['category'])
                    ->where('metal', $data['metal'])
                    ->where('purity', $data['purity'])
                    ->whereBetween('weight', [(float) $data['weight'] - 0.001, (float) $data['weight'] + 0.001])
                    ->first();
                if ($match) {
                    $duplicate = 'Looks like ' . $match->label . ' already in stock';
                }
            }

            $this->reviewRows[] = [
                'line' => $n + 2,
                'data' => $data,
                'packet_id' => $packetId,
                'errors' => $errors,
                'duplicate' => $duplicate,
                'include' => ! $errors && ! $duplicate,
            ];
        }
    }

    private function parseMetal(string $raw): ?string
    {
        if ($raw === '') {
            return $this->defaultMetal;
        }
        $raw = strtolower($raw);
        foreach (['gold', 'silver', 'platinum', 'titanium'] as $m) {
            if (str_contains($raw, $m)) {
                return $m;
            }
        }

        return ['au' => 'gold', 'ag' => 'silver', 'pt' => 'platinum', 'ti' => 'titanium'][$raw] ?? null;
    }

    private function parseMakingType(string $raw): ?string
    {
        if ($raw === '') {
            return $this->defaultMakingType;
        }
        $raw = strtolower($raw);

        return match (true) {
            str_contains($raw, '%') || str_contains($raw, 'percent') => 'percentage',
            str_contains($raw, 'gram') || str_contains($raw, '/g') || $raw === 'flat_per_gram' => 'flat_per_gram',
            str_contains($raw, 'piece') || str_contains($raw, 'flat') || str_contains($raw, 'pc') => 'flat_per_piece',
            default => null,
        };
    }

    // ---------------------------------------------------------------- step 3

    public function toggleInclude(int $index): void
    {
        if (isset($this->reviewRows[$index]) && ! $this->reviewRows[$index]['errors']) {
            $this->reviewRows[$index]['include'] = ! $this->reviewRows[$index]['include'];
        }
    }

    public function setAll(bool $include): void
    {
        foreach ($this->reviewRows as $i => $row) {
            if (! $row['errors'] && ($include === false || ! $row['duplicate'])) {
                $this->reviewRows[$i]['include'] = $include;
            }
        }
    }

    public function confirmImport(): void
    {
        $rows = array_filter($this->reviewRows, fn ($r) => $r['include'] && ! $r['errors']);
        if (! $rows) {
            $this->dispatch('toast', message: 'Tick at least one row to import.', type: 'warning');
            return;
        }

        $this->createdIds = DB::transaction(function () use ($rows) {
            $ids = [];
            foreach ($rows as $row) {
                $d = $row['data'];
                $item = Item::create([
                    'huid_code' => $d['huid_code'],
                    // No HUID -> the curated non-ambiguous internal code (never a second generator).
                    'internal_code' => $d['huid_code'] ? null : Item::generateInternalCode(),
                    'metal' => $d['metal'],
                    'category' => $d['category'],
                    // Matched to the owner's category tree; a new name is added under that metal for the owner to tidy.
                    'category_id' => \App\Models\Stock\ItemCategory::firstOrCreate(['metal' => $d['metal'], 'name' => $d['category']])->id,
                    'purity' => $d['purity'],
                    'weight' => (float) $d['weight'],
                    'description' => $d['description'],
                    'hsn_code' => $d['hsn_code'],
                    'making_type' => $d['making_type'],
                    'making_value' => (float) $d['making_value'],
                    'packet_id' => $row['packet_id'],
                    'status' => 'in_stock',
                ]);
                $ids[] = $item->id;
            }

            return $ids;
        });

        $this->step = 4;
        $this->dispatch('toast', message: count($this->createdIds) . ' piece(s) added to stock.', type: 'success');
    }

    public function printLabels()
    {
        $ids = collect($this->createdIds)->map(fn ($id) => QrCode::forTarget('item', $id)->id);

        return $this->redirectRoute('stock.qr.print', ['ids' => $ids->implode(',')]);
    }

    public function backTo(int $step): void
    {
        if ($step < $this->step && $step >= 1 && $this->step < 4) {
            $this->step = $step;
        }
    }

    public function startOver(): void
    {
        $this->reset();
    }

    public function render()
    {
        $review = collect($this->reviewRows);

        return view('livewire.stock.bulk-import', [
            'packets' => Packet::with('box:id,code')->orderBy('code')->get(['id', 'code', 'label', 'box_id']),
            'counts' => [
                'all' => $review->count(),
                'ready' => $review->filter(fn ($r) => ! $r['errors'] && ! $r['duplicate'])->count(),
                'duplicate' => $review->filter(fn ($r) => ! $r['errors'] && $r['duplicate'])->count(),
                'error' => $review->filter(fn ($r) => (bool) $r['errors'])->count(),
                'included' => $review->where('include', true)->count(),
            ],
            'created' => $this->step === 4 ? Item::whereIn('id', $this->createdIds)->with('packet:id,code')->limit(12)->get() : collect(),
        ])->layout('components.layouts.app', ['title' => 'Bulk Import · Radharani Jewellery']);
    }
}
