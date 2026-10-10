<?php
namespace App\Models\Exchange;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** The percentage deducted from a melted exchange, set per metal and carat on the daily rates page. */
class ExchangeDeductionPreset extends Model
{
    use LogsActivity;

    protected $fillable = ['metal', 'purity', 'percent', 'updated_by'];

    // Where each carat sits in percent purity, to pick the carat a tested purity is closest to.
    public const FINENESS = [
        'gold' => ['24K' => 99.9, '22K' => 91.6, '18K' => 75.0, '14K' => 58.5],
        'silver' => ['99.9' => 99.9, '92.5' => 92.5],
        'platinum' => ['950' => 95.0, '900' => 90.0],
        'titanium' => ['Grade 5' => 100.0, 'Grade 2' => 100.0],
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['metal', 'purity', 'percent'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('pricing');
    }

    /** The carat whose purity is nearest to a tested purity percentage. */
    public static function nearestCarat(string $metal, float $purityPercent): ?string
    {
        $best = null;
        $gap = INF;
        foreach (self::FINENESS[$metal] ?? [] as $carat => $fine) {
            if (abs($fine - $purityPercent) < $gap) {
                $gap = abs($fine - $purityPercent);
                $best = $carat;
            }
        }

        return $best;
    }

    /** The deduction for a metal at a tested purity; 2% when none is set. */
    public static function percentFor(string $metal, float $purityPercent): float
    {
        $carat = self::nearestCarat($metal, $purityPercent);

        return (float) (static::where('metal', $metal)->where('purity', $carat)->value('percent') ?? 2.0);
    }
}
