<?php
namespace App\Livewire\Notifications;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Notification\PendingNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pending Messages Queue — real data.
 *
 * WhatsApp auto-send is not being integrated this phase: every
 * customer-facing notification (sale confirmation, order ready, scheme welcome
 * award, instalment reminder, exchange valuation ready) is generated here
 * as a copyable message. Staff copies it and sends it manually (WhatsApp,
 * SMS, whatever), then marks it "sent" in this log.
 */
class PendingMessagesQueue extends Component
{
    use WithDataTable;

    #[Url(except: 'pending')]
    public string $statusFilter = 'pending';

    #[Url(as: 'group', except: '')]
    public string $groupFilter = ''; // '' | Sales | Installment | Order | Other

    protected function sortableColumns(): array
    {
        return [
            'type' => 'type',
            'created' => 'created_at',
        ];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['statusFilter', 'groupFilter'];
    }

    // statusFilter defaults to 'pending', not '', so the trait's own
    // hasActiveFilters() would always read as "active" — this is the
    // real "any filter differs from the page's own default" check used
    // by the view instead.
    public function hasNonDefaultFilters(): bool
    {
        return $this->search !== '' || $this->statusFilter !== 'pending' || $this->groupFilter !== '';
    }

    public function markSent(int $id)
    {
        PendingNotification::findOrFail($id)->markSent(Auth::user());
        $this->dispatch('toast', message: 'Marked as sent.', type: 'success');
    }

    public function render()
    {
        $query = PendingNotification::with('customer')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(isset(\App\Support\MessageTemplates::GROUPS[$this->groupFilter]), fn ($q) => $q->whereIn('type', array_keys(\App\Support\MessageTemplates::GROUPS[$this->groupFilter])))
            ->when($this->search, fn ($q) => $q->where('message', 'like', "%{$this->search}%")
                ->orWhere('recipient_name', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")));

        return view('livewire.notifications.pending-messages-queue', [
            'messages' => $this->applySorting($query)->paginate($this->perPageValue()),
            'stats' => [
                'pending' => PendingNotification::where('status', 'pending')->count(),
                'sent' => PendingNotification::where('status', 'sent')->count(),
                'sentToday' => PendingNotification::where('status', 'sent')->whereDate('sent_at', today())->count(),
            ],
        ])->layout('components.layouts.app', ['title' => 'Pending Messages Queue — Radharani Jewellery ERP']);
    }
}
