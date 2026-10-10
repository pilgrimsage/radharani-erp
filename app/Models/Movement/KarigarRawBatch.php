<?php
namespace App\Models\Movement;

use App\Models\Purchase\Vendor;
use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One issue of work to a karigar (a "batch"), identified by its date and time.
 * Named for its first use (raw metal); the table also carries the new batch fields.
 */
class KarigarRawBatch extends Model
{
    protected $fillable = [
        'vendor_id', 'weight_out', 'metal', 'purity', 'purpose_label', 'description', 'categories',
        'pieces_expected', 'advance_cash', 'advance_metal_weight', 'advance_metal_purity', 'order_id',
        'expected_return', 'actual_return', 'weight_returned', 'weight_loss',
        'status', 'note', 'user_id', 'returned_by', 'closed_by', 'closed_at', 'close_note', 'photo_path',
    ];

    protected $casts = ['expected_return' => 'date', 'actual_return' => 'date', 'categories' => 'array', 'closed_at' => 'datetime'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receipts()
    {
        return $this->hasMany(KarigarReceipt::class, 'batch_id');
    }

    public function payments()
    {
        return $this->hasMany(KarigarPayment::class, 'batch_id');
    }

    // Finished pieces created when this batch was returned.
    public function items()
    {
        return $this->hasMany(Item::class, 'source_karigar_batch_id');
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', ['dispatched', 'partially_returned']);
    }

    /** Date and time identify the batch. */
    public function getLabelAttribute(): string
    {
        return $this->created_at->format('j M Y, g:i a');
    }

    public function getPiecesReceivedAttribute(): int
    {
        return (int) ($this->receipts_sum_pieces ?? $this->receipts()->sum('pieces'));
    }

    /** Pieces still to come back. Always shown, even after a part return. */
    public function getPiecesPendingAttribute(): int
    {
        return max(0, $this->pieces_expected - $this->pieces_received);
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, ['dispatched', 'partially_returned'], true);
    }
}
