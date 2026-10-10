<?php
namespace App\Http\Controllers;

use App\Models\Purchase\Vendor;
use App\Services\LedgerService;
use Dompdf\Dompdf;
use Dompdf\Options;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Downloads of a party ledger as PDF or Excel (8 Oct change list, 14.1). */
class LedgerDownloadController extends Controller
{
    public function __invoke(Vendor $party, string $format, LedgerService $ledgers)
    {
        abort_unless(in_array($party->type, ['karigar', 'hallmark_center'], true), 404);

        return $this->download($party, $ledgers->forParty($party), $format);
    }

    // The customer's own ledger, shown on their detail page.
    public function customer(\App\Models\Customer\Customer $customer, string $format, LedgerService $ledgers)
    {
        return $this->download($customer, $ledgers->forCustomer($customer), $format);
    }

    private function download($party, array $ledger, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $name = str($party->name)->slug() . '-ledger-' . now()->format('Ymd');
        $party->type ??= 'customer';
        $fmt = fn ($v) => $v === null ? '' : (is_float($v) ? round($v, 3) : $v);

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($ledger, $fmt, $party) {
                $w = new Writer();
                $w->openToFile('php://output');
                $w->addRow(Row::fromValues([$party->name . ' ledger']));
                $w->addRow(Row::fromValues($ledger['columns']));
                foreach ($ledger['rows'] as $r) {
                    $w->addRow(Row::fromValues(array_merge([$r['at']->format('Y-m-d H:i'), $r['label']], array_map($fmt, $r['cells']))));
                }
                $w->addRow(Row::fromValues(array_merge(['Total', ''], array_map($fmt, $ledger['totals']))));
                $w->close();
            }, $name . '.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('ledgers.pdf', ['party' => $party, 'ledger' => $ledger])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $name . '.pdf"']);
    }
}
