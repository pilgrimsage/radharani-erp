<?php
namespace App\Livewire\Exchange;

use App\Models\Customer\Customer;
use App\Models\Exchange\ExchangeDeductionPreset;
use App\Models\Exchange\ExchangeTransaction;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Old Gold/Silver Exchange as a resumable step form (8 Oct change list, 9.1).
 *
 * The client was explicit that no step in this process (gross weight, net weight after melt, two
 * independent purity readings auto-averaged, the preset deduction) is abstracted or combined, so
 * each step saves its own slice of the exchange_transactions row as soon as it is completed.
 * That is what makes it resumable: open an exchange from the list and it comes back at the step it
 * had reached. Any earlier step can be reopened and edited until the final valuation settles it,
 * the later figures are worked out again from the new numbers, and every edit is logged with its
 * before and after. Once settled, the exchange is locked.
 */
class NewEntry extends Component
{
    public const STEPS = [1 => 'Received', 2 => 'Melted', 3 => 'Tested', 4 => 'Deduction', 5 => 'Summary'];

    public int $step = 1;

    #[Url(as: 'tx', except: '')]
    public ?int $transactionId = null;

    public ?int $customerId = null;
    public string $customerSearch = '';
    public string $metal = 'gold';
    public $grossWeight = '';
    public string $description = '';

    public $netWeight = '';

    public $purityTest1 = '';
    public $purityTest2 = '';

    public function mount(): void
    {
        if (! $this->transactionId) {
            return;
        }
        $tx = ExchangeTransaction::find($this->transactionId);
        if (! $tx) {
            $this->transactionId = null;

            return;
        }

        $this->customerId = $tx->customer_id;
        $this->metal = $tx->metal;
        $this->grossWeight = (string) (float) $tx->gross_weight;
        $this->description = (string) $tx->description;
        $this->netWeight = $tx->net_weight !== null ? (string) (float) $tx->net_weight : '';
        $this->purityTest1 = $tx->purity_test_1 !== null ? (string) (float) $tx->purity_test_1 : '';
        $this->purityTest2 = $tx->purity_test_2 !== null ? (string) (float) $tx->purity_test_2 : '';

        // Back at the next step still to do.
        $this->step = ['received' => 2, 'melted' => 3, 'tested' => 5, 'valued' => 5, 'settled' => 5][$tx->stage] ?? 1;
    }

    private function tx(): ?ExchangeTransaction
    {
        return $this->transactionId ? ExchangeTransaction::find($this->transactionId) : null;
    }

    public function getLockedProperty(): bool
    {
        return (bool) $this->tx()?->is_settled;
    }

    // The furthest step that can be opened: one past what has been completed.
    public function getReachProperty(): int
    {
        return match ($this->tx()?->stage) {
            null => 1,
            'received' => 2,
            'melted' => 3,
            default => 5,
        };
    }

    public function goToStep(int $target): void
    {
        if ($target >= 1 && $target <= $this->reach) {
            $this->resetValidation();
            $this->step = $target;
        }
    }

    public function chooseCustomer(int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
        $this->resetErrorBag('customerId');
    }

    public function next(): void
    {
        abort_if($this->locked, 403, 'This exchange is settled and locked.');

        if ($this->step === 1) {
            $this->validate([
                'customerId' => 'required|exists:customers,id',
                'metal' => 'required|in:gold,silver,platinum,titanium',
                'grossWeight' => 'required|numeric|min:0.001',
            ]);

            $data = ['customer_id' => $this->customerId, 'metal' => $this->metal, 'gross_weight' => $this->grossWeight, 'description' => $this->description ?: null];
            if ($tx = $this->tx()) {
                $tx->update($data);
            } else {
                $tx = ExchangeTransaction::create($data + ['stage' => 'received', 'created_by' => auth()->id()]);
                $this->transactionId = $tx->id;
            }
            $this->recompute($tx);
        }

        if ($this->step === 2) {
            $this->validate(['netWeight' => 'required|numeric|min:0.001']);
            $tx = $this->tx();
            $tx->update(['net_weight' => $this->netWeight] + ($tx->stage === 'received' ? ['stage' => 'melted'] : []));
            $this->recompute($tx);
        }

        if ($this->step === 3) {
            $this->validate([
                'purityTest1' => 'required|numeric|min:0|max:100',
                'purityTest2' => 'required|numeric|min:0|max:100',
            ]);
            $tx = $this->tx();
            $tx->update([
                'purity_test_1' => $this->purityTest1,
                'purity_test_2' => $this->purityTest2,
                'purity_averaged' => $this->averagePurity,
                'stage' => in_array($tx->stage, ['received', 'melted'], true) ? 'tested' : $tx->stage,
            ]);
            $this->recompute($tx);
        }

        $this->step = min($this->step + 1, 5);
    }

    // Whenever something upstream changes, what follows is worked out again from it.
    private function recompute(ExchangeTransaction $tx): void
    {
        $tx->refresh();
        if ($tx->net_weight === null || $tx->purity_averaged === null) {
            return;
        }
        $percent = ExchangeDeductionPreset::percentFor($tx->metal, (float) $tx->purity_averaged);
        $tx->update([
            'preset_deduction_percent' => $percent,
            'deductable_weight' => round((float) $tx->net_weight * (1 - $percent / 100), 3),
        ]);
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function getAveragePurityProperty()
    {
        return is_numeric($this->purityTest1) && is_numeric($this->purityTest2)
            ? round(((float) $this->purityTest1 + (float) $this->purityTest2) / 2, 2)
            : 0;
    }

    public function getPresetDeductionPercentProperty(): float
    {
        $tx = $this->tx();

        return $tx && $tx->preset_deduction_percent !== null ? (float) $tx->preset_deduction_percent : 0.0;
    }

    public function getDeductedWeightProperty(): float
    {
        $tx = $this->tx();

        return $tx && $tx->deductable_weight !== null ? (float) $tx->deductable_weight : 0.0;
    }

    public function getSummaryTextProperty()
    {
        $customer = Customer::find($this->customerId);

        return implode("\n", [
            'Old Gold/Silver Exchange: Summary',
            'Customer: ' . ($customer->name ?? '-') . ' (' . ($customer->phone ?? '-') . ')',
            'Metal: ' . ucfirst($this->metal),
            "Description: {$this->description}",
            "Gross weight (as received): {$this->grossWeight}g",
            "Net weight (after melting): {$this->netWeight}g",
            "Purity test 1: {$this->purityTest1}% · Purity test 2: {$this->purityTest2}%",
            "Average purity: {$this->averagePurity}%",
            "Deduction: {$this->presetDeductionPercent}%",
            "Net payable weight: {$this->deductedWeight}g",
        ]);
    }

    public function render()
    {
        return view('livewire.exchange.new-entry', [
            'steps' => self::STEPS,
            'customer' => $this->customerId ? Customer::find($this->customerId) : null,
            'locked' => $this->locked,
            'customerResults' => $this->customerSearch
                ? Customer::where('name', 'like', "%{$this->customerSearch}%")->orWhere('phone', 'like', "%{$this->customerSearch}%")->limit(8)->get()
                : collect(),
        ])->layout('components.layouts.app', ['title' => 'Exchange — Radharani Jewellery']);
    }
}
