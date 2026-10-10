<?php
namespace App\Models\Stock;

use App\Models\Movement\KarigarRawBatch;
use App\Models\Purchase\PurchaseItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Item extends Model
{
    use SoftDeletes, LogsActivity;

    // Non-ambiguous charset for auto-generated codes: excludes 0/O, 1/I,
    // and L/S/5/8/B — visually close to each other on a small printed tag.
    // Confirmed requirement: 5 characters, alphanumeric, no ambiguous chars.
    public const CODE_ALPHABET = '234679ACDEFGHJKMNPQRTUVWXY';

    protected $fillable = [
        'packet_id', 'metal', 'huid_code', 'internal_code', 'category', 'category_id', 'entry_batch_id', 'purity',
        'weight', 'description', 'hsn_code', 'making_type', 'making_value',
        'pair_group_id', 'source_karigar_batch_id', 'source_purchase_item_id',
        'status', 'net_weight', 'stones', 'stone_value',
        // website listing
        'show_on_website', 'web_name', 'slug', 'web_description', 'storefront_collection_id',
        'audiences', 'occasions', 'dimensions', 'size_type', 'size_label', 'is_bestseller', 'listed_at',
    ];

    protected $casts = [
        'show_on_website' => 'boolean',
        'is_bestseller' => 'boolean',
        'audiences' => 'array',
        'occasions' => 'array',
        'listed_at' => 'datetime',
    ];

    // First time a piece goes on the website it gets its permanent web
    // address and its listing date ("New" badge, newest-first sorting).
    // Both are kept afterwards, so shared links and dates never change.
    protected static function booted(): void
    {
        static::saving(function (Item $item) {
            // Every piece sits in the Metal > Subcategory tree. Code that only knows the
            // category name (imports, seeders, tagging) is linked to it, adding the row if new.
            if (! $item->category_id && trim((string) $item->category) !== '') {
                $item->category_id = ItemCategory::firstOrCreate(['metal' => $item->metal ?: 'gold', 'name' => trim($item->category)])->id;
            }

            if ($item->show_on_website && $item->web_name) {
                $item->slug ??= static::makeSlug($item->web_name, $item->huid_code ?: $item->internal_code, $item->id);
                $item->listed_at ??= now();
            }
        });
    }

    // Packet reassignments, edits and status changes feed the Item/Packet
    // Detail history timelines (movements alone don't capture regrouping).
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'packet_id', 'status', 'huid_code', 'metal', 'category', 'purity', 'weight',
                'description', 'hsn_code', 'making_type', 'making_value', 'pair_group_id',
                'net_weight', 'stones', 'stone_value', 'show_on_website', 'web_name', 'slug',
                'storefront_collection_id', 'is_bestseller',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    // The code staff actually read off the tag: HUID when present, else the internal code.
    public function getLabelAttribute(): string
    {
        return $this->huid_code ?: (string) $this->internal_code;
    }

    // Generates a unique 5-character internal code from a charset that
    // avoids visually ambiguous characters.
    public static function generateInternalCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('internal_code', $code)->exists());

        return $code;
    }

    public function packet()
    {
        return $this->belongsTo(Packet::class);
    }

    public function sourceKarigarBatch()
    {
        return $this->belongsTo(KarigarRawBatch::class, 'source_karigar_batch_id');
    }

    public function sourcePurchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class, 'source_purchase_item_id');
    }

    public function entryBatch()
    {
        return $this->belongsTo(EntryBatch::class);
    }

    public function categoryRow()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class, 'target_id')->where('target_type', 'item');
    }

    // Why this piece can't be deleted, or null if it can (8 Oct change list, 4.3).
    public function deletionBlocker(): ?string
    {
        if ($this->sales()->exists()) {
            return 'This piece has been sold, so it must stay in the records.';
        }
        if ($this->status !== 'in_stock') {
            return 'This piece is out of the store or in a process (' . str_replace('_', ' ', $this->status) . ').';
        }

        return null;
    }

    public function sales()
    {
        return $this->belongsToMany(\App\Models\Sales\Sale::class, 'sale_items')->withPivot('price_at_sale');
    }

    public function movements()
    {
        return \App\Models\Movement\Movement::where('trackable_type', 'item')
            ->where('trackable_id', $this->id)
            ->orderByDesc('created_at');
    }

    // Latest movement = current location. Used by owner dashboard.
    public function currentMovement()
    {
        return $this->movements()->first();
    }

    public function images()
    {
        return $this->hasMany(ItemImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function storefrontCollection()
    {
        return $this->belongsTo(\App\Models\Storefront\StorefrontCollection::class);
    }

    // What the public catalogue may show: ticked for the website and physically
    // available. Reserved (unverified sale), dispatched and sold pieces drop off
    // automatically. Category mapping is checked by StorefrontCatalog.
    public function scopeOnWebsite($query)
    {
        return $query->where('show_on_website', true)
            ->where('status', 'in_stock')
            ->whereNotNull('slug')
            ->whereNotNull('web_name');
    }

    // Unique URL slug from the website name plus the tag code, e.g. "meenakari-jhumka-7k3qd".
    public static function makeSlug(string $name, ?string $code, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug(trim($name.' '.$code)) ?: 'piece';
        $slug = $base;
        $n = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    public function pairedWith()
    {
        return static::where('pair_group_id', $this->pair_group_id)
            ->where('id', '!=', $this->id);
    }
}
