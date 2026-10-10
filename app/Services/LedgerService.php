<?php
namespace App\Services;

use App\Models\Movement\KarigarPayment;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Purchase\Vendor;
use Illuminate\Support\Collection;

/**
 * Party ledgers (8 Oct change list, section 14): weights and counts only, never money.
 * One per karigar and one per hallmarking centre.
 */
class LedgerService
{
    public const KARIGAR_COLUMNS = ['Date', 'Batch', 'Pieces out', 'Pieces in', 'Issued (g)', 'Received (g)', 'Loss (g)', 'Metal paid (g)', 'Balance (g)'];

    /**
     * Rows for a party, oldest first. Each row: date, label, cells[], and a 'kind' for styling.
     * Balance on a batch row is the metal still with the karigar for that batch: the advance
     * metal given less what came back and the loss typed in (never below zero).
     *
     * @return array{columns: array<int, string>, rows: Collection, totals: array<int, mixed>}
     */
    public function forParty(Vendor $party): array
    {
        return $party->type === 'hallmark_center' ? $this->hallmarker($party) : $this->karigar($party);
    }

    private function karigar(Vendor $party): array
    {
        $batches = KarigarRawBatch::where('vendor_id', $party->id)->withSum('receipts', 'pieces')
            ->withSum('receipts', 'weight_received')->withSum('receipts', 'weight_loss')->orderBy('id')->get();

        $rows = collect();
        $tot = ['out' => 0, 'in' => 0, 'issued' => 0.0, 'received' => 0.0, 'loss' => 0.0, 'paid' => 0.0, 'balance' => 0.0];

        foreach ($batches as $b) {
            $issued = (float) $b->advance_metal_weight;
            $received = (float) ($b->receipts_sum_weight_received ?? 0);
            $loss = (float) ($b->receipts_sum_weight_loss ?? 0);
            $in = (int) ($b->receipts_sum_pieces ?? 0);
            $balance = $issued > 0 ? max(0.0, $issued - $received - $loss) : 0.0;

            $rows->push([
                'at' => $b->created_at,
                'label' => $b->label . ($b->status === 'closed' ? ' (closed)' : ''),
                'cells' => [$b->pieces_expected, $in, $issued, $received, $loss, 0.0, $balance],
            ]);
            $tot['out'] += $b->pieces_expected;
            $tot['in'] += $in;
            $tot['issued'] += $issued;
            $tot['received'] += $received;
            $tot['loss'] += $loss;
            $tot['balance'] += $balance;
        }

        foreach (KarigarPayment::where('vendor_id', $party->id)->where('kind', 'metal')->orderBy('id')->get() as $p) {
            $rows->push([
                'at' => $p->created_at,
                'label' => 'Metal paid: ' . ucfirst($p->metal) . ' ' . $p->purity,
                'cells' => [null, null, 0.0, 0.0, 0.0, (float) $p->weight, null],
            ]);
            $tot['paid'] += (float) $p->weight;
        }

        return [
            'columns' => self::KARIGAR_COLUMNS,
            'rows' => $rows->sortBy(fn ($r) => $r['at']->timestamp)->values(),
            'totals' => [$tot['out'], $tot['in'], $tot['issued'], $tot['received'], $tot['loss'], $tot['paid'], $tot['balance']],
        ];
    }

    // Filled in with the hallmarking rebuild (section 7).
    private function hallmarker(Vendor $party): array
    {
        return app(HallmarkLedger::class)->forCentre($party);
    }

    /** Summary line per party for the ledger index. */
    public function summary(string $kind): Collection
    {
        $type = $kind === 'hallmarker' ? 'hallmark_center' : 'karigar';

        return Vendor::where('type', $type)->orderBy('name')->get()->map(function (Vendor $v) {
            $l = $this->forParty($v);

            return ['party' => $v, 'last' => $l['rows']->last()['at'] ?? null, 'totals' => $l['totals']];
        });
    }
}
