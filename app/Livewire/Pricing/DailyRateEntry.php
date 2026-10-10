<?php
namespace App\Livewire\Pricing;

use App\Models\Movement\RateLog;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DailyRateEntry extends Component
{
    public const METALS = ['gold', 'silver', 'titanium', 'platinum'];

    public array $rates = ['gold' => 0, 'silver' => 0, 'titanium' => 0, 'platinum' => 0];

    public function mount()
    {
        foreach (self::METALS as $metal) {
            $this->rates[$metal] = RateLog::latestFor($metal)?->rate ?? 0;
        }
    }

    public function save()
    {
        $this->validate([
            'rates.gold' => 'required|numeric|min:0',
            'rates.silver' => 'required|numeric|min:0',
            'rates.titanium' => 'required|numeric|min:0',
            'rates.platinum' => 'required|numeric|min:0',
        ]);

        foreach (self::METALS as $metal) {
            RateLog::create([
                'metal' => $metal,
                'rate' => $this->rates[$metal],
                'source' => 'manual',
                'updated_by' => Auth::id(),
            ]);
        }

        $this->dispatch('toast', message: "Today's rates saved.", type: 'success');

        if ($warning = $this->zeroRateWarning()) {
            $this->dispatch('toast', message: $warning, type: 'warning');
        }
    }

    // A rate of 0 switches that metal off the website display; say so while pieces of it still exist.
    private function zeroRateWarning(): ?string
    {
        $parts = [];
        foreach (self::METALS as $metal) {
            if ((float) $this->rates[$metal] > 0) {
                continue;
            }
            $count = Item::where('metal', $metal)->whereIn('status', ['in_stock', 'reserved'])->count();
            if ($count > 0) {
                $parts[] = "{$count} " . ucfirst($metal) . ' ' . str('piece')->plural($count);
            }
        }

        return $parts ? 'Rate is 0, so these are hidden from the display: ' . implode(', ', $parts) . '.' : null;
    }

    public function render()
    {
        $last = [];
        foreach (self::METALS as $metal) {
            $last[$metal] = RateLog::latestFor($metal);
        }

        return view('livewire.pricing.daily-rate-entry', [
            'last' => $last,
            'zeroWarning' => $this->zeroRateWarning(),
        ])->layout('components.layouts.app', ['title' => 'Daily Rate Entry — Radharani Jewellery']);
    }
}
