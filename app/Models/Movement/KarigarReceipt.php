<?php
namespace App\Models\Movement;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One part-return against a karigar batch. */
class KarigarReceipt extends Model
{
    protected $fillable = ['batch_id', 'pieces', 'weight_received', 'weight_loss', 'disposition', 'photo_path', 'note', 'user_id', 'done_by_employee_id'];

    public function batch()
    {
        return $this->belongsTo(KarigarRawBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function doneBy()
    {
        return $this->belongsTo(Employee::class, 'done_by_employee_id');
    }
}
