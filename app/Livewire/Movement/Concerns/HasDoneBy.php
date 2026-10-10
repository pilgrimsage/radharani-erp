<?php
namespace App\Livewire\Movement\Concerns;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

/**
 * "Done by" employee for movement screens. Defaults to the logged-in user's own
 * employee record; user_id on the movement is still always the logged-in actor.
 * Pair with <x-movement.done-by />.
 */
trait HasDoneBy
{
    public ?int $doneBy = null;

    public function mountHasDoneBy(): void
    {
        $this->doneBy = Auth::user()?->employee_id;
    }

    protected function doneByAttributes(): array
    {
        $id = $this->doneBy && Employee::where('status', 'active')->whereKey($this->doneBy)->exists() ? $this->doneBy : null;

        return ['done_by_employee_id' => $id];
    }
}
