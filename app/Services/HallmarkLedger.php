<?php
namespace App\Services;

use App\Models\Movement\HallmarkBatch;
use App\Models\Purchase\Vendor;

/** The ledger of one hallmarking centre: counts and weights only (8 Oct change list, 7.4). */
class HallmarkLedger
{
    public const COLUMNS = ['Date', 'Batch', 'Pieces out', 'Pieces in', 'Weight out (g)', 'Weight in (g)', 'Loss (g)', 'Pieces pending'];

    public function forCentre(Vendor $centre): array
    {
        $batches = HallmarkBatch::where('vendor_id', $centre->id)->withCount('lines')->withSum('lines', 'weight_out')
            ->withSum('receipts', 'pieces')->withSum('receipts', 'weight_received')->withSum('receipts', 'weight_loss')->orderBy('id')->get();

        $rows = collect();
        $tot = ['out' => 0, 'in' => 0, 'wout' => 0.0, 'win' => 0.0, 'loss' => 0.0, 'pending' => 0];

        foreach ($batches as $b) {
            $out = $b->pieces_out;
            $in = (int) ($b->receipts_sum_pieces ?? 0);
            $wout = (float) $b->weight_counted + (float) ($b->lines_sum_weight_out ?? 0);
            $win = (float) ($b->receipts_sum_weight_received ?? 0);
            $loss = (float) ($b->receipts_sum_weight_loss ?? 0);
            // A closed batch has nothing pending any more.
            $pending = $b->status === 'closed' ? 0 : max(0, $out - $in);

            $rows->push([
                'at' => $b->created_at,
                'label' => $b->label . ($b->status === 'closed' ? ' (closed)' : ''),
                'cells' => [$out, $in, $wout, $win, $loss, $pending],
            ]);
            $tot['out'] += $out;
            $tot['in'] += $in;
            $tot['wout'] += $wout;
            $tot['win'] += $win;
            $tot['loss'] += $loss;
            $tot['pending'] += $pending;
        }

        return [
            'columns' => self::COLUMNS,
            'rows' => $rows,
            'totals' => [$tot['out'], $tot['in'], $tot['wout'], $tot['win'], $tot['loss'], $tot['pending']],
        ];
    }
}
