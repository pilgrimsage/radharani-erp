<?php
namespace App\Models\Orders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A reference image the customer left with an order, stored through PhotoCompressionService. */
class OrderImage extends Model
{
    protected $fillable = ['order_id', 'path', 'created_by'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getUrlAttribute(): ?string
    {
        return Storage::disk('public')->exists($this->path) ? asset('storage/' . $this->path) : null;
    }
}
