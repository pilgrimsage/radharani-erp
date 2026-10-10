<div>
    <x-ui.page-header title="Audit Log" subtitle="Every change, with who made it, when, and the values before and after. Read-only." />

    <div class="grid grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="history" label="Total entries" :value="number_format($stats['total'])" />
        <x-ui.stat-card icon="calendar" label="Today" :value="number_format($stats['today'])" />
        <x-ui.stat-card icon="clock" label="This week" :value="number_format($stats['thisWeek'])" />
    </div>

    <x-ui.datatable :paginator="$activities">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search description" class="w-full sm:w-[280px]" />
            <select wire:model.live="logNameFilter" class="rj-select rj-input-sm">
                <option value="">All types</option>
                <option value="movement">Movements</option>
                <option value="sale">Sales</option>
                <option value="purchase">Purchases</option>
                <option value="stock">Stock</option>
                <option value="order">Orders</option>
            </select>
            <select wire:model.live="userFilter" class="rj-select rj-input-sm" aria-label="Who">
                <option value="">Everyone</option>
                @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
            <input type="date" wire:model.live="from" class="rj-input rj-input-sm w-auto" aria-label="From date">
            <input type="date" wire:model.live="to" class="rj-input rj-input-sm w-auto" aria-label="To date">
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date</x-ui.th>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Type</x-ui.th>
            <x-ui.th>What</x-ui.th>
            <x-ui.th>What changed</x-ui.th>
            <x-ui.th>By</x-ui.th>
        </x-slot:head>

        @forelse ($activities as $a)
            <tr wire:key="activity-{{ $a->id }}">
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $a->created_at->format('d M Y, g:i a') }}</td>
                <td><x-ui.badge tone="neutral" size="sm">{{ ucfirst($a->log_name ?? 'other') }}</x-ui.badge></td>
                @php
                    $new = (array) ($a->properties['attributes'] ?? []);
                    $old = (array) ($a->properties['old'] ?? []);
                @endphp
                <td class="text-ink_text-primary">
                    <span class="font-semibold">{{ \App\Livewire\Admin\AuditLogViewer::subjectLabel($a) ?: '-' }}</span>
                    <span class="block text-[12.5px] text-ink_text-muted">{{ $a->description }}</span>
                </td>
                <td class="text-[12.5px]">
                    @forelse ($new as $field => $value)
                        <span class="block"><span class="text-ink_text-muted">{{ \Illuminate\Support\Str::headline($field) }}:</span>
                            @if (array_key_exists($field, $old))<span class="line-through text-ink_text-muted">{{ is_scalar($old[$field]) || is_null($old[$field]) ? ($old[$field] ?? 'empty') : json_encode($old[$field]) }}</span> <x-ui.icon name="arrow-right" :size="11" class="inline" />@endif
                            <span class="font-semibold">{{ is_scalar($value) || is_null($value) ? ($value ?? 'empty') : json_encode($value) }}</span></span>
                    @empty
                        <span class="text-ink_text-muted">-</span>
                    @endforelse
                </td>
                <td class="text-ink_text-secondary">{{ $a->causer->name ?? 'System' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No entries match these filters" message="Try a different search or type, or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="history" title="No audit entries yet" message="Movements, sales, purchases, stock and order changes will show up here." />
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
