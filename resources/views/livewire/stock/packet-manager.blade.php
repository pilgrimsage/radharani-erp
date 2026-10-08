<div>
    <x-ui.page-header title="Packets" subtitle="Labelled groups of pieces. A packet sits inside a box and can move between boxes."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Packets']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="scan" :href="route('stock.assign')">Change item location</x-ui.button>
            <x-ui.button icon="plus" wire:click="create">New packet</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="package" label="Packets" :value="number_format($stats['packets'])" />
        <x-ui.stat-card icon="gem" label="Pieces in packets" :value="number_format($stats['items'])" />
        <x-ui.stat-card icon="unlink" label="Not in a box" :value="number_format($stats['loose'])"
            :hint="$stats['loose'] ? 'click to list them' : 'every packet is boxed'"
            :class="$stats['loose'] ? 'cursor-pointer hover:border-gold-soft' : ''"
            :wire:click="$stats['loose'] ? '$set(\'boxFilter\', \'none\')' : null" />
        <x-ui.stat-card icon="inbox" label="Empty packets" :value="number_format($stats['empty'])" />
    </div>

    @php $pageIds = $packets->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
    <x-ui.datatable :paginator="$packets">
        <x-slot:toolbar>
            <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="Search by code or label" class="w-full sm:w-[280px]" />
            <select wire:model.live="boxFilter" class="rj-select w-auto min-w-[170px]" aria-label="Filter by box">
                <option value="">All boxes</option>
                <option value="none">Not in a box</option>
                @foreach ($boxes as $b)
                    <option value="{{ $b->id }}">{{ $b->code }}{{ $b->label ? ' · ' . $b->label : '' }}</option>
                @endforeach
            </select>
            <div class="rj-segment">
                @foreach (['' => 'All', 'filled' => 'With pieces', 'empty' => 'Empty'] as $value => $name)
                    <button type="button" wire:click="$set('contents', '{{ $value }}')" class="{{ $contents === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @if (count($selected))
            <x-slot:bulk>
                <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 bg-ink text-white animate-fade-in">
                    <span class="text-[13px] font-semibold mr-1"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                    <div class="w-px h-5 bg-white/10"></div>
                    <button type="button" wire:click="openMove" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white hover:bg-white/10">
                        <x-ui.icon name="move" :size="14" /> Move to box
                    </button>
                    <button type="button" wire:click="printSelectedQr" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white hover:bg-white/10">
                        <x-ui.icon name="printer" :size="14" /> Print QR labels
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
            <x-ui.th field="code" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Packet</x-ui.th>
            <x-ui.th field="box" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Box</x-ui.th>
            <x-ui.th field="items" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Pieces</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
            <x-ui.th>QR label</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Created</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($packets as $packet)
            <tr wire:key="packet-{{ $packet->id }}" @class(['is-selected' => in_array((string) $packet->id, $selected, true)])>
                <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $packet->id }}" wire:model.live="selected" aria-label="Select {{ $packet->code }}"></td>
                <td>
                    <a href="{{ route('stock.packets.show', $packet) }}" class="group flex items-center gap-3 min-w-0">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-surface-muted ring-1 ring-inset ring-line-light text-ink_text-secondary group-hover:bg-gold-tint group-hover:text-gold-dark group-hover:ring-gold-soft flex items-center justify-center transition-colors">
                            <x-ui.icon name="package" :size="17" />
                        </span>
                        <span class="min-w-0">
                            <span class="block rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $packet->code }}</span>
                            <span class="block text-[12.5px] text-ink_text-secondary truncate">{{ $packet->label ?: 'No label' }}</span>
                        </span>
                    </a>
                </td>
                <td>
                    @if ($packet->box_id)
                        <a href="{{ route('stock.boxes.show', $packet->box_id) }}" class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-lg bg-surface-sunken ring-1 ring-inset ring-line-light text-ink_text-primary hover:ring-gold-soft hover:text-gold-dark">
                            <x-ui.icon name="archive" :size="13" class="text-ink_text-muted" />
                            <span class="rj-code text-[12px]">{{ $packet->box_code }}</span>
                        </a>
                    @else
                        <x-ui.badge tone="warning" size="sm">Not in a box</x-ui.badge>
                    @endif
                </td>
                <td class="text-right tabular font-semibold">{{ $packet->items_count }}</td>
                <td class="text-right tabular text-ink_text-secondary">{{ $packet->items_sum_weight ? number_format($packet->items_sum_weight, 3) . ' g' : '-' }}</td>
                <td>
                    @if ($packet->qr_codes_exists)
                        <x-ui.badge tone="success" size="sm">Issued</x-ui.badge>
                    @else
                        <x-ui.badge size="sm">Not issued</x-ui.badge>
                    @endif
                </td>
                <td class="text-ink_text-secondary text-[12.5px] whitespace-nowrap">{{ $packet->created_at?->format('d M Y') }}</td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="qr-code" wire:click="printQr({{ $packet->id }})" title="Print QR label" aria-label="Print QR label for {{ $packet->code }}" />
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit({{ $packet->id }})" title="Edit" aria-label="Edit {{ $packet->code }}" />
                        <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('stock.packets.show', $packet)">Open</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No packets match these filters" message="Try a different code, box or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="package" title="No packets yet" message="Create a packet, then assign pieces to it by scanning or picking from a list.">
                            <x-ui.button size="sm" icon="plus" wire:click="create">New packet</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edit packet' : 'New packet'" icon="package" max-width="md" submit="save"
        :subtitle="$editingId ? 'Changing the box moves the packet and records it in its history.' : 'A packet is a labelled group of pieces inside a box.'">
        <div class="space-y-4">
            <x-ui.field label="Box" for="packet-box" error="box_id" hint="Leave empty if the packet isn't in a box yet.">
                <select id="packet-box" wire:model.live="box_id" class="rj-select @error('box_id') is-invalid @enderror">
                    <option value="">No box</option>
                    @foreach ($boxes as $b)
                        <option value="{{ $b->id }}">{{ $b->code }}{{ $b->label ? ' · ' . $b->label : '' }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Packet code" for="packet-code" error="code" hint="Printed on the packet and its QR label. Must be unique.">
                <input id="packet-code" type="text" wire:model="code" autofocus class="rj-input rj-code uppercase @error('code') is-invalid @enderror" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Label" for="packet-label" error="label" optional hint="e.g. 22K bangles, pair sets.">
                <input id="packet-label" type="text" wire:model="label" maxlength="100" class="rj-input @error('label') is-invalid @enderror">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Create packet' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="showMove" title="Move packets" icon="move" max-width="sm" submit="moveSelected"
        subtitle="{{ count($selected) }} packet(s) will be moved. Each move is recorded in the packet's history.">
        <x-ui.field label="Destination box" for="move-box" error="moveToBoxId">
            <select id="move-box" wire:model="moveToBoxId" autofocus class="rj-select">
                <option value="">Take out of any box</option>
                @foreach ($boxes as $b)
                    <option value="{{ $b->id }}">{{ $b->code }}{{ $b->label ? ' · ' . $b->label : '' }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="moveSelected" icon="move">Move packets</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
