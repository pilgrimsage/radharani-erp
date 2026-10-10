<div>
    <x-ui.page-header title="Inventory" subtitle="Every piece in the system. Search by code, or narrow down by category, box, metal and status."
        :crumbs="[['label' => 'Stock'], ['label' => 'Inventory']]">
        <x-slot:actions>
            <x-ui.dropdown width="w-56">
                <x-slot:trigger>
                    <x-ui.button variant="secondary" iconRight="chevron-down">Tools</x-ui.button>
                </x-slot:trigger>
                <x-ui.dropdown-item icon="scan" :href="route('stock.assign')">Change item location</x-ui.dropdown-item>
                <x-ui.dropdown-item icon="qr-code" :href="route('stock.qr-codes')">QR code generator</x-ui.dropdown-item>
                <x-ui.dropdown-item icon="upload" :href="route('stock.import')">Bulk import</x-ui.dropdown-item>
                <x-ui.dropdown-item icon="layers" :href="route('stock.configurator')">Configurator</x-ui.dropdown-item>
            </x-ui.dropdown>
            <x-ui.button icon="plus" x-on:click="Livewire.dispatch('open-item-form')">Add piece</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="gem" label="In stock" :value="number_format($stats['inStock'])" :hint="number_format($stats['inStockWeight'], 3) . ' g on hand'"
            class="cursor-pointer" wire:click="$set('statusFilter', 'in_stock')" />
        <x-ui.stat-card icon="truck" label="Out with karigar / hallmarking" :value="number_format($stats['dispatched'])" hint="dispatched"
            class="cursor-pointer" wire:click="$set('statusFilter', 'dispatched')" />
        <x-ui.stat-card icon="clock" label="Awaiting review" :value="number_format($stats['pending'])" hint="open the review queue"
            :href="\Illuminate\Support\Facades\Route::has('movements.pending-review') ? route('movements.pending-review') : null" />
        <x-ui.stat-card icon="receipt" label="Reserved in unverified sales" :value="number_format($stats['reserved'])" hint="awaiting sale verification"
            class="cursor-pointer" wire:click="$set('statusFilter', 'reserved')" />
    </div>

    @if ($pendingTags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-4 mb-6 px-5 py-4 rounded-card bg-gold-tint border border-gold-soft/80 animate-rise-in">
            <div class="w-10 h-10 shrink-0 rounded-xl bg-white ring-1 ring-gold-soft text-gold-dark flex items-center justify-center">
                <x-ui.icon name="tag" :size="18" />
            </div>
            <div class="flex-1 min-w-[220px]">
                <div class="text-[14px] font-bold text-ink_text-primary">{{ $pendingTags->count() }} purchased {{ \Illuminate\Support\Str::plural('line', $pendingTags->count()) }} waiting to be tagged</div>
                <div class="text-[12.5px] text-ink_text-secondary">Raw material from purchases. Tagging turns each line into a real piece and links it back to the purchase.</div>
            </div>
            <x-ui.button variant="dark" icon="tag" wire:click="$set('showPendingTags', true)">Tag them</x-ui.button>
        </div>
    @endif

    @php $pageIds = $items->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
    <x-ui.datatable :paginator="$items">
        <x-slot:toolbar>
            <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="HUID, code, category, packet or box" class="w-full lg:w-[300px]" />
            <select wire:model.live="categoryFilter" class="rj-select w-auto min-w-[150px]" aria-label="Category">
                <option value="">All categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
            <select wire:model.live="boxFilter" class="rj-select w-auto min-w-[140px]" aria-label="Box">
                <option value="">All boxes</option>
                <option value="none">No packet yet</option>
                @foreach ($boxes as $b)
                    <option value="{{ $b->id }}">{{ $b->code }}</option>
                @endforeach
            </select>
            <select wire:model.live="metalFilter" class="rj-select w-auto min-w-[130px]" aria-label="Metal">
                <option value="">All metals</option>
                @foreach (\App\Livewire\Stock\ItemForm::METALS as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="rj-select w-auto min-w-[150px]" aria-label="Status">
                <option value="">Any status</option>
                @foreach (\App\Livewire\Stock\ItemForm::STATUSES as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear all</x-ui.button>
            @endif
        </x-slot:toolbar>

        @if (count($selected))
            <x-slot:bulk>
                <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 bg-ink text-white animate-fade-in">
                    <span class="text-[13px] font-semibold mr-1"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                    <div class="w-px h-5 bg-white/10"></div>
                    <button type="button" wire:click="openAssign" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white hover:bg-white/10">
                        <x-ui.icon name="package" :size="14" /> Move to packet
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
            <x-ui.th field="category" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Category</x-ui.th>
            <x-ui.th field="metal" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Metal</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
            <x-ui.th class="hidden 2xl:table-cell">Making</x-ui.th>
            <x-ui.th field="location" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Location</x-ui.th>
            <x-ui.th field="status" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($items as $item)
            <tr wire:key="item-{{ $item->id }}" @class(['is-selected' => in_array((string) $item->id, $selected, true)])>
                <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $item->id }}" wire:model.live="selected" aria-label="Select {{ $item->label }}"></td>
                <td>
                    <a href="{{ route('stock.items.show', $item) }}" class="group block min-w-0 max-w-[260px]">
                        <span class="flex items-center gap-1.5">
                            <span class="rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $item->label }}</span>
                            @if ($item->huid_code)
                                <x-ui.badge tone="gold" size="sm">HUID</x-ui.badge>
                            @endif
                            @if ($item->pair_group_id)
                                <x-ui.badge size="sm" title="Part of a pair"><x-ui.icon name="link" :size="10" /> Pair</x-ui.badge>
                            @endif
                        </span>
                        <span class="block text-[12.5px] text-ink_text-muted truncate">{{ $item->description ?: 'No description' }}</span>
                    </a>
                </td>
                <td class="font-medium">{{ $item->category }}</td>
                <td class="whitespace-nowrap">
                    <span class="inline-flex items-center gap-2">
                        <span @class([
                            'w-2.5 h-2.5 rounded-full ring-2 ring-white shadow',
                            'bg-gradient-to-br from-gold-light to-gold' => $item->metal === 'gold',
                            'bg-gradient-to-br from-[#E4E4E4] to-[#A9A9A9]' => $item->metal === 'silver',
                            'bg-gradient-to-br from-[#E9E6E1] to-[#B8B3AA]' => $item->metal === 'platinum',
                            'bg-gradient-to-br from-[#9EA4AA] to-[#5F666D]' => $item->metal === 'titanium',
                            'bg-line' => ! $item->metal,
                        ])></span>
                        {{ $item->metal ? ucfirst($item->metal) : '-' }}
                        <span class="text-ink_text-muted text-[12.5px]">{{ $item->purity }}</span>
                    </span>
                </td>
                <td class="text-right tabular font-semibold whitespace-nowrap">{{ number_format($item->weight, 3) }} <span class="text-ink_text-muted font-normal">g</span></td>
                <td class="hidden 2xl:table-cell text-[12.5px] text-ink_text-secondary whitespace-nowrap tabular">
                    @switch($item->making_type)
                        @case('percentage') {{ rtrim(rtrim(number_format($item->making_value, 2), '0'), '.') }}% of metal @break
                        @case('flat_per_gram') ₹{{ number_format($item->making_value, 0) }} / g @break
                        @default ₹{{ number_format($item->making_value, 0) }} / piece
                    @endswitch
                </td>
                <td class="whitespace-nowrap">
                    @if ($item->packet_code)
                        <span class="inline-flex items-center gap-1 text-[12.5px]">
                            @if ($item->box_code)
                                <a href="{{ route('stock.boxes.show', $item->box_id) }}" class="rj-code text-[12px] text-ink_text-secondary hover:text-gold-dark">{{ $item->box_code }}</a>
                                <x-ui.icon name="chevron-right" :size="11" class="text-ink_text-muted" />
                            @endif
                            <a href="{{ route('stock.packets.show', $item->packet_id) }}" class="rj-code text-[12px] text-ink_text-primary hover:text-gold-dark">{{ $item->packet_code }}</a>
                        </span>
                    @else
                        <span class="text-[12.5px] text-ink_text-muted">Not packed</span>
                    @endif
                </td>
                <td><x-ui.status :status="$item->status" size="sm" /></td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" x-on:click="Livewire.dispatch('open-item-form', { id: {{ $item->id }} })" title="Edit" aria-label="Edit {{ $item->label }}" />
                        <x-ui.button variant="secondary" size="icon-sm" icon="arrow-right" :href="route('stock.items.show', $item)" title="Open" aria-label="Open {{ $item->label }}" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No pieces match" message="Nothing fits these filters. Loosen one of them or clear them all.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="gem" title="No pieces entered yet" message="Add pieces one at a time, or import a spreadsheet of many.">
                            <x-ui.button size="sm" icon="plus" x-on:click="Livewire.dispatch('open-item-form')">Add piece</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" icon="upload" :href="route('stock.import')">Bulk import</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <livewire:stock.item-form />

    {{-- ============================================================ Pending tags --}}
    <x-ui.modal wire:model="showPendingTags" title="Purchased material to tag" icon="tag" max-width="xl"
        subtitle="Pick a line to open the piece form with its details already filled in.">
        <div class="border border-line-light rounded-xl overflow-hidden">
            <x-ui.table :headers="['Purchase', 'Description', 'Metal', ['label' => 'Weight', 'class' => 'text-right'], ['label' => '', 'class' => 'w-px']]">
                @forelse ($pendingTags as $line)
                    <tr wire:key="pt-{{ $line->id }}">
                        <td>
                            <div class="font-semibold">#{{ $line->purchase_id }}</div>
                            <div class="text-[12px] text-ink_text-muted">{{ $line->purchase?->vendor?->name ?? 'Unknown vendor' }}</div>
                        </td>
                        <td>
                            <div>{{ $line->description ?: 'No description' }}</div>
                            <div class="text-[12px] text-ink_text-muted">{{ $line->category ?: 'Uncategorised' }}</div>
                        </td>
                        <td class="whitespace-nowrap">{{ $line->metal ? ucfirst($line->metal) : '-' }} <span class="text-ink_text-muted">{{ $line->purity }}</span></td>
                        <td class="text-right tabular">{{ $line->weight ? number_format($line->weight, 3) . ' g' : '-' }}</td>
                        <td><x-ui.button size="sm" icon="tag" x-on:click="show = false; Livewire.dispatch('open-item-form', { purchaseItemId: {{ $line->id }} })">Tag</x-ui.button></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="check-circle" title="All purchased material is tagged" compact /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </x-ui.modal>

    {{-- ============================================================ Bulk: move to packet --}}
    <x-ui.modal wire:model="showAssign" title="Move to packet" icon="package" max-width="sm" submit="assignSelected"
        subtitle="{{ count($selected) }} piece(s) will be moved. Each move is recorded in the piece's history.">
        <x-ui.field label="Destination packet" for="bulk-packet" error="assignPacketId">
            <select id="bulk-packet" wire:model="assignPacketId" autofocus class="rj-select">
                <option value="">Take out of any packet</option>
                @foreach ($packetsByBox as $boxCode => $packets)
                    <optgroup label="{{ $boxCode }}">
                        @foreach ($packets as $p)
                            <option value="{{ $p->id }}">{{ $p->code }}{{ $p->label ? ' · ' . $p->label : '' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="assignSelected" icon="check">Move pieces</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
