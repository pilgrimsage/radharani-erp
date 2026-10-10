<?php
namespace App\Models\Movement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HallmarkReceipt extends Model
{
    protected $fillable = [
        'hallmark_batch_id', 'pieces', 'with_huid', 'without_huid', 'weight_received', 'weight_loss',
        'tagged_by', 'photo_path', 'note', 'user_id', 'done_by_employee_id',
    ];

    public function batch()
    {
        return $this->belongsTo(HallmarkBatch::class, 'hallmark_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
