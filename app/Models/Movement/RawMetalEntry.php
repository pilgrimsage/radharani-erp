<?php
namespace App\Models\Movement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The raw-metal balance by metal and carat (8 Oct change list, 6.5). Entries are only ever
 * added: raw-material purchases put metal in; advances and payments to karigars take it out.
 */
class RawMetalEntry extends Model
{
    protected $fillable = ['metal', 'purity', 'weight', 'source_type', 'source_id', 'note', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $metal, ?string $purity, float $weight, string $sourceType, ?int $sourceId = null, ?string $note = null): self
    {
        return static::create([
            'metal' => $metal,
            'purity' => trim((string) $purity) ?: '-',
            'weight' => $weight,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'note' => $note,
            'user_id' => Auth::id(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, object{metal: string, purity: string, weight: float}> */
    public static function balances()
    {
        return static::selectRaw('metal, purity, SUM(weight) as weight')->groupBy('metal', 'purity')
            ->orderBy('metal')->orderBy('purity')->get();
    }

    public static function balanceFor(string $metal, ?string $purity): float
    {
        return (float) static::where('metal', $metal)->where('purity', trim((string) $purity) ?: '-')->sum('weight');
    }
}
