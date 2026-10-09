<div x-data="{
        drag: null,
        start(e, kind, ids) { this.drag = { kind, ids }; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', kind); },
        end() { this.drag = null },
        dropOn(kind, target) {
            if (!this.drag || this.drag.kind !== kind) return;
            const ids = this.drag.ids; this.drag = null;
            kind === 'item' ? $wire.moveItems(ids, target) : $wire.movePackets(ids, target);
        },
    }" x-on:dragend.window="end()">
    <x-ui.page-header title="Configurator" subtitle="Reorganise the stock room in one place. Drag pieces onto packets and packets onto boxes, or tick them and use Move to."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Configurator']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="archive" wire:click="openCreate('box')">New box</x-ui.button>
            <x-ui.button icon="package" wire:click="openCreate('packet')">New packet</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @php $col = 'bg-white border border-line-light rounded-card shadow-card flex flex-col min-h-[420px] lg:h-[calc(100dvh-250px)] overflow-hidden'; @endphp

    <div class="grid grid-cols-1 lg:grid-cols-[300px_minmax(0,1fr)_minmax(0,1.25fr)] gap-4">

        {{-- ============================================================ BOXES --}}
        <section class="{{ $col }}">
            <header class="px-4 pt-4 pb-3 border-b border-line-light">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="flex items-center gap-2 text-[14px] font-bold"><x-ui.icon name="archive" :size="16" class="text-gold-dark" /> Boxes</h2>
                    <span class="text-[12px] text-ink_text-muted tabular">{{ $boxes->count() }}</span>
                </div>
                <x-ui.search-input scan wire:model.live.debounce.250ms="boxSearch" placeholder="Find a box" class="[&_.rj-input]:h-9" />
            </header>
            <div class="flex-1 overflow-y-auto p-2 space-y-1">
                @foreach ($boxes as $b)
                    @php $active = $boxId === (string) $b->id; @endphp
                    <div wire:key="cb-{{ $b->id }}" x-data="{ over: false }"
                         x-on:dragover.prevent="if (drag && drag.kind === 'packet') over = true" x-on:dragleave="over = false"
                         x-on:drop.prevent="over = false; dropOn('packet', {{ $b->id }})"
                         :class="over ? 'ring-2 ring-gold bg-gold-tint' : (drag && drag.kind === 'packet' ? 'ring-1 ring-gold-soft' : '')"
                         class="group relative rounded-xl transition-all {{ $active ? 'bg-ink text-white shadow-raised' : 'hover:bg-surface-sunken' }}">
                        <button type="button" wire:click="selectBox('{{ $b->id }}')" class="w-full flex items-center gap-3 px-3 py-2.5 text-left">
                            <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center {{ $active ? 'gold-sheen text-ink' : 'bg-surface-muted text-ink_text-secondary' }}">
                                <x-ui.icon name="archive" :size="16" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block rj-code {{ $active ? 'text-gold-light' : 'text-ink_text-primary' }}">{{ $b->code }}</span>
                                <span class="block text-[12px] truncate {{ $active ? 'text-ink-fg' : 'text-ink_text-muted' }}">{{ $b->packets_count }} pkt · {{ $b->items_count }} pcs{{ $b->label ? ' · ' . $b->label : '' }}</span>
                            </span>
                        </button>
                        <button type="button" wire:click="openRename('box', {{ $b->id }})" title="Rename" aria-label="Rename {{ $b->code }}"
                            class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md opacity-0 group-hover:opacity-100 focus:opacity-100 flex items-center justify-center {{ $active ? 'text-ink-fg hover:bg-white/10' : 'text-ink_text-muted hover:bg-white' }}">
                            <x-ui.icon name="edit" :size="13" />
                        </button>
                    </div>
                @endforeach

                {{-- Bucket: packets without a box --}}
                <div x-data="{ over: false }" x-on:dragover.prevent="if (drag && drag.kind === 'packet') over = true" x-on:dragleave="over = false"
                     x-on:drop.prevent="over = false; dropOn('packet', null)"
                     :class="over ? 'ring-2 ring-gold bg-gold-tint' : ''"
                     class="rounded-xl mt-2 border border-dashed {{ $boxId === 'none' ? 'border-ink bg-surface-sunken' : 'border-line' }}">
                    <button type="button" wire:click="selectBox('none')" class="w-full flex items-center gap-3 px-3 py-2.5 text-left">
                        <span class="w-9 h-9 shrink-0 rounded-lg bg-warning-bg text-warning flex items-center justify-center"><x-ui.icon name="unlink" :size="16" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold">Not in a box</span>
                            <span class="block text-[12px] text-ink_text-muted">{{ $unboxedCount }} {{ \Illuminate\Support\Str::plural('packet', $unboxedCount) }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </section>

        {{-- ============================================================ PACKETS --}}
        <section class="{{ $col }}">
            <header class="px-4 pt-4 pb-3 border-b border-line-light">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="flex items-center gap-2 text-[14px] font-bold min-w-0">
                        <x-ui.icon name="package" :size="16" class="text-gold-dark shrink-0" />
                        <span class="truncate">Packets {{ $currentBox ? 'in ' . $currentBox->code : ($boxId === 'none' ? 'without a box' : '') }}</span>
                    </h2>
                    @if ($currentBox)
                        <a href="{{ route('stock.boxes.show', $currentBox) }}" class="text-[12px] font-semibold shrink-0">Open box</a>
                    @endif
                </div>
                <x-ui.search-input scan wire:model.live.debounce.250ms="packetSearch" placeholder="Find a packet" class="[&_.rj-input]:h-9" />
            </header>

            @if (count($selectedPackets))
                <div class="flex flex-wrap items-center gap-2 px-4 py-2 bg-ink text-white text-[12.5px] animate-fade-in">
                    <span class="font-semibold"><span class="text-gold-light tabular">{{ count($selectedPackets) }}</span> selected</span>
                    <select class="ml-auto h-8 rounded-lg bg-white/10 border-white/10 text-white text-[12.5px] pl-2.5 pr-8 focus:ring-gold/30 focus:border-gold-light"
                        x-on:change="if ($event.target.value !== '') { $wire.movePackets($wire.selectedPackets, $event.target.value === 'none' ? null : $event.target.value); $event.target.value = '' }">
                        <option value="" class="text-ink">Move to box...</option>
                        <option value="none" class="text-ink">No box</option>
                        @foreach ($allBoxes as $ob)<option value="{{ $ob->id }}" class="text-ink">{{ $ob->code }}</option>@endforeach
                    </select>
                    <button type="button" wire:click="$set('selectedPackets', [])" class="text-ink-dim hover:text-white" aria-label="Clear selection"><x-ui.icon name="x" :size="15" /></button>
                </div>
            @endif

            <div class="flex-1 overflow-y-auto p-2 space-y-1">
                @forelse ($packets as $p)
                    @php $active = $packetId === (string) $p->id; @endphp
                    <div wire:key="cp-{{ $p->id }}" x-data="{ over: false }" draggable="true"
                         x-on:dragstart="start($event, 'packet', $wire.selectedPackets.includes('{{ $p->id }}') ? [...$wire.selectedPackets] : ['{{ $p->id }}'])"
                         x-on:dragover.prevent="if (drag && drag.kind === 'item') over = true" x-on:dragleave="over = false"
                         x-on:drop.prevent="over = false; dropOn('item', {{ $p->id }})"
                         :class="over ? 'ring-2 ring-gold bg-gold-tint' : (drag && drag.kind === 'item' ? 'ring-1 ring-gold-soft' : '')"
                         class="group flex items-center gap-2 pl-1.5 pr-2 rounded-xl cursor-grab active:cursor-grabbing transition-all
                            {{ $active ? 'bg-gold-tint ring-1 ring-gold-soft' : 'hover:bg-surface-sunken' }} {{ in_array((string) $p->id, $selectedPackets, true) ? 'bg-gold-tint/60' : '' }}">
                        <x-ui.icon name="grip-vertical" :size="14" class="text-ink_text-muted/60 shrink-0" />
                        <input type="checkbox" class="rj-checkbox shrink-0" value="{{ $p->id }}" wire:model.live="selectedPackets" aria-label="Select {{ $p->code }}">
                        <button type="button" wire:click="selectPacket('{{ $p->id }}')" class="flex-1 min-w-0 flex items-center gap-3 py-2.5 text-left">
                            <span class="min-w-0 flex-1">
                                <span class="block rj-code {{ $active ? 'text-gold-dark' : 'text-ink_text-primary' }}">{{ $p->code }}</span>
                                <span class="block text-[12px] text-ink_text-muted truncate">{{ $p->label ?: 'No label' }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-[13px] font-semibold tabular">{{ $p->items_count }} <span class="font-normal text-ink_text-muted text-[12px]">pcs</span></span>
                                <span class="block text-[11.5px] text-ink_text-muted tabular">{{ number_format((float) $p->items_sum_weight, 2) }} g</span>
                            </span>
                        </button>
                        <button type="button" wire:click="openRename('packet', {{ $p->id }})" title="Rename" aria-label="Rename {{ $p->code }}"
                            class="w-7 h-7 shrink-0 rounded-md text-ink_text-muted hover:bg-white hover:text-ink_text-primary opacity-0 group-hover:opacity-100 focus:opacity-100 flex items-center justify-center">
                            <x-ui.icon name="edit" :size="13" />
                        </button>
                    </div>
                @empty
                    <x-ui.empty-state icon="package" :title="$boxId === 'none' ? 'Every packet is in a box' : 'No packets here yet'" :message="$currentBox ? 'Create one, or drag packets onto this box.' : null" compact>
                        @if ($currentBox)
                            <x-ui.button size="sm" icon="plus" wire:click="openCreate('packet')">New packet here</x-ui.button>
                        @endif
                    </x-ui.empty-state>
                @endforelse

                {{-- Bucket: pieces without a packet --}}
                <div x-data="{ over: false }" x-on:dragover.prevent="if (drag && drag.kind === 'item') over = true" x-on:dragleave="over = false"
                     x-on:drop.prevent="over = false; dropOn('item', null)"
                     :class="over ? 'ring-2 ring-gold bg-gold-tint' : ''"
                     class="rounded-xl mt-2 border border-dashed {{ $packetId === 'none' ? 'border-ink bg-surface-sunken' : 'border-line' }}">
                    <button type="button" wire:click="selectPacket('none')" class="w-full flex items-center gap-3 px-3 py-2.5 text-left">
                        <span class="w-9 h-9 shrink-0 rounded-lg bg-warning-bg text-warning flex items-center justify-center"><x-ui.icon name="unlink" :size="16" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-semibold">Pieces not in a packet</span>
                            <span class="block text-[12px] text-ink_text-muted">{{ $unpackedCount }} {{ \Illuminate\Support\Str::plural('piece', $unpackedCount) }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </section>

        {{-- ============================================================ PIECES --}}
        <section class="{{ $col }}">
            <header class="px-4 pt-4 pb-3 border-b border-line-light">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="flex items-center gap-2 text-[14px] font-bold min-w-0">
                        <x-ui.icon name="gem" :size="16" class="text-gold-dark shrink-0" />
                        <span class="truncate">Pieces {{ $currentPacket ? 'in ' . $currentPacket->code : ($packetId === 'none' ? 'not in a packet' : '') }}</span>
                    </h2>
                    @if ($currentPacket)
                        <a href="{{ route('stock.packets.show', $currentPacket) }}" class="text-[12px] font-semibold shrink-0">Open packet</a>
                    @endif
                </div>
                <x-ui.search-input scan wire:model.live.debounce.250ms="itemSearch" placeholder="Find a piece" class="[&_.rj-input]:h-9" />
            </header>

            @if (count($selectedItems))
                <div class="flex flex-wrap items-center gap-2 px-4 py-2 bg-ink text-white text-[12.5px] animate-fade-in">
                    <span class="font-semibold"><span class="text-gold-light tabular">{{ count($selectedItems) }}</span> selected</span>
                    <span class="text-ink-dim hidden sm:inline">Drag them onto a packet, or</span>
                    <select class="ml-auto h-8 rounded-lg bg-white/10 border-white/10 text-white text-[12.5px] pl-2.5 pr-8 focus:ring-gold/30 focus:border-gold-light"
                        x-on:change="if ($event.target.value !== '') { $wire.moveItems($wire.selectedItems, $event.target.value === 'none' ? null : $event.target.value); $event.target.value = '' }">
                        <option value="" class="text-ink">Move to packet...</option>
                        <option value="none" class="text-ink">No packet</option>
                        @foreach ($allPackets as $op)<option value="{{ $op->id }}" class="text-ink">{{ $op->code }}{{ $op->box ? ' (' . $op->box->code . ')' : '' }}</option>@endforeach
                    </select>
                    <button type="button" wire:click="$set('selectedItems', [])" class="text-ink-dim hover:text-white" aria-label="Clear selection"><x-ui.icon name="x" :size="15" /></button>
                </div>
            @endif

            <div class="flex-1 overflow-y-auto">
                @if ($packetId === '')
                    <x-ui.empty-state icon="corner-down-right" title="Pick a packet" message="Choose a packet in the middle column to see and move the pieces inside it." />
                @else
                    @php $itemIds = $items->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
                    @if ($items->isNotEmpty())
                        <label class="sticky top-0 z-[1] flex items-center gap-2.5 px-4 py-2 bg-surface-sunken/95 backdrop-blur border-b border-line-light text-[12px] font-semibold text-ink_text-secondary cursor-pointer">
                            <input type="checkbox" class="rj-checkbox" @checked(count($itemIds) && ! array_diff($itemIds, $selectedItems))
                                x-on:change="$wire.set('selectedItems', $event.target.checked ? @js($itemIds) : [])">
                            Select all {{ $items->count() }}{{ $items->count() === 200 ? '+' : '' }}
                        </label>
                    @endif
                    <ul class="divide-y divide-line-light">
                        @forelse ($items as $it)
                            <li wire:key="ci-{{ $it->id }}" draggable="true"
                                x-on:dragstart="start($event, 'item', $wire.selectedItems.includes('{{ $it->id }}') ? [...$wire.selectedItems] : ['{{ $it->id }}'])"
                                class="group flex items-center gap-3 pl-2.5 pr-4 py-2.5 cursor-grab active:cursor-grabbing hover:bg-surface-sunken transition-colors
                                    {{ in_array((string) $it->id, $selectedItems, true) ? 'bg-gold-tint/70' : '' }}">
                                <x-ui.icon name="grip-vertical" :size="14" class="text-ink_text-muted/60 shrink-0" />
                                <input type="checkbox" class="rj-checkbox shrink-0" value="{{ $it->id }}" wire:model.live="selectedItems" aria-label="Select {{ $it->label }}">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('stock.items.show', $it) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $it->label }}</a>
                                    <div class="text-[12px] text-ink_text-muted truncate">{{ $it->category }} · {{ ucfirst($it->metal ?? '') }} {{ $it->purity }}</div>
                                </div>
                                <span class="text-[12.5px] tabular text-ink_text-primary shrink-0">{{ number_format($it->weight, 3) }} g</span>
                                <x-ui.status :status="$it->status" size="sm" class="shrink-0 hidden sm:inline-flex" />
                            </li>
                        @empty
                            <li><x-ui.empty-state icon="gem" title="No pieces here" message="Drag pieces onto this packet from another packet, or use Change Item location to scan them in." compact /></li>
                        @endforelse
                    </ul>
                @endif
            </div>
        </section>
    </div>

    <p class="mt-4 text-[12.5px] text-ink_text-muted flex items-center gap-2">
        <x-ui.icon name="info" :size="14" /> Every move made here is saved to that box, packet and piece's history. Sold pieces can't be moved.
    </p>

    <x-ui.modal wire:model="showForm" :title="($formId ? 'Rename ' : 'New ') . $formKind" :icon="$formKind === 'box' ? 'archive' : 'package'" max-width="md" submit="saveForm"
        :subtitle="! $formId && $formKind === 'packet' ? ($currentBox ? 'It will be created inside ' . $currentBox->code . '.' : 'It will be created without a box.') : null">
        <div class="space-y-4">
            <x-ui.field :label="ucfirst($formKind) . ' code'" for="cf-code" error="formCode">
                <input id="cf-code" type="text" wire:model="formCode" autofocus class="rj-input rj-code uppercase @error('formCode') is-invalid @enderror">
            </x-ui.field>
            <x-ui.field label="Label" for="cf-label" error="formLabel" optional>
                <input id="cf-label" type="text" wire:model="formLabel" maxlength="100" class="rj-input">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="saveForm" icon="check">{{ $formId ? 'Save' : 'Create' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
