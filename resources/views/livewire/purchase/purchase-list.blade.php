<div>
    <x-ui.page-header title="Purchases" subtitle="Raw material that came in, newest first. Each entry is identified by its date and time."
        :crumbs="[['label' => 'Purchases']]">
        <x-slot:actions><x-ui.button icon="plus" :href="route('purchases.new')">New purchase</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 gap-4 mb-6 max-w-[520px]">
        <x-ui.stat-card icon="cart" label="Purchases" :value="number_format($stats['total'])" />
        <x-ui.stat-card icon="scale" label="Weight in" :value="number_format($stats['weight'], 3) . ' g'" />
    </div>

    <x-ui.datatable :paginator="$purchases">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Bill reference or notes" class="w-full sm:w-[280px]" />
            @if ($this->hasActiveFilters())<x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>@endif
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Entered</x-ui.th>
            <x-ui.th>What came in</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
            <x-ui.th>Bill ref</x-ui.th>
            <x-ui.th>Order</x-ui.th>
            <x-ui.th>Notes</x-ui.th>
        </x-slot:head>
        @forelse ($purchases as $p)
            <tr wire:key="pu-{{ $p->id }}">
                <td class="whitespace-nowrap">{{ $p->label }}<span class="block text-[12px] text-ink_text-muted">{{ $p->creator?->name }}</span></td>
                <td class="text-[13px]">
                    @forelse ($p->lines as $l)<span class="block">{{ ucfirst($l->metal ?? '') }} {{ $l->purity }} · {{ number_format($l->weight, 3) }} g @if ($l->description)<span class="text-ink_text-muted">· {{ $l->description }}</span>@endif</span>
                    @empty <span class="text-ink_text-muted">-</span> @endforelse
                </td>
                <td class="text-right tabular">{{ $p->total_weight ? number_format($p->total_weight, 3) . ' g' : '-' }}</td>
                <td class="rj-code">{{ $p->invoice_number ?: '-' }}</td>
                <td>@if ($p->order)<a href="{{ route('orders.show', $p->order) }}" class="text-gold-dark hover:underline">#{{ $p->order->id }} {{ $p->order->customer?->name }}</a>@else - @endif</td>
                <td class="text-ink_text-secondary max-w-[260px] truncate">{{ $p->notes ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><x-ui.empty-state icon="cart" title="No purchases yet" message="Record the first raw-material purchase."><x-ui.button size="sm" icon="plus" :href="route('purchases.new')">New purchase</x-ui.button></x-ui.empty-state></td></tr>
        @endforelse
    </x-ui.datatable>
</div>
