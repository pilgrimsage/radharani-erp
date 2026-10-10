<div>
    <x-ui.page-header title="Pending Review" subtitle="Everything still unfinished. Check each one and confirm it; confirming is what moves a piece into normal, sellable stock."
        :crumbs="[['label' => 'Movements'], ['label' => 'Pending Review']]">
        <x-slot:meta>
            <x-ui.badge tone="dark"><x-ui.icon name="shield-check" :size="12" /> Admin only</x-ui.badge>
        </x-slot:meta>
    </x-ui.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="truck" label="Back from karigar" :value="$counts['karigar']" hint="repairs to check"
            class="cursor-pointer {{ $tab === 'returns' && $source === 'karigar' ? '!border-gold shadow-focus' : '' }}" wire:click="showSource('karigar')" />
        <x-ui.stat-card icon="shield-check" label="Back from hallmarking" :value="$counts['hallmark']" hint="HUIDs to check"
            class="cursor-pointer {{ $tab === 'returns' && $source === 'hallmark' ? '!border-gold shadow-focus' : '' }}" wire:click="showSource('hallmark')" />
        <x-ui.stat-card icon="flame" label="New pieces" :value="$counts['new']" hint="made from raw material"
            class="cursor-pointer {{ $tab === 'returns' && $source === 'new' ? '!border-gold shadow-focus' : '' }}" wire:click="showSource('new')" />
        <x-ui.stat-card icon="tag" label="Purchases to tag" :value="$counts['tags']" hint="raw material lines"
            class="cursor-pointer {{ $tab === 'tags' ? '!border-gold shadow-focus' : '' }}" wire:click="setTab('tags')" />
    </div>

    <div class="rj-segment mb-5">
        <button type="button" wire:click="setTab('returns')" class="{{ $tab === 'returns' ? 'is-active' : '' }}">
            <x-ui.icon name="corner-down-right" :size="14" /> Returned pieces
            <span class="min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-bold tabular inline-flex items-center justify-center {{ $tab === 'returns' ? 'bg-gold-tint text-gold-dark' : 'bg-white text-ink_text-muted' }}">{{ $counts['returns'] }}</span>
        </button>
        <button type="button" wire:click="setTab('tags')" class="{{ $tab === 'tags' ? 'is-active' : '' }}">
            <x-ui.icon name="tag" :size="14" /> Purchases to tag
            <span class="min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-bold tabular inline-flex items-center justify-center {{ $tab === 'tags' ? 'bg-gold-tint text-gold-dark' : 'bg-white text-ink_text-muted' }}">{{ $counts['tags'] }}</span>
        </button>
    </div>

    @if ($tab === 'returns')
        @php $pageIds = $items->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
        <x-ui.datatable :paginator="$items">
            <x-slot:toolbar>
                <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="HUID, code, category, packet or box" class="w-full sm:w-[260px]" />
                <select wire:model.live="source" class="rj-select w-auto min-w-[190px]" aria-label="Came back from">
                    <option value="">Everything</option>
                    <option value="karigar">Back from karigar</option>
                    <option value="hallmark">Back from hallmarking</option>
                    <option value="new">New pieces from raw material</option>
                </select>
                @if ($this->hasActiveFilters())
                    <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
                @endif
            </x-slot:toolbar>

            @if (count($selected))
                <x-slot:bulk>
                    <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 bg-ink text-white animate-fade-in">
                        <span class="text-[13px] font-semibold mr-1"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                        <div class="w-px h-5 bg-white/10"></div>
                        <button type="button"
                            x-on:click="$dispatch('rj-confirm', { title: 'Confirm {{ count($selected) }} into stock?', message: 'They become available for sale straight away. Your name is recorded as the reviewer.', confirm: 'Confirm all', action: () => $wire.confirmSelected() })"
                            class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-semibold text-gold-light hover:text-white hover:bg-white/10">
                            <x-ui.icon name="check" :size="14" /> Confirm into stock
                        </button>
                        <button type="button" wire:click="clearSelection" class="ml-auto text-[12.5px] font-semibold text-ink-dim hover:text-white">Clear selection</button>
                    </div>
                </x-slot:bulk>
            @endif

            <x-slot:head>
                <th class="w-10 !pr-0">
                    <input type="checkbox" class="rj-checkbox" aria-label="Select all on this page"
                        @checked(count($pageIds) && ! array_diff($pageIds, $selected))
                        x-on:change="$wire.set('selected', $event.target.checked ? @js($pageIds) : [])">
                </th>
                <x-ui.th field="code" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Piece</x-ui.th>
                <x-ui.th>Came back from</x-ui.th>
                <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
                <x-ui.th>Tagged by</x-ui.th>
                <x-ui.th field="since" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Waiting</x-ui.th>
                <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
            </x-slot:head>

            @forelse ($rows as $row)
                @php $item = $row['item']; $ret = $row['return']; @endphp
                <tr wire:key="pr-{{ $item->id }}" @class(['is-selected' => in_array((string) $item->id, $selected, true)])>
                    <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $item->id }}" wire:model.live="selected" aria-label="Select {{ $item->label }}"></td>
                    <td>
                        <a href="{{ route('stock.items.show', $item) }}" class="group block min-w-0 max-w-[260px]">
                            <span class="flex items-center gap-1.5">
                                <x-movement.metal-dot :metal="$item->metal" />
                                <span class="rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $item->label }}</span>
                                @if ($item->huid_code)<x-ui.badge tone="gold" size="sm">HUID</x-ui.badge>@endif
                            </span>
                            <span class="block text-[12.5px] text-ink_text-muted truncate">{{ $item->category }} · {{ $item->purity }}</span>
                        </a>
                        @if ($row['kind'] === 'new' && (float) $item->making_value == 0)
                            <x-ui.badge tone="warning" size="sm" class="mt-1">Making charge not set</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        @switch($row['kind'])
                            @case('karigar') <x-ui.badge tone="info" size="sm"><x-ui.icon name="truck" :size="11" /> Karigar</x-ui.badge> @break
                            @case('hallmark') <x-ui.badge tone="gold" size="sm"><x-ui.icon name="shield-check" :size="11" /> Hallmarking</x-ui.badge> @break
                            @case('new') <x-ui.badge tone="dark" size="sm"><x-ui.icon name="flame" :size="11" /> New piece</x-ui.badge> @break
                            @default <x-ui.badge size="sm">No return on record</x-ui.badge>
                        @endswitch
                        @if ($ret && ($ret->counterparty || $ret->purpose_label))
                            <div class="text-[12.5px] text-ink_text-secondary mt-1">{{ implode(' · ', array_filter([$ret->counterparty, $ret->purpose_label])) }}</div>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <div class="tabular font-semibold">{{ number_format((float) ($ret?->weight_at_return ?? $item->weight), 3) }} <span class="text-ink_text-muted font-normal">g</span></div>
                        <div class="text-[12px] text-ink_text-muted tabular">
                            @if ($row['sent'] !== null) sent {{ number_format((float) $row['sent'], 3) }} · @endif
                            @if ($ret?->weight_loss !== null)
                                <span class="{{ (float) $ret->weight_loss > 0 ? 'text-warning font-semibold' : '' }}">loss {{ number_format((float) $ret->weight_loss, 3) }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-[13px]">{{ $ret?->tagged_by ?: '-' }}</td>
                    <td class="whitespace-nowrap text-[13px]">
                        @if ($ret)
                            {{ $ret->created_at->diffForHumans(short: true) }}
                            <div class="text-[12px] text-ink_text-muted">by {{ $ret->user?->name ?? '-' }}</div>
                        @else
                            {{ $item->updated_at?->diffForHumans(short: true) ?? '-' }}
                            <div class="text-[12px] text-ink_text-muted">last changed</div>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <div class="inline-flex items-center gap-1.5">
                            <x-ui.button variant="ghost" size="sm" icon="edit" x-on:click="Livewire.dispatch('open-item-form', { id: {{ $item->id }} })">Review</x-ui.button>
                            <x-ui.button size="sm" icon="check" wire:click="confirmClose({{ $item->id }})">Confirm</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">
                    <x-ui.empty-state icon="check-circle" :title="$this->hasActiveFilters() ? 'Nothing matches' : 'Nothing waiting for review'"
                        :message="$this->hasActiveFilters() ? 'Try clearing the filters.' : 'Returned pieces land here until someone confirms them into stock.'" compact />
                </td></tr>
            @endforelse
        </x-ui.datatable>
    @else
        <x-ui.card :padding="false">
            @if ($tags->isEmpty())
                <x-ui.empty-state icon="tag" title="No purchases waiting to be tagged" message="Raw-material lines from Purchases appear here until each one is tagged into a piece." />
            @else
                <x-ui.table :headers="['Purchase', 'What was bought', 'Metal', ['label' => 'Weight', 'class' => 'text-right'], ['label' => '', 'class' => 'text-right']]">
                    @foreach ($tags as $line)
                        <tr wire:key="tag-{{ $line->id }}">
                            <td>
                                <div class="text-[13px] font-semibold">{{ $line->purchase?->vendor?->name ?? '-' }}</div>
                                <div class="text-[12px] text-ink_text-muted">{{ $line->purchase?->invoice_number ? 'Bill ' . $line->purchase->invoice_number . ' · ' : '' }}{{ $line->purchase?->created_at?->format('j M Y') }}</div>
                            </td>
                            <td>
                                <div class="text-[13px]">{{ $line->description ?: '-' }}</div>
                                <div class="text-[12px] text-ink_text-muted">{{ $line->category ?: 'No category' }}</div>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="inline-flex items-center gap-2"><x-movement.metal-dot :metal="$line->metal" /> {{ ucfirst($line->metal ?? '-') }} <span class="text-ink_text-muted text-[12.5px]">{{ $line->purity }}</span></span>
                            </td>
                            <td class="text-right tabular font-semibold">{{ number_format((float) $line->weight, 3) }} <span class="text-ink_text-muted font-normal">g</span></td>
                            <td class="text-right">
                                <x-ui.button size="sm" icon="tag" x-on:click="Livewire.dispatch('open-item-form', { purchaseItemId: {{ $line->id }} })">Tag it</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    @endif

    <livewire:stock.item-form />
</div>
