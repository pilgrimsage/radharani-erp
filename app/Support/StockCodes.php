<?php
namespace App\Support;

use Illuminate\Database\Eloquent\Model;

// Suggests the next code in an existing series (BOX-02 -> BOX-03, PKT-3-3 -> PKT-3-4)
// so the add forms come pre-filled. Staff can always overwrite the suggestion.
class StockCodes
{
    /** @param class-string<Model> $model */
    public static function next(string $model, string $prefix): string
    {
        $base = method_exists($model, 'bootSoftDeletes') ? $model::withTrashed() : $model::query();
        $codes = (clone $base)->where('code', 'like', $prefix . '%')->pluck('code');

        $max = 0;
        $width = 2;
        foreach ($codes as $code) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $code, $m)) {
                $max = max($max, (int) $m[1]);
                $width = max($width, strlen($m[1]));
            }
        }

        do {
            $max++;
            $candidate = $prefix . str_pad((string) $max, $width, '0', STR_PAD_LEFT);
        } while ((clone $base)->where('code', $candidate)->exists());

        return $candidate;
    }
}
