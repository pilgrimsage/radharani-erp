<?php
namespace App\Models\Stock;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A batch of entries identified by its date and time. */
class EntryBatch extends Model
{
    protected $fillable = ['kind', 'note', 'user_id'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->created_at->format('j M Y, g:i a');
    }
}
