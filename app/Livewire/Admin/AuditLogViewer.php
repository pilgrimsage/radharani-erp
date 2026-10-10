<?php
namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit Log Viewer — reads spatie/laravel-activitylog's activity_log
 * table. Movement, Sale, Purchase, Item (stock) and Order all use the
 * LogsActivity trait, so every write to those writes a real audit row
 * here — exactly the tamper-evident trail rule 1 (never update/delete
 * movements/sales/purchases) is meant to support.
 */
class AuditLogViewer extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $logNameFilter = '';

    #[Url(except: '')]
    public string $userFilter = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    // What a row is about, readable: "Piece 6u3xr", "Box BOX-01". Looks through soft deletes.
    public static function subjectLabel(Activity $a): string
    {
        $kind = class_basename((string) $a->subject_type);
        $model = $a->subject_type ? $a->subject()->withTrashed()->first() : null;
        $name = match (true) {
            $model === null => $a->subject_id ? "#{$a->subject_id}" : '',
            isset($model->label) && $kind === 'Item' => $model->label,
            isset($model->code) => $model->code,
            isset($model->name) => $model->name,
            default => "#{$a->subject_id}",
        };
        $kind = ['Item' => 'Piece', 'ItemCategory' => 'Category', 'StockAudit' => 'Audit'][$kind] ?? $kind;

        return trim($kind . ' ' . $name);
    }

    protected function sortableColumns(): array
    {
        return [
            'created' => 'created_at',
            'type' => 'log_name',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['logNameFilter', 'userFilter', 'from', 'to'];
    }

    public function render()
    {
        $query = Activity::with('causer')
            ->when($this->logNameFilter, fn ($q) => $q->where('log_name', $this->logNameFilter))
            ->when($this->userFilter, fn ($q) => $q->where('causer_id', (int) $this->userFilter)->where('causer_type', \App\Models\User::class))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%"));

        return view('livewire.admin.audit-log-viewer', [
            'users' => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'activities' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'total' => Activity::count(),
                'today' => Activity::whereDate('created_at', today())->count(),
                'thisWeek' => Activity::where('created_at', '>=', now()->startOfWeek())->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Audit Log — Radharani Jewellery']);
    }
}
