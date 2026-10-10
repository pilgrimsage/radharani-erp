<?php
namespace App\Models\Movement;

use App\Models\Stock\Item;
use Illuminate\Database\Eloquent\Model;

class HallmarkBatchItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['hallmark_batch_id', 'item_id', 'weight_out', 'returned'];

    protected $casts = ['returned' => 'boolean'];

    public function batch()
    {
        return $this->belongsTo(HallmarkBatch::class, 'hallmark_batch_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
