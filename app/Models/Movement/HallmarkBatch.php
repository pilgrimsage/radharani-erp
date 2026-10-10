<?php
namespace App\Models\Movement;

use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One dispatch to a hallmarking centre, identified by its date and time. */
class HallmarkBatch extends Model
{
    protected $fillable = [
        'vendor_id', 'source', 'order_id', 'description', 'pieces_counted', 'weight_counted', 'huid_expected',
        'expected_return', 'status', 'photo_path', 'note', 'user_id', 'done_by_employee_id',
        'closed_by', 'closed_at', 'close_note',
    ];

    protected $casts = ['expected_return' => 'date', 'closed_at' => 'datetime'];

    public function centre()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lines()
    {
        return $this->hasMany(HallmarkBatchItem::class);
    }

    public function receipts()
    {
        return $this->hasMany(HallmarkReceipt::class);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['dispatched', 'partially_returned']);
    }

    public function getLabelAttribute(): string
    {
        return $this->created_at->format('j M Y, g:i a');
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, ['dispatched', 'partially_returned'], true);
    }

    /** Pieces in the batch: counted ones plus the tagged ones. */
    public function getPiecesOutAttribute(): int
    {
        return $this->pieces_counted + ($this->lines_count ?? $this->lines()->count());
    }

    public function getPiecesBackAttribute(): int
    {
        return (int) ($this->receipts_sum_pieces ?? $this->receipts()->sum('pieces'));
    }

    public function getPiecesPendingAttribute(): int
    {
        return max(0, $this->pieces_out - $this->pieces_back);
    }
}
