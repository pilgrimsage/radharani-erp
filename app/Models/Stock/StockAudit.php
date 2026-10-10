<?php
namespace App\Models\Stock;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One finished audit of a box. Written once when the audit is saved. */
class StockAudit extends Model
{
    protected $fillable = ['box_id', 'user_id', 'expected_count', 'present_count', 'missing_count', 'extra_count', 'note'];

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lines()
    {
        return $this->hasMany(StockAuditLine::class);
    }

    public function getCleanAttribute(): bool
    {
        return $this->missing_count === 0 && $this->extra_count === 0;
    }
}
