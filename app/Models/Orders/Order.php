<?php
namespace App\Models\Orders;

use App\Models\Customer\Customer;
use App\Models\Sales\Sale;
use App\Models\Stock\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Order extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id', 'product_description', 'category', 'metal',
        'estimated_weight', 'estimated_value', 'advance_amount',
        'full_payment_now', 'rate_locked', 'locked_rate', 'locked_at',
        'in_stock_item_id', 'out_of_stock', 'status', 'expected_ready_date',
        'converted_sale_id', 'created_by',
    ];

    protected $casts = ['locked_at' => 'datetime', 'expected_ready_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'status', 'rate_locked', 'converted_sale_id'])
            ->useLogName('order');
    }

    /** An order still open against this piece (customers often leave an advance on a stock item). */
    public static function openForItem(int $itemId): ?self
    {
        return static::with('customer:id,name')->where('in_stock_item_id', $itemId)
            ->whereIn('status', ['placed', 'confirmed', 'ready'])->latest('id')->first();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function stockItem()
    {
        return $this->belongsTo(Item::class, 'in_stock_item_id');
    }

    public function convertedSale()
    {
        return $this->belongsTo(Sale::class, 'converted_sale_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
