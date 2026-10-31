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
        'packet_id', 'metal', 'mrp', 'huid_code', 'internal_code', 'category', 'category_id', 'entry_batch_id', 'source_hallmark_batch_id', 'purity',
        'weight', 'description', 'hsn_code', 'making_type', 'making_value',
        'pair_group_id', 'source_karigar_batch_id', 'source_purchase_item_id',
        'status', 'net_weight', 'stones', 'stone_value', 'default_additional_charges', 'diamond_carat', 'diamond_cut', 'diamond_summary_no',
        // website listing
        'show_on_website', 'web_name', 'slug', 'web_description', 'storefront_collection_id',
        'audiences', 'occasions', 'dimensions', 'size_type', 'size_label', 'is_bestseller', 'listed_at',
    ];

    protected $casts = [
        'show_on_website' => 'boolean',
        'is_bestseller' => 'boolean',
        'audiences' => 'array',
        'occasions' => 'array',
        'default_additional_charges' => 'array',
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

            // A piece with no packet is on hold, not in stock; putting it in a packet makes it stock.
            // Done here so every path (form, import, review, assignment) agrees.
            if ($item->status === 'in_stock' && ! $item->packet_id) {
                $item->status = 'on_hold';
            } elseif ($item->status === 'on_hold' && $item->packet_id) {
                $item->status = 'in_stock';
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
                'net_weight', 'stones', 'stone_value', 'default_additional_charges', 'diamond_carat', 'diamond_cut', 'diamond_summary_no', 'show_on_website', 'web_name', 'slug',
                'storefront_collection_id', 'is_bestseller',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    /** The piece's default additional charges as clean pairs: [['name' => 'Sakha', 'amount' => 250.0], ...]. */
    public function defaultCharges(): array
    {
        return collect($this->default_additional_charges ?? [])
            ->filter(fn ($c) => ($c['name'] ?? '') !== '' && is_numeric($c['amount'] ?? null) && (float) $c['amount'] > 0)
            ->map(fn ($c) => ['name' => (string) $c['name'], 'amount' => round((float) $c['amount'], 2)])->values()->all();
    }

    // The code staff actually read off the tag: HUID when present, else the internal code.
    public function getLabelAttribute(): string
    {
        return $this->huid_code ?: (string) $this->internal_code;
    }

    // A piece can hold both codes (a HUID added later to a piece that already had its tag code). Where a
    // piece is named in a search or scan, show both so the code on the tag is never hidden behind the HUID.
    public function getLabelWithTagAttribute(): string
    {
        return $this->huid_code && $this->internal_code
            ? "{$this->huid_code} (tag {$this->internal_code})"
            : $this->label;
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
        } while (static::where('internal_code', $code)->exists() || ReservedCode::where('code', $code)->exists());

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

    /**
     * One search behaviour for every piece list (8 Oct change list, 1.2): finds a piece by its
     * HUID, internal code, category, description or website name, and also everything inside a
     * packet or box when the packet or box code or label is typed, or a QR sticker code.
     */
    public function scopeSearchAnything($query, string $term)
    {
        // A scanned QR sticker arrives as its link (…/stock/q/CODE): search by the code in it.
        $term = preg_match('~/q/([A-Za-z0-9]+)/?$~', trim($term), $m) ? $m[1] : trim($term);
        $like = '%' . $term . '%';
        $code = strtoupper($term);

        // Qualified with the table: list pages join packets and boxes.
        return $query->where(function ($q) use ($like, $code) {
            $q->where('items.huid_code', 'like', $like)
                ->orWhere('items.internal_code', 'like', $like)
                ->orWhere('items.category', 'like', $like)
                ->orWhere('items.description', 'like', $like)
                ->orWhere('items.web_name', 'like', $like)
                ->orWhereIn('items.packet_id', Packet::query()->select('id')
                    ->where('code', 'like', $like)->orWhere('label', 'like', $like)
                    ->orWhereIn('box_id', Box::query()->select('id')->where('code', 'like', $like)->orWhere('label', 'like', $like)))
                ->orWhereIn('items.id', QrCode::query()->select('target_id')->where('target_type', 'item')->where('code', $code))
                // A packet's or box's own sticker finds the pieces inside it.
                ->orWhereIn('items.packet_id', QrCode::query()->select('target_id')->where('target_type', 'packet')->where('code', $code))
                ->orWhereIn('items.packet_id', Packet::query()->select('id')->whereIn('box_id', QrCode::query()->select('target_id')->where('target_type', 'box')->where('code', $code)));
        });
    }

    /** What a piece still lacks before it can be priced and sold: a list of field names, empty when complete. */
    public function missingDetails(): array
    {
        return array_keys(array_filter([
            'metal' => ! $this->metal,
            'purity' => trim((string) $this->purity) === '',
            'category' => trim((string) $this->category) === '',
            'weight' => (float) $this->weight <= 0,
        ]));
    }

    // Why this piece can't be deleted, or null if it can (8 Oct change list, 4.3).
    public function deletionBlocker(): ?string
    {
        if ($this->sales()->exists()) {
            return 'This piece has been sold, so it must stay in the records.';
        }
        if (! in_array($this->status, ['in_stock', 'on_hold'], true)) {
            return 'This piece is out of the store or in a process (' . str_replace('_', ' ', $this->status) . ').';
        }

        return null;
    }

    public function sales()
    {
        return $this->belongsToMany(\App\Models\Sales\Sale::class, 'sale_items')->withPivot('price_at_sale', 'weight_listed', 'weight_at_sale', 'weight_note', 'making_kind', 'making_default_rate', 'making_rate', 'making_amount');
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

    /** The other pieces tagged with this one (a pair, or a set of any size); empty for a single piece. */
    public function mates()
    {
        return $this->pair_group_id
            ? $this->pairedWith()->orderBy('id')->get()
            : $this->newCollection();
    }

    /**
     * Tags this piece with another (28 Oct): earrings, or any set that is kept together. A group is the shared pair_group_id.
     * A single piece joins the other's group (or they start one); two pieces that already sit in different groups are refused,
     * so unpair one first. Per-model updates, so every change lands in the pieces' history.
     */
    public function tagWith(self $other): void
    {
        if ($other->id === $this->id) {
            throw new \InvalidArgumentException('A piece cannot be tagged with itself.');
        }
        if ($this->pair_group_id && $this->pair_group_id === $other->pair_group_id) {
            return;
        }
        if ($this->pair_group_id && $other->pair_group_id) {
            throw new \InvalidArgumentException("{$other->label} is already tagged with other pieces. Unpair one of them first.");
        }

        $group = $this->pair_group_id ?: $other->pair_group_id ?: min($this->id, $other->id);
        foreach ([$this, $other] as $piece) {
            if ($piece->pair_group_id !== $group) {
                $piece->update(['pair_group_id' => $group]);
            }
        }
    }

    /** Takes this piece out of its group. A group left with one piece is dissolved, so nothing is "paired" with nothing. */
    public function untag(): void
    {
        $group = $this->pair_group_id;
        if (! $group) {
            return;
        }

        $this->update(['pair_group_id' => null]);
        $left = static::where('pair_group_id', $group)->get();
        if ($left->count() === 1) {
            $left->first()->update(['pair_group_id' => null]);
        }
    }
}
