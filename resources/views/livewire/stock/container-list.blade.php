<div>
    <x-ui.page-header title="Boxes & Packets" subtitle="Every box and packet in one list. Boxes hold packets, packets hold pieces."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Boxes & Packets']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="layers" :href="route('stock.configurator')">Configurator</x-ui.button>
            <x-ui.button variant="secondary" icon="plus" wire:click="create('packet')">New packet</x-ui.button>
            <x-ui.button icon="plus" wire:click="create('box')">New box</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="archive" label="Boxes" :value="number_format($stats['boxes'])" />
        <x-ui.stat-card icon="package" label="Packets" :value="number_format($stats['packets'])" />
        <x-ui.stat-card icon="unlink" label="Packets without a box" :value="number_format($stats['loosePackets'])"
            :hint="$stats['loosePackets'] ? 'assign them in the configurator' : 'all packets are boxed'" :href="$stats['loosePackets'] ? route('stock.configurator') : null" />
        <x-ui.stat-card icon="trash" label="Empty" :value="number_format($stats['empty'])" hint="can be deleted" />
    </div>

    <x-ui.datatable :paginator="$rows">
        <x-slot:toolbar>
            <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="Search by code or label" class="w-full sm:w-[300px]" />
            <div class="rj-segment">
                @foreach (['' => 'All', 'box' => 'Boxes', 'packet' => 'Packets'] as $value => $name)
                    <button type="button" wire:click="$set('type', '{{ $value }}')" class="{{ $type === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            <div class="rj-segment">
                @foreach (['' => 'Any contents', 'filled' => 'Not empty', 'empty' => 'Empty'] as $value => $name)
                    <button type="button" wire:click="$set('contents', '{{ $value }}')" class="{{ $contents === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="code" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Code</x-ui.th>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Type</x-ui.th>
            <x-ui.th field="parent" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">In box</x-ui.th>
            <x-ui.th field="items" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Pieces</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Created</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($rows as $row)
            @php
                $isBox = $row->kind === 'box';
                $href = route($isBox ? 'stock.boxes.show' : 'stock.packets.show', $row->id);
                $isEmpty = (int) $row->items_count === 0 && (int) $row->child_count === 0;
            @endphp
            <tr wire:key="{{ $row->kind }}-{{ $row->id }}">
                <td>
                    <a href="{{ $href }}" class="group flex items-center gap-3 min-w-0">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-surface-muted ring-1 ring-inset ring-line-light text-ink_text-secondary group-hover:bg-gold-tint group-hover:text-gold-dark group-hover:ring-gold-soft flex items-center justify-center transition-colors">
                            <x-ui.icon :name="$isBox ? 'archive' : 'package'" :size="17" />
                        </span>
                        <span class="min-w-0">
                            <span class="block rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $row->code }}</span>
                            <span class="block text-[12.5px] text-ink_text-secondary truncate">{{ $row->label ?: 'No label' }}</span>
                        </span>
                    </a>
                </td>
                <td><x-ui.badge :tone="$isBox ? 'dark' : 'gold'" size="sm">{{ $isBox ? 'Box' : 'Packet' }}</x-ui.badge></td>
                <td class="rj-code text-[12.5px] text-ink_text-secondary">
                    @if ($isBox) <span class="text-ink_text-muted">{{ $row->child_count }} {{ \Illuminate\Support\Str::plural('packet', $row->child_count) }}</span>
                    @elseif ($row->parent_code) <a href="{{ route('stock.boxes.show', $row->parent_id) }}" class="hover:text-gold-dark">{{ $row->parent_code }}</a>
                    @else <span class="text-ink_text-muted">No box</span> @endif
                </td>
                <td class="text-right tabular">{{ $row->items_count }}</td>
                <td class="text-right tabular text-ink_text-secondary">{{ (float) $row->weight ? number_format($row->weight, 3) . ' g' : '-' }}</td>
                <td class="text-ink_text-secondary text-[12.5px] whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y') }}</td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" wire:click="edit('{{ $row->kind }}', {{ $row->id }})" title="Edit" aria-label="Edit {{ $row->code }}" />
                        @if ($isEmpty)
                            <x-ui.button variant="ghost" size="icon-sm" icon="trash" title="Delete (empty)" aria-label="Delete {{ $row->code }}"
                                x-on:click="$dispatch('rj-confirm', { title: 'Delete {{ $row->code }}?', message: 'It is empty. It will be hidden from lists but kept in the audit trail.', confirm: 'Delete', tone: 'danger', action: () => $wire.deleteContainer('{{ $row->kind }}', {{ $row->id }}) })" />
                        @endif
                        <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="$href">Open</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty-state icon="archive" :title="$this->hasActiveFilters() ? 'Nothing matches these filters' : 'No boxes or packets yet'"
                        :message="$this->hasActiveFilters() ? 'Try a different code or clear the filters.' : 'Create the first box, then add packets to it.'" />
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showForm" :title="($editingId ? 'Edit ' : 'New ') . $formType" :icon="$formType === 'box' ? 'archive' : 'package'" max-width="md" submit="save"
        :subtitle="$formType === 'box' ? 'Give the box a code that matches its physical label.' : 'A packet is a labelled group of pieces inside a box.'">
        <div class="space-y-4">
            @if ($formType === 'packet')
                <x-ui.field label="Box" for="c-box" error="box_id" hint="Leave empty if the packet isn't in a box yet.">
                    <select id="c-box" wire:model.live="box_id" class="rj-select @error('box_id') is-invalid @enderror">
                        <option value="">No box</option>
                        @foreach ($boxes as $b)
                            <option value="{{ $b->id }}">{{ $b->code }}{{ $b->label ? ' · ' . $b->label : '' }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            @endif
            <x-ui.field :label="ucfirst($formType) . ' code'" for="c-code" error="code" hint="Printed on the label and its QR sticker. Must be unique.">
                <input id="c-code" type="text" wire:model="code" class="rj-input rj-code uppercase @error('code') is-invalid @enderror" autocomplete="off" autofocus>
            </x-ui.field>
            <x-ui.field label="Label" for="c-label" error="label" optional hint="Where it lives or what it holds.">
                <input id="c-label" type="text" wire:model="label" maxlength="100" class="rj-input @error('label') is-invalid @enderror">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : 'Create ' . $formType }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
