<?php
namespace App\Livewire\Ledgers;

use App\Services\LedgerService;
use Livewire\Attributes\Url;
use Livewire\Component;

/** All karigar ledgers, or all hallmarker ledgers (8 Oct change list, 14.1). */
class LedgerIndex extends Component
{
    #[Url(except: 'karigar')]
    public string $kind = 'karigar';

    public function render(LedgerService $ledgers)
    {
        $kind = $this->kind === 'hallmarker' ? 'hallmarker' : 'karigar';

        return view('livewire.ledgers.ledger-index', [
            'kind' => $kind,
            'parties' => $ledgers->summary($kind),
            'columns' => $kind === 'karigar' ? array_slice(LedgerService::KARIGAR_COLUMNS, 2) : array_slice(\App\Services\HallmarkLedger::COLUMNS, 2),
        ])->layout('components.layouts.app', ['title' => 'Ledgers · Radharani Jewellery']);
    }
}
