<?php
namespace App\Models\Movement;

use App\Models\Purchase\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Cash or metal paid to a karigar. Metal payments come off the raw-metal balance. */
class KarigarPayment extends Model
{
    protected $fillable = ['vendor_id', 'batch_id', 'kind', 'amount', 'metal', 'purity', 'weight', 'note', 'user_id'];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function batch()
    {
        return $this->belongsTo(KarigarRawBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
