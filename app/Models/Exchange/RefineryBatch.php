<?php
namespace App\Models\Exchange;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RefineryBatch extends Model
{
    protected $fillable = [
        'metal', 'weight', 'photo_path', 'status', 'refined_weight', 'refined_purity', 'deduction_percent', 'result_weight',
        'sent_at', 'returned_at', 'created_by', 'returned_by',
    ];

    protected $casts = ['sent_at' => 'datetime', 'returned_at' => 'datetime'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
