<?php
namespace App\Models;

use App\Models\Movement\Movement;
use App\Models\Stock\Box;
use App\Models\Stock\Item;
use App\Models\Stock\Packet;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An owner-managed place in the shop: Vault, Counter 1, Display... Anything
 * not recorded as out of the vault is in the Vault location.
 */
class Location extends Model
{
    use LogsActivity;

    public const TYPES = ['vault' => 'Vault', 'counter' => 'Counter', 'display' => 'Display', 'other' => 'Other'];

    protected $fillable = ['name', 'type', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'type', 'sort_order', 'is_active'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('stock');
    }

    public function movements()
    {
        return $this->hasMany(Movement::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public static function vault(): ?self
    {
        return static::where('type', 'vault')->orderBy('id')->first();
    }

    /** Where a vault_out with no recorded location (older rows) is taken to have gone. */
    public static function defaultFloor(): ?self
    {
        return static::active()->where('type', '!=', 'vault')->orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Is this piece recorded as in the vault? The latest vault movement of the piece itself decides,
     * else that of its packet, else its box; no movement at all means it never left the vault.
     */
    public static function isInVault(Item $item): bool
    {
        $chain = collect([['item', $item->id]]);
        if ($item->packet_id) {
            $chain->push(['packet', $item->packet_id]);
            if ($boxId = Packet::withTrashed()->whereKey($item->packet_id)->value('box_id')) {
                $chain->push(['box', $boxId]);
            }
        }

        foreach ($chain as [$type, $id]) {
            $last = Movement::where('trackable_type', $type)->where('trackable_id', $id)
                ->whereIn('movement_type', Movement::PAIRS['vault'])->latest('id')->first();
            if ($last) {
                return $last->movement_type === 'vault_in';
            }
        }

        return true;
    }

    /** Record a piece as moved from the vault to the counter (the first counter unless told otherwise). */
    public static function sendToFloor(Item $item, ?int $locationId = null): Movement
    {
        return Movement::create([
            'trackable_type' => 'item',
            'trackable_id' => $item->id,
            'movement_type' => 'vault_out',
            'location_id' => $locationId ?? static::defaultFloor()?->id,
            'purpose_label' => 'Counter display',
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'weight_at_dispatch' => (float) $item->weight,
        ]);
    }

    /**
     * Who is out of the vault right now and where: latest vault-pair movement per
     * box / packet / piece that is a vault_out. Keyed "type:id".
     *
     * @return \Illuminate\Support\Collection<string, array{type: string, id: int, location_id: ?int, since: \Illuminate\Support\Carbon, user_id: ?int}>
     */
    public static function floorEntries()
    {
        $default = static::defaultFloor()?->id;
        $latest = Movement::selectRaw('MAX(id)')->whereIn('movement_type', Movement::PAIRS['vault'])
            ->groupBy('trackable_type', 'trackable_id');

        return Movement::whereIn('id', $latest)->where('movement_type', 'vault_out')
            ->get(['trackable_type', 'trackable_id', 'location_id', 'created_at', 'user_id'])
            ->mapWithKeys(fn ($m) => [$m->trackable_type . ':' . $m->trackable_id => [
                'type' => $m->trackable_type,
                'id' => (int) $m->trackable_id,
                'location_id' => $m->location_id ?? $default,
                'since' => $m->created_at,
                'user_id' => $m->user_id,
            ]]);
    }

    /**
     * Pieces (in stock) by the location they are at: the piece's own latest vault move,
     * else its packet's, else its box's, else the vault. Returns [item_id => location_id].
     */
    public static function itemLocations(): array
    {
        $entries = static::floorEntries();
        $vault = static::vault()?->id;
        $packetBox = Packet::pluck('box_id', 'id');

        $out = [];
        Item::where('status', 'in_stock')->get(['id', 'packet_id'])->each(function ($item) use ($entries, $packetBox, $vault, &$out) {
            $box = $item->packet_id ? ($packetBox[$item->packet_id] ?? null) : null;
            $e = $entries->get('item:' . $item->id)
                ?? ($item->packet_id ? $entries->get('packet:' . $item->packet_id) : null)
                ?? ($box ? $entries->get('box:' . $box) : null);
            $out[$item->id] = $e['location_id'] ?? $vault;
        });

        return $out;
    }
}
