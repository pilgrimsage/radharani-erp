<?php
namespace App\Models\Customer;

use App\Models\Sales\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Referral points earned by a referrer. Append-only, like the other ledgers. */
class ReferralPoint extends Model
{
    public $timestamps = false;

    protected $casts = ['created_at' => 'datetime'];

    protected $fillable = ['customer_id', 'points', 'sale_id', 'note', 'user_id', 'created_at'];

    protected static function booted()
    {
        static::creating(fn ($p) => $p->created_at ??= now());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
