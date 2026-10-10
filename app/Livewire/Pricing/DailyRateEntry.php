<?php
namespace App\Livewire\Pricing;

use App\Models\Exchange\ExchangeDeductionPreset;
use App\Models\Movement\RateLog;
use App\Models\Stock\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Daily rates, per metal and per carat, each entered separately (8 Oct change list, 11.1).
 * A rate of 0 takes that carat's pieces off the website display, with a warning here.
 */
class DailyRateEntry extends Component
{
    public const METALS = ['gold', 'silver', 'titanium', 'platinum'];

    /** @var array<string, array<string, string>> metal => carat => rate as typed */
    public array $rates = [];

    /** @var array<string, array<string, string>> metal => carat key => deduction percent for old gold and silver taken in */
    public array $deductions = [];

    // Livewire reads a dot in a wire:model name as a path ("99.9" would become 99 > 9), so inputs use a dot-free key.
    public static function key(string $carat): string
    {
        return str_replace(['.', ' '], '_', $carat);
    }

    public function mount(): void
    {
        foreach (RateLog::CARATS as $metal => $carats) {
            foreach ($carats as $carat) {
                $log = RateLog::latestFor($metal, $carat);
                $this->rates[$metal][self::key($carat)] = $log ? (string) (float) $log->rate : '';
                $this->deductions[$metal][self::key($carat)] = (string) (float) (ExchangeDeductionPreset::where('metal', $metal)->where('purity', $carat)->value('percent') ?? 2);
            }
        }
    }

    public function save(): void
    {
        $this->validate([
            'rates.*.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'deductions.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['rates.*.*.numeric' => 'Rates must be numbers.', 'rates.*.*.min' => 'A rate can not be negative.']);

        $saved = 0;
        DB::transaction(function () use (&$saved) {
            foreach (RateLog::CARATS as $metal => $carats) {
                foreach ($carats as $carat) {
                    $typed = $this->rates[$metal][self::key($carat)] ?? '';
                    if ($typed === '' || $typed === null) {
                        continue;
                    }
                    $last = RateLog::latestFor($metal, $carat);
                    // Only changed rates are logged, so the history shows real movements.
                    if ($last && abs((float) $last->rate - (float) $typed) < 0.005) {
                        continue;
                    }
                    RateLog::create(['metal' => $metal, 'purity' => $carat, 'rate' => $typed, 'source' => 'manual', 'updated_by' => Auth::id()]);
                    $saved++;
                }
            }
        });

        // Deduction presets for exchanges, by metal and carat (8 Oct change list, 9.2).
        foreach (RateLog::CARATS as $metal => $carats) {
            foreach ($carats as $carat) {
                $typed = $this->deductions[$metal][self::key($carat)] ?? '';
                if ($typed === '' || $typed === null) {
                    continue;
                }
                $preset = ExchangeDeductionPreset::firstOrNew(['metal' => $metal, 'purity' => $carat]);
                if (! $preset->exists || abs((float) $preset->percent - (float) $typed) > 0.004) {
                    $preset->fill(['percent' => $typed, 'updated_by' => Auth::id()])->save();
                    $saved++;
                }
            }
        }

        $this->dispatch('toast', message: $saved ? "{$saved} " . \Illuminate\Support\Str::plural('rate', $saved) . ' saved.' : 'No rates changed.', type: $saved ? 'success' : 'info');
        if ($warning = $this->zeroRateWarning()) {
            $this->dispatch('toast', message: $warning, type: 'warning');
        }
    }

    // A rate of 0 hides that carat from the display; say so while pieces of it still exist.
    private function zeroRateWarning(): ?string
    {
        $parts = [];
        foreach (RateLog::CARATS as $metal => $carats) {
            foreach ($carats as $carat) {
                $log = RateLog::latestFor($metal, $carat);
                if (! $log || (float) $log->rate > 0) {
                    continue;
                }
                $count = Item::where('metal', $metal)->whereRaw('UPPER(purity) = ?', [strtoupper($carat)])->whereIn('status', ['in_stock', 'reserved'])->count();
                if ($count > 0) {
                    $parts[] = "{$count} " . ucfirst($metal) . " {$carat}";
                }
            }
        }

        return $parts ? 'The rate is 0, so these are hidden from the display: ' . implode(', ', $parts) . '.' : null;
    }

    public function render()
    {
        $last = [];
        foreach (RateLog::CARATS as $metal => $carats) {
            foreach ($carats as $carat) {
                $last[$metal][$carat] = RateLog::latestFor($metal, $carat);
            }
        }

        return view('livewire.pricing.daily-rate-entry', [
            'last' => $last,
            'carats' => RateLog::CARATS,
            'zeroWarning' => $this->zeroRateWarning(),
        ])->layout('components.layouts.app', ['title' => 'Daily Rate Entry — Radharani Jewellery']);
    }
}
