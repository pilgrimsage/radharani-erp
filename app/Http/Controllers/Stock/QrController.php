<?php
namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use App\Models\Stock\QrCode;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Services\StockHistoryService;
use App\Support\StockLookup;
use Illuminate\Http\Request;

class QrController extends Controller
{
    // What every printed sticker points at. Logs the scan against the
    // target's history, then opens its detail page.
    public function resolve(string $code)
    {
        $qr = QrCode::where('code', strtoupper($code))->firstOrFail();
        $target = $qr->target();

        abort_unless($target, 404, 'This QR code points to a record that no longer exists.');

        StockHistoryService::logScan($target, $qr->code);

        return redirect()->to($qr->detailUrl());
    }

    // Whatever the top-bar camera scanner read: /stock/scan?code=...
    // A sticker is logged exactly like resolve(); HUIDs, internal codes and
    // packet/box codes open their detail page directly.
    public function lookup(Request $request)
    {
        $raw = (string) $request->query('code', '');
        $code = StockLookup::normalize($raw);

        if ($code !== '' && ($qr = QrCode::where('code', $code)->first()) && ($target = $qr->target())) {
            StockHistoryService::logScan($target, $qr->code);

            return redirect()->to($qr->detailUrl());
        }
        if ($item = StockLookup::item($raw)) {
            return redirect()->route('stock.items.show', $item);
        }
        if ($found = StockLookup::container($raw)) {
            return redirect()->route($found['type'] === 'packet' ? 'stock.packets.show' : 'stock.boxes.show', $found['model']);
        }

        return back()->with('toast', $code === '' ? 'Nothing was scanned.' : "Nothing in stock matches \"{$code}\".");
    }

    // Printable sticker sheet: /stock/qr-codes/print?ids=1,2,3
    public function print(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(300);

        $codes = QrCode::whereIn('id', $ids)->get()
            ->sortBy(fn ($qr) => $ids->search($qr->id))
            ->map(function (QrCode $qr) {
                $target = $qr->target();

                return [
                    'qr' => $qr,
                    'label' => QrCode::labelFor($target),
                    'sub' => match ($qr->target_type) {
                        'item' => $target ? trim($target->category . ' · ' . number_format((float) $target->weight, 3) . ' g') : '',
                        'packet' => $target?->label ?: 'Packet',
                        'box' => $target?->label ?: 'Box',
                    },
                    'type' => $qr->target_type,
                ];
            })->values();

        abort_if($codes->isEmpty(), 404, 'No QR codes selected for printing.');

        return view('stock.qr-print', [
            'codes' => $codes,
            'size' => in_array($request->query('size'), ['sm', 'md', 'lg'], true) ? $request->query('size') : 'md',
        ]);
    }

    // Stored QR data as an Excel file (8 Oct change list, 4.4): /stock/qr-codes/export?sort=box|packet|category
    public function export(Request $request)
    {
        $sort = in_array($request->query('sort'), ['box', 'packet', 'category'], true) ? $request->query('sort') : 'box';

        $qrs = QrCode::orderBy('id')->get();
        $items = Item::with('packet.box')->whereIn('id', $qrs->where('target_type', 'item')->pluck('target_id'))->get()->keyBy('id');
        $packets = Packet::with('box')->whereIn('id', $qrs->where('target_type', 'packet')->pluck('target_id'))->get()->keyBy('id');
        $boxes = Box::whereIn('id', $qrs->where('target_type', 'box')->pluck('target_id'))->get()->keyBy('id');

        $rows = $qrs->map(function (QrCode $q) use ($items, $packets, $boxes) {
            $item = $q->target_type === 'item' ? $items->get($q->target_id) : null;
            $packet = $q->target_type === 'packet' ? $packets->get($q->target_id) : $item?->packet;
            $box = $q->target_type === 'box' ? $boxes->get($q->target_id) : $packet?->box;

            return [
                'type' => ucfirst($q->target_type),
                'sticker' => $q->code,
                'what' => $item?->label ?? $packet?->code ?? $box?->code ?? '(removed)',
                'box' => $box?->code ?? '',
                'packet' => $packet?->code ?? '',
                'category' => $item?->category ?? '',
                'metal' => $item?->metal ? ucfirst($item->metal) : '',
                'url' => $q->scanUrl(),
                'created' => $q->created_at?->format('Y-m-d H:i') ?? '',
            ];
        })->sortBy(fn ($r) => mb_strtolower($r[$sort] . '|' . $r['box'] . '|' . $r['packet'] . '|' . $r['what']))->values();

        return response()->streamDownload(function () use ($rows) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(['Type', 'Sticker code', 'Code', 'Box', 'Packet', 'Category', 'Metal', 'Scan link', 'Created']));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_values($r)));
            }
            $writer->close();
        }, 'qr-codes-by-' . $sort . '-' . now()->format('Ymd-Hi') . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
