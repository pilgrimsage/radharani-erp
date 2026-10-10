<?php
namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customer\Customer;
use App\Models\User;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Sale extends Model
{
    use LogsActivity;

    public $timestamps = false;
    // created_at needs an explicit cast because $timestamps=false stops
    // Eloquent's default date-casting too — without this, ->created_at is
    // a raw string and every ->format() call on it (SalesHistory,
    // InvoiceView, the portal dashboard) fatal-errors.
    protected $casts = ['additional_charges' => 'array', 'created_at' => 'datetime'];
    protected $fillable = [
        'customer_id', 'referral_customer_id', 'invoice_number', 'type',
        'additional_charges', 'discount', 'adjustment_type', 'adjustment_value', 'accountant_note',
        'order_override_note', 'total', 'confirmed_by_accountant', 'created_by',
    ];

    protected static function booted()
    {
        static::creating(fn ($sale) => $sale->created_at ??= now());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->belongsToMany(\App\Models\Stock\Item::class, 'sale_items')
            ->withPivot('price_at_sale');
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referral_customer_id');
    }

    /** What has been paid so far, across every part. */
    public function getPaidAttribute(): float
    {
        return round((float) ($this->payments_sum_amount ?? $this->payments()->sum('amount')), 2);
    }

    /** Worked out, never stored: the bill less everything paid. */
    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - $this->paid, 2);
    }

    /** The bill number staff read: the Tally bill number once verified, else the holding reference. */
    public function getBillNumberAttribute(): string
    {
        return $this->confirmed_by_accountant ? $this->invoice_number : 'Reserved #' . $this->id;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Feeds the Audit Log Viewer (Section 17). Sales rows are insert-only
    // (rule 1), so only "created" fires — a clean audit trail per sale.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'invoice_number', 'type', 'total', 'discount', 'confirmed_by_accountant', 'created_by'])
            ->useLogName('sale');
    }
}
