<?php
namespace App\Models\Exchange;

use App\Models\Customer\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ExchangeTransaction extends Model
{
    use LogsActivity;

    // Every edit before settlement is logged with its before and after (8 Oct change list, 9.1).
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'metal', 'gross_weight', 'description', 'net_weight', 'purity_test_1', 'purity_test_2', 'purity_averaged', 'preset_deduction_percent', 'deductable_weight', 'stage', 'final_value'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('exchange');
    }

    public function getIsSettledAttribute(): bool
    {
        return $this->stage === 'settled';
    }

    protected $fillable = [
        'customer_id', 'metal', 'gross_weight', 'description', 'net_weight',
        'purity_test_1', 'purity_test_2', 'purity_averaged',
        'preset_deduction_percent', 'deductable_weight', 'stage',
        'final_value', 'settled_by', 'settled_at', 'created_by',
    ];

    protected $casts = ['settled_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function settler()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
