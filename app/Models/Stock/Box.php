<?php
namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Box extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['code', 'label'];

    // Renames and creation feed the Box Detail history timeline.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'label'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    public function packets()
    {
        return $this->hasMany(Packet::class);
    }

    public function items()
    {
        return $this->hasManyThrough(Item::class, Packet::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class, 'target_id')->where('target_type', 'box');
    }

    public function movements()
    {
        return \App\Models\Movement\Movement::where('trackable_type', 'box')
            ->where('trackable_id', $this->id);
    }
}
