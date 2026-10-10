<div>
    <x-ui.page-header title="Unassigned items" subtitle="Pieces that are not in a packet yet, grouped by when they were entered."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Unassigned items']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="package" :href="route('stock.assign')">Change item location</x-ui.button>
            <x-ui.button icon="upload" :href="route('stock.import')">Bulk import</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="HUID, code, category or description" class="w-full sm:w-[320px]" />
        <span class="text-[13px] text-ink_text-secondary">{{ $total }} {{ \Illuminate\Support\Str::plural('piece', $total) }} in {{ $batches->count() }} {{ \Illuminate\Support\Str::plural('batch', $batches->count()) }}</span>
        @if (count($selected))
            <div class="ml-auto flex items-center gap-2 px-3 py-1.5 rounded-control bg-ink text-white">
                <span class="text-[13px] font-semibold"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                <x-ui.button size="sm" variant="secondary" icon="package" wire:click="openMove">Move to packet</x-ui.button>
            </div>
        @endif
    </div>

    @forelse ($batches as $b)
        @php $allOn = ! array_diff($b['ids'], $selected); @endphp
        <x-ui.card :padding="false" class="mb-5" wire:key="batch-{{ $b['key'] }}">
            <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 border-b border-line-light">
                <input type="checkbox" class="rj-checkbox" aria-label="Select this batch" @checked($allOn)
                    x-on:change="$wire.toggleBatch('{{ $b['key'] }}', $event.target.checked)">
                <div>
                    <div class="font-display text-[20px] leading-tight font-semibold text-ink_text-primary">{{ $b['label'] }}</div>
                    <div class="text-[12.5px] text-ink_text-muted">{{ $b['kind'] }} · {{ count($b['rows']) }} {{ \Illuminate\Support\Str::plural('piece', count($b['rows'])) }} · {{ number_format($b['weight'], 3) }} g</div>
                </div>
            </div>
            <x-ui.table :headers="['', 'Piece', 'Category', 'Metal', 'Weight', 'Status', '']">
                @foreach ($b['rows'] as $item)
                    <tr wire:key="un-{{ $item->id }}" @class(['is-selected' => in_array((string) $item->id, $selected, true)])>
                        <td class="!pr-0 w-10"><input type="checkbox" class="rj-checkbox" value="{{ $item->id }}" wire:model.live="selected" aria-label="Select {{ $item->label }}"></td>
                        <td><a href="{{ route('stock.items.show', $item) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $item->label }}</a></td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->metal ? ucfirst($item->metal) : '-' }} <span class="text-ink_text-muted">{{ $item->purity }}</span></td>
                        <td class="tabular">{{ number_format($item->weight, 3) }} g</td>
                        <td><x-ui.status :status="$item->status" /></td>
                        <td class="text-right">
                            <x-ui.button variant="ghost" size="icon-sm" icon="edit" x-on:click="Livewire.dispatch('open-item-form', { id: {{ $item->id }} })" aria-label="Edit {{ $item->label }}" />
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @empty
        <x-ui.card>
            <x-ui.empty-state icon="package" title="Nothing unassigned" message="Every piece is in a packet. Pieces imported or added without a packet will show up here." />
        </x-ui.card>
    @endforelse

    <x-ui.modal wire:model="showMove" title="Move to packet" icon="package" max-width="sm" submit="moveSelected"
        subtitle="{{ count($selected) }} piece(s). Each move is recorded in the piece's history.">
        <x-ui.field label="Packet" for="un-packet" error="moveToPacketId">
            <select id="un-packet" wire:model="moveToPacketId" autofocus class="rj-select">
                <option value="">Choose a packet...</option>
                @foreach ($packets as $box => $list)
                    <optgroup label="{{ $box }}">
                        @foreach ($list as $p)<option value="{{ $p->id }}">{{ $p->code }}{{ $p->label ? ' · ' . $p->label : '' }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="moveSelected" icon="package">Move</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <livewire:stock.item-form />
</div>
