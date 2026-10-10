<?php
namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Packet extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['box_id', 'code', 'label'];

    // box_id changes are how a packet's "moved to another box" history is
    // reconstructed on Packet Detail and Box Detail.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['box_id', 'code', 'label'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class, 'target_id')->where('target_type', 'packet');
    }

    public function movements()
    {
        return \App\Models\Movement\Movement::where('trackable_type', 'packet')
            ->where('trackable_id', $this->id);
    }
}
