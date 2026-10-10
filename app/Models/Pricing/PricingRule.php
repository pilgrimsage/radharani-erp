<?php
namespace App\Models\Pricing;

use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One pricing rule (8 Oct change list, 11.2): a making charge, an additional charge, a discount
 * or a hallmarking charge, set for a product, a category, a price range, a metal or everything,
 * optionally for one carat. The price range is measured on the metal value only.
 */
class PricingRule extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['kind', 'scope', 'item_id', 'category', 'metal', 'purity', 'min_value', 'max_value', 'name', 'calc', 'value', 'active', 'valid_from', 'valid_to'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('pricing');
    }

    public const KINDS = ['making' => 'Making charge', 'additional' => 'Additional charge', 'discount' => 'Discount', 'hallmark' => 'Hallmarking charge'];

    // Scopes that make sense for each kind.
    public const SCOPES = [
        'making' => ['product', 'category', 'price_range'],
        'additional' => ['product', 'category', 'price_range'],
        'discount' => ['product', 'category', 'price_range'],
        'hallmark' => ['product', 'category', 'price_range', 'metal', 'all'],
    ];

    public const SCOPE_LABELS = ['product' => 'A product', 'category' => 'A category', 'price_range' => 'A price range', 'metal' => 'A metal', 'all' => 'Everything'];

    public const CALC_LABELS = ['percentage' => 'Percentage', 'per_gram' => 'Per gram', 'per_piece' => 'Per piece'];

    // Higher wins.
    private const RANK = ['product' => 4, 'category' => 3, 'price_range' => 2, 'metal' => 1, 'all' => 0];

    protected $fillable = [
        'kind', 'scope', 'item_id', 'category', 'metal', 'purity', 'min_value', 'max_value', 'name',
        'calc', 'value', 'active', 'valid_from', 'valid_to', 'created_by',
    ];

    protected $casts = ['active' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCurrentlyValid(): bool
    {
        return $this->active
            && (! $this->valid_from || $this->valid_from->lte(today()))
            && (! $this->valid_to || $this->valid_to->gte(today()));
    }

    /** Does this rule apply to a piece with this metal, carat, category and metal value? */
    public function matches(Item $item, float $metalValue): bool
    {
        $metal = $item->metal ?: 'gold';
        if ($this->metal && $this->metal !== $metal) {
            return false;
        }
        if ($this->purity && strtoupper($this->purity) !== strtoupper(trim((string) $item->purity))) {
            return false;
        }

        return match ($this->scope) {
            'product' => $item->id && $this->item_id === $item->id,
            'category' => $this->category !== null && mb_strtolower($this->category) === mb_strtolower(trim((string) $item->category)),
            'price_range' => ($this->min_value === null || $metalValue >= (float) $this->min_value)
                && ($this->max_value === null || $metalValue < (float) $this->max_value),
            'metal' => $this->metal === $metal,
            'all' => true,
        };
    }

    /** Specificity: scope first, then a carat-specific rule beats an any-carat one. */
    public function rank(): int
    {
        return self::RANK[$this->scope] * 10 + ($this->purity ? 1 : 0);
    }

    /** What the rule comes to, for a piece with this net weight and metal value. */
    public function amount(float $netWeight, float $metalValue, float $subtotal = 0.0): float
    {
        return round(match ($this->calc) {
            'percentage' => ($this->kind === 'discount' ? $subtotal : $metalValue) * (float) $this->value / 100,
            'per_gram' => $netWeight * (float) $this->value,
            'per_piece' => (float) $this->value,
        }, 2);
    }

    public function describe(): string
    {
        $v = rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.');

        return match ($this->calc) {
            'percentage' => $v . '%',
            'per_gram' => '₹' . $v . ' / g',
            'per_piece' => '₹' . $v . ' / piece',
        };
    }

    /** The scope as a sentence: "Category Sakha, 22K", "₹25,000 to ₹50,000", "Everything". */
    public function target(): string
    {
        $carat = $this->purity ? ', ' . $this->purity : '';

        return match ($this->scope) {
            'product' => 'Product ' . ($this->item?->label ?? '#' . $this->item_id) . $carat,
            'category' => 'Category ' . $this->category . $carat . ($this->metal ? ' (' . ucfirst($this->metal) . ')' : ''),
            'price_range' => 'Metal value ' . ($this->min_value !== null ? '₹' . number_format((float) $this->min_value) : 'up') . ' to ' . ($this->max_value !== null ? '₹' . number_format((float) $this->max_value) : 'any') . $carat . ($this->metal ? ' (' . ucfirst($this->metal) . ')' : ''),
            'metal' => ucfirst((string) $this->metal) . ' pieces' . $carat,
            'all' => 'Every piece' . $carat,
        };
    }
}
