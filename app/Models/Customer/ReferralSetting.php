<?php
namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** The owner's rules for referral points. A single row, read through current(). */
class ReferralSetting extends Model
{
    use LogsActivity;

    protected $fillable = ['points_per_gram', 'first_sale_bonus', 'updated_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['points_per_gram', 'first_sale_bonus'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('referral');
    }

    public static function current(): self
    {
        return static::first() ?? static::create(['points_per_gram' => 0, 'first_sale_bonus' => 0]);
    }
}
