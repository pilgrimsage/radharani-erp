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
        'in_stock_item_id', 'out_of_stock', 'sourcing', 'status', 'expected_ready_date',
        'converted_sale_id', 'created_by',
    ];

    protected $casts = ['locked_at' => 'datetime', 'expected_ready_date' => 'date'];

    // How each way of getting the piece moves through the shop (8 Oct change list, 10.3).
    public const SOURCING = [
        'stock' => ['In stock', 'A piece already in stock', ['sales']],
        'karigar' => ['Made by a karigar', 'Not in stock; a karigar makes it', ['karigar', 'hallmark', 'sales']],
        'bought_finished' => ['Bought in, finished and hallmarked', 'Not in stock; comes in ready', ['sales']],
        'bought_unhallmarked' => ['Bought in, not hallmarked', 'Finished but still needs hallmarking', ['hallmark', 'sales']],
        'bought_unfinished' => ['Bought in, not finished', 'Needs finishing and hallmarking', ['karigar', 'hallmark', 'sales']],
    ];

    public function images()
    {
        return $this->hasMany(OrderImage::class);
    }

    public function karigarBatches()
    {
        return $this->hasMany(\App\Models\Movement\KarigarRawBatch::class, 'order_id');
    }

    public function hallmarkBatches()
    {
        return $this->hasMany(\App\Models\Movement\HallmarkBatch::class, 'order_id');
    }

    /** The steps this order goes through and where each one stands: todo, doing or done. */
    public function pathSteps(): array
    {
        $steps = [];
        foreach (self::SOURCING[$this->sourcing][2] ?? ['sales'] as $step) {
            $status = 'todo';
            $detail = null;
            if ($step === 'karigar') {
                $b = $this->karigarBatches;
                $status = $b->isEmpty() ? 'todo' : ($b->contains(fn ($x) => $x->is_open) ? 'doing' : 'done');
                $detail = $b->isEmpty() ? 'Not issued yet' : $b->count() . ' ' . \Illuminate\Support\Str::plural('batch', $b->count()) . ($status === 'doing' ? ' out' : ' back');
            } elseif ($step === 'hallmark') {
                $b = $this->hallmarkBatches;
                $status = $b->isEmpty() ? 'todo' : ($b->contains(fn ($x) => $x->is_open) ? 'doing' : 'done');
                $detail = $b->isEmpty() ? 'Not sent yet' : $b->count() . ' ' . \Illuminate\Support\Str::plural('batch', $b->count()) . ($status === 'doing' ? ' out' : ' back');
            } else {
                $status = $this->converted_sale_id ? 'done' : 'todo';
                $detail = $this->converted_sale_id ? 'Sale #' . $this->converted_sale_id : 'Not sold yet';
            }
            $steps[] = ['step' => $step, 'status' => $status, 'detail' => $detail];
        }

        return $steps;
    }

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
