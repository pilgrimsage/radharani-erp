<?php
namespace App\Models\Movement;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Movement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'trackable_type', 'trackable_id', 'movement_type', 'purpose_label',
        'user_id', 'done_by_employee_id', 'counterparty', 'expected_return', 'actual_return',
        'weight_at_dispatch', 'weight_at_return', 'weight_loss', 'tagged_by',
        'photo_path', 'bill_path', 'note',
        'reverses_movement_id', 'approved_by',
    ];

    public function doneBy()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'done_by_employee_id');
    }

    protected $casts = ['expected_return' => 'date', 'actual_return' => 'date'];

    // Out/in pairs for the round-trip screens. Vault is the odd one out:
    // "out" of the vault means on the counter, not out of the shop.
    public const PAIRS = [
        'vault' => ['vault_out', 'vault_in'],
        'karigar' => ['karigar_out', 'karigar_in'],
        'hallmark' => ['hallmark_out', 'hallmark_in'],
        'photo' => ['photo_out', 'photo_in'],
        'custom' => ['custom_out', 'custom_in'],
        'melt' => ['melt_out', 'melt_in'],
    ];

    public const LABELS = [
        'vault_out' => 'To counter', 'vault_in' => 'To vault',
        'karigar_out' => 'To karigar', 'karigar_in' => 'From karigar',
        'hallmark_out' => 'To hallmarking', 'hallmark_in' => 'From hallmarking',
        'photo_out' => 'Out for photos', 'photo_in' => 'Back from photos',
        'custom_out' => 'Out (custom)', 'custom_in' => 'Back (custom)',
        'melt_out' => 'To melting', 'melt_in' => 'From melting',
        'correction' => 'Correction',
    ];

    /**
     * Item movements that are still "out": the latest row of the given pairs
     * is an *_out and the piece is still marked dispatched. One query instead
     * of walking every dispatched item's history in PHP.
     */
    public static function openItemDispatches(array $pairs)
    {
        $types = collect($pairs)->flatMap(fn ($p) => self::PAIRS[$p])->all();
        $outTypes = collect($pairs)->map(fn ($p) => self::PAIRS[$p][0])->all();

        $latest = static::query()->selectRaw('MAX(id)')
            ->where('trackable_type', 'item')
            ->whereIn('movement_type', $types)
            ->groupBy('trackable_id');

        return static::query()
            ->whereIn('id', $latest)
            ->whereIn('movement_type', $outTypes)
            ->whereIn('trackable_id', \App\Models\Stock\Item::where('status', 'dispatched')->select('id'));
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->movement_type] ?? ucwords(str_replace('_', ' ', $this->movement_type));
    }

    public function getIsOutAttribute(): bool
    {
        return str_ends_with($this->movement_type, '_out');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->expected_return && $this->expected_return->isBefore(today());
    }

    // Feeds the Audit Log Viewer (Section 17). Movements are insert-only
    // (rule 1), so only "created" events will ever fire here — which is
    // exactly the tamper-evident trail that rule exists for.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['trackable_type', 'trackable_id', 'movement_type', 'user_id', 'done_by_employee_id', 'counterparty', 'reverses_movement_id'])
            ->useLogName('movement');
    }

    // Never update or delete a movement at the app layer.
    // Mistakes are corrected via a new 'correction' row referencing
    // reverses_movement_id, approved by an owner (approved_by).

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reverses()
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    // Only meaningful when trackable_type is 'item'; lets list screens eager-load pieces.
    public function item()
    {
        return $this->belongsTo(\App\Models\Stock\Item::class, 'trackable_id');
    }

    public function trackable()
    {
        return match ($this->trackable_type) {
            'item' => \App\Models\Stock\Item::find($this->trackable_id),
            'packet' => \App\Models\Stock\Packet::find($this->trackable_id),
            'box' => \App\Models\Stock\Box::find($this->trackable_id),
        };
    }
}
