<?php
namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

class StockAuditLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_audit_id', 'item_id', 'code', 'result'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
