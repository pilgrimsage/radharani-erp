<?php
namespace App\Models\Customer;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Authenticatable — customers log in through a separate 'customer' guard,
// completely independent of the staff `users` table/guard. Never merge
// these two — a customer must never be assignable a staff role.
class Customer extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'phone', 'address', 'email', 'password', 'gstin', 'balance', 'status',
        'loyalty_points', 'referral_code', 'referral_opted_at', 'referred_by', 'imported_from_tally',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function sales()
    {
        return $this->hasMany(\App\Models\Sales\Sale::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function installmentSchemes()
    {
        return $this->hasMany(InstallmentScheme::class);
    }

    public function referredBy()
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    // Customers this one referred (inverse of referredBy) — used by the
    // owner-facing Referral Overview report.
    public function referrals()
    {
        return $this->hasMany(self::class, 'referred_by');
    }
}
