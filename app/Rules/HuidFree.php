<?php
namespace App\Rules;

use App\Models\Stock\Item;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A HUID can sit on one piece only. Deleted pieces do not count (nobody can see or reopen them), and the
 * message names the piece that has it so staff can go and find it.
 */
class HuidFree implements ValidationRule
{
    public function __construct(private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $huid = Huid::normalize((string) $value);
        if ($huid === null) {
            return;
        }

        $other = Item::where('huid_code', $huid)->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))->first();
        if (! $other) {
            return;
        }

        $what = trim($other->category . ' ' . ((float) $other->weight > 0 ? number_format((float) $other->weight, 3) . ' g' : ''));
        $tag = $other->internal_code ? ", tag {$other->internal_code}" : '';
        $fail("The HUID {$huid} is already on another piece ({$what}{$tag}, " . str_replace('_', ' ', $other->status) . '). Search for it in Inventory.');
    }
}
