<?php
namespace App\Livewire\Ledgers;

use App\Models\Purchase\Vendor;
use App\Services\LedgerService;
use Livewire\Component;

class LedgerParty extends Component
{
    public Vendor $party;

    public function mount(Vendor $party): void
    {
        abort_unless(in_array($party->type, ['karigar', 'hallmark_center'], true), 404);
        $this->party = $party;
    }

    public function render(LedgerService $ledgers)
    {
        return view('livewire.ledgers.ledger-party', ['ledger' => $ledgers->forParty($this->party)])
            ->layout('components.layouts.app', ['title' => $this->party->name . ' ledger · Radharani Jewellery']);
    }
}
