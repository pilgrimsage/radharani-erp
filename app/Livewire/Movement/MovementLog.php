<?php
namespace App\Livewire\Movement;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Customer\CustomerMaterialJob;
use App\Models\Movement\KarigarRawBatch;
use App\Models\Movement\Movement;
use App\Models\User;
use App\Support\Trackables;
use Livewire\Attributes\Url;
use Livewire\Component;

// Every movement row, newest first: who moved what, where, and when. Read only.
class MovementLog extends Component
{
    use WithDataTable;

    public const GROUPS = [
        'vault' => 'Vault ↔ Counter',
        'karigar' => 'Karigar',
        'hallmark' => 'Hallmarking',
        'photo' => 'Photography',
        'custom' => 'Custom purpose',
        'correction' => 'Corrections',
    ];

    #[Url(as: 'type', except: '')]
    public string $group = '';

    #[Url(as: 'staff', except: '')]
    public string $userId = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    protected function sortableColumns(): array
    {
        return ['when' => 'movements.created_at', 'type' => 'movements.movement_type', 'by' => 'users.name'];
    }

    protected function defaultSort(): array
    {
        return ['when', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['group', 'userId', 'dateFrom', 'dateTo'];
    }

    public function render()
    {
        $term = trim($this->search);

        $query = Movement::query()
            ->select('movements.*')
            ->leftJoin('users', 'users.id', '=', 'movements.user_id')
            ->with(['user:id,name', 'approver:id,name', 'doneBy:id,name'])
            ->when($this->group !== '', fn ($q) => $q->whereIn('movements.movement_type',
                Movement::PAIRS[$this->group] ?? [$this->group]))
            ->when($this->userId !== '', fn ($q) => $q->where('movements.user_id', $this->userId))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('movements.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('movements.created_at', '<=', $this->dateTo))
            ->when($term !== '', fn ($q) => $q
                ->leftJoin('items', fn ($j) => $j->on('items.id', '=', 'movements.trackable_id')->where('movements.trackable_type', 'item'))
                ->leftJoin('packets', fn ($j) => $j->on('packets.id', '=', 'movements.trackable_id')->where('movements.trackable_type', 'packet'))
                ->leftJoin('boxes', fn ($j) => $j->on('boxes.id', '=', 'movements.trackable_id')->where('movements.trackable_type', 'box'))
                ->where(fn ($q) => $q
                    ->where('items.huid_code', 'like', "%{$term}%")
                    ->orWhere('items.internal_code', 'like', "%{$term}%")
                    ->orWhere('packets.code', 'like', "%{$term}%")
                    ->orWhere('boxes.code', 'like', "%{$term}%")
                    ->orWhere('movements.counterparty', 'like', "%{$term}%")
                    ->orWhere('movements.purpose_label', 'like', "%{$term}%")
                    ->orWhere('movements.tagged_by', 'like', "%{$term}%")));

        $movements = $this->applySorting($query)->orderByDesc('movements.id')->paginate($this->perPageValue());
        $loaded = Trackables::load($movements->getCollection());

        $latestVault = Movement::query()->selectRaw('MAX(id)')->whereIn('movement_type', Movement::PAIRS['vault'])->groupBy('trackable_type', 'trackable_id');

        return view('livewire.movement.movement-log', [
            'movements' => $movements,
            'described' => $movements->getCollection()->mapWithKeys(fn ($m) => [
                $m->id => Trackables::describe($loaded, $m->trackable_type, $m->trackable_id),
            ]),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'now' => [
                'counter' => Movement::whereIn('id', $latestVault)->where('movement_type', 'vault_out')->count(),
                'karigar' => Movement::openItemDispatches(['karigar'])->count()
                    + CustomerMaterialJob::where('status', 'out')->count()
                    + KarigarRawBatch::whereIn('status', ['dispatched', 'partially_returned'])->count(),
                'hallmark' => Movement::openItemDispatches(['hallmark'])->count(),
                'other' => Movement::openItemDispatches(['photo', 'custom'])->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Movement Log · Radharani Jewellery']);
    }
}
