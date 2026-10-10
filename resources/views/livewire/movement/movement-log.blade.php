<div>
    <x-ui.page-header title="Movement Log" subtitle="Every recorded move, newest first. Nothing here can be edited; mistakes are fixed with a correction entry."
        :crumbs="[['label' => 'Movements'], ['label' => 'Movement Log']]" />

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="grid" label="On the counter" :value="$now['counter']" hint="pieces, packets and boxes" :href="route('movements.vault-counter')" />
        <x-ui.stat-card icon="truck" label="With karigars" :value="$now['karigar']" hint="repairs, customer jobs, raw batches" :href="route('movements.karigar-return')" />
        <x-ui.stat-card icon="shield-check" label="At hallmarking" :value="$now['hallmark']" hint="waiting to come back" :href="route('movements.hallmark-return')" />
        <x-ui.stat-card icon="camera" label="Out for photos or other" :value="$now['other']" hint="short trips" :href="route('movements.custom-purpose', ['direction' => 'in'])" />
    </div>

    <x-ui.datatable :paginator="$movements">
        <x-slot:toolbar>
            <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="Code, karigar, centre or reason" class="w-full lg:w-[280px]" />
            <select wire:model.live="group" class="rj-select w-auto min-w-[160px]" aria-label="Type">
                <option value="">All types</option>
                @foreach (\App\Livewire\Movement\MovementLog::GROUPS as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
            <select wire:model.live="userId" class="rj-select w-auto min-w-[150px]" aria-label="Staff">
                <option value="">All staff</option>
                @foreach ($staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-1.5">
                <input type="date" wire:model.live="dateFrom" class="rj-input w-[150px] tabular" aria-label="From date">
                <span class="text-ink_text-muted text-[12.5px]">to</span>
                <input type="date" wire:model.live="dateTo" class="rj-input w-[150px] tabular" aria-label="To date">
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear all</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="when" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">When</x-ui.th>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Movement</x-ui.th>
            <x-ui.th>What</x-ui.th>
            <x-ui.th>Where / why</x-ui.th>
            <x-ui.th align="right">Weight</x-ui.th>
            <x-ui.th field="by" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">By</x-ui.th>
        </x-slot:head>

        @forelse ($movements as $m)
            @php $d = $described[$m->id]; @endphp
            <tr wire:key="mv-{{ $m->id }}">
                <td class="whitespace-nowrap">
                    <div class="text-[13px] font-medium tabular">{{ $m->created_at->format('j M Y') }}</div>
                    <div class="text-[12px] text-ink_text-muted tabular">{{ $m->created_at->format('g:i a') }}</div>
                </td>
                <td class="whitespace-nowrap">
                    @php
                        $tone = match (true) {
                            $m->movement_type === 'correction' => 'danger',
                            str_starts_with($m->movement_type, 'vault') => 'gold',
                            str_starts_with($m->movement_type, 'hallmark') => 'info',
                            $m->is_out => 'warning',
                            default => 'success',
                        };
                    @endphp
                    <x-ui.badge :tone="$tone" size="sm">
                        <x-ui.icon :name="$m->movement_type === 'correction' ? 'refresh' : ($m->is_out ? 'arrow-up' : 'arrow-down')" :size="11" /> {{ $m->label }}
                    </x-ui.badge>
                    @if ($m->approver)
                        <div class="text-[11.5px] text-success mt-1 flex items-center gap-1"><x-ui.icon name="check" :size="11" /> Reviewed by {{ $m->approver->name }}</div>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-1.5">
                        <x-ui.icon :name="['item' => 'gem', 'packet' => 'package', 'box' => 'archive'][$m->trackable_type] ?? 'gem'" :size="13" class="text-ink_text-muted" />
                        @if ($d['url'])
                            <a href="{{ $d['url'] }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $d['code'] }}</a>
                        @else
                            <span class="rj-code">{{ $d['code'] }}</span>
                        @endif
                    </div>
                    <div class="text-[12px] text-ink_text-muted truncate max-w-[220px]">{{ $d['detail'] }}</div>
                </td>
                <td class="max-w-[260px]">
                    @php
                        $where = $m->counterparty ?: ($m->purpose_label ?: match ($m->movement_type) {
                            'vault_out' => 'Counter', 'vault_in' => 'Vault', default => '-',
                        });
                        $extra = array_filter([
                            $m->counterparty ? $m->purpose_label : null,
                            $m->tagged_by ? 'tagged by ' . $m->tagged_by : null,
                            $m->note,
                        ]);
                    @endphp
                    <div class="text-[13px] truncate">{{ $where }}</div>
                    @if ($extra)
                        <div class="text-[12px] text-ink_text-muted truncate">{{ implode(' · ', $extra) }}</div>
                    @endif
                    @if ($m->is_out && $m->expected_return)
                        <div class="text-[12px] text-ink_text-secondary mt-0.5">due {{ $m->expected_return->format('j M') }}</div>
                    @endif
                </td>
                <td class="text-right whitespace-nowrap tabular text-[13px]">
                    @if ($m->weight_at_return !== null)
                        {{ number_format((float) $m->weight_at_return, 3) }} g
                        @if ($m->weight_loss !== null)<div class="text-[12px] {{ (float) $m->weight_loss > 0 ? 'text-warning font-semibold' : 'text-ink_text-muted' }}">loss {{ number_format((float) $m->weight_loss, 3) }}</div>@endif
                    @elseif ($m->weight_at_dispatch !== null)
                        {{ number_format((float) $m->weight_at_dispatch, 3) }} g
                    @else
                        <span class="text-ink_text-muted">-</span>
                    @endif
                </td>
                <td class="whitespace-nowrap text-[13px]">
                    {{ $m->user?->name ?? '-' }}@if ($m->doneBy) <span class="block text-[11.5px] text-ink_text-muted">done by {{ $m->doneBy->name }}</span>@endif
                    @if ($m->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($m->photo_path))
                        <a href="{{ asset('storage/' . $m->photo_path) }}" target="_blank" class="ml-1 inline-flex align-middle text-ink_text-muted hover:text-gold-dark" title="Photo"><x-ui.icon name="image" :size="14" /></a>
                    @elseif ($m->photo_path)
                        <span class="ml-1 inline-flex align-middle text-ink_text-muted" title="Photo expired and was removed after 90 days"><x-ui.icon name="image" :size="14" /></span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6">
                <x-ui.empty-state icon="history" :title="$this->hasActiveFilters() ? 'Nothing matches' : 'No movements yet'"
                    :message="$this->hasActiveFilters() ? 'Try clearing the filters.' : 'Every dispatch and return is recorded here.'" compact />
            </td></tr>
        @endforelse
    </x-ui.datatable>
</div>
