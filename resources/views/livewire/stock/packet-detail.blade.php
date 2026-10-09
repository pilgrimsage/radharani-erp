<div>
    @php
        $crumbs = [['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Packets', 'href' => route('stock.packets')]];
        if ($packet->box) { $crumbs[] = ['label' => $packet->box->code, 'href' => route('stock.boxes.show', $packet->box)]; }
        $crumbs[] = ['label' => $packet->code];
        $totalWeight = $items->sum('weight');
    @endphp
    <x-ui.page-header :title="$packet->code" :subtitle="$packet->label ?: 'No label set'" :crumbs="$crumbs">
        <x-slot:meta>
            <x-ui.badge tone="gold" size="lg"><x-ui.icon name="package" :size="13" /> Packet</x-ui.badge>
            @unless ($packet->box)
                <x-ui.badge tone="warning" size="lg">Not in a box</x-ui.badge>
            @endunless
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="edit" wire:click="openEdit">Edit</x-ui.button>
            <x-ui.button icon="plus" wire:click="openAdd">Add pieces</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <div class="space-y-6 min-w-0">
            <x-ui.card :padding="false" title="Contents" :subtitle="$items->count() . ' ' . \Illuminate\Support\Str::plural('piece', $items->count()) . ' · ' . number_format($totalWeight, 3) . ' g'" icon="gem">
                @if ($items->count() > 1)
                    <x-slot:actions>
                        <x-ui.button variant="ghost" size="sm" icon="move" wire:click="openMove">Move pieces</x-ui.button>
                    </x-slot:actions>
                @endif
                @if ($items->isEmpty())
                    <x-ui.empty-state icon="gem" title="Nothing in this packet" message="Add pieces by picking them here, or scan them in from Change Item location." compact>
                        <x-ui.button size="sm" icon="plus" wire:click="openAdd">Add pieces</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="scan" :href="route('stock.assign')">Scan to assign</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.table :headers="['Piece', 'Metal', ['label' => 'Weight', 'class' => 'text-right'], 'Status', ['label' => '', 'class' => 'w-px']]">
                        @foreach ($items as $item)
                            <tr wire:key="pi-{{ $item->id }}">
                                <td>
                                    <a href="{{ route('stock.items.show', $item) }}" class="group block">
                                        <span class="block rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $item->label }}</span>
                                        <span class="block text-[12.5px] text-ink_text-secondary">{{ $item->category }}{{ $item->description ? ' · ' . \Illuminate\Support\Str::limit($item->description, 40) : '' }}</span>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="text-ink_text-primary">{{ $item->metal ? ucfirst($item->metal) : '-' }}</span>
                                    <span class="text-ink_text-muted text-[12.5px]"> {{ $item->purity }}</span>
                                </td>
                                <td class="text-right tabular whitespace-nowrap">{{ number_format($item->weight, 3) }} g</td>
                                <td><x-ui.status :status="$item->status" size="sm" /></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-ui.button variant="ghost" size="icon-sm" icon="move" wire:click="openMove({{ $item->id }})" title="Move to another packet" aria-label="Move {{ $item->label }}" />
                                        <x-ui.button variant="ghost" size="icon-sm" icon="unlink" title="Take out of packet" aria-label="Take {{ $item->label }} out of this packet"
                                            x-on:click="$dispatch('rj-confirm', { title: 'Take {{ $item->label }} out of {{ $packet->code }}?', message: 'The piece stays in stock but will not belong to any packet.', confirm: 'Take it out', action: () => $wire.removeItem({{ $item->id }}) })" />
                                        <x-ui.button variant="secondary" size="icon-sm" icon="arrow-right" :href="route('stock.items.show', $item)" title="Open" aria-label="Open {{ $item->label }}" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            <x-ui.card title="History" subtitle="Pieces added or removed, box moves, movements and QR scans" icon="history">
                <x-ui.timeline :events="$events" empty-title="No history recorded yet" />
            </x-ui.card>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Summary" icon="package">
                <div class="grid grid-cols-2 gap-3 pb-4 mb-4 border-b border-line-light">
                    <div>
                        <div class="font-display text-[28px] leading-none font-semibold tabular">{{ $items->count() }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">pieces</div>
                    </div>
                    <div>
                        <div class="font-display text-[28px] leading-none font-semibold tabular">{{ number_format($totalWeight, 3) }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">grams</div>
                    </div>
                </div>
                <x-stock.status-mix :counts="$statusCounts" :total="$items->count()" />
                @if ($metalWeights->filter()->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-line-light flex flex-wrap gap-1.5">
                        @foreach ($metalWeights->filter() as $metal => $w)
                            <x-ui.badge size="sm">{{ ucfirst($metal ?: 'Unspecified') }} · {{ number_format($w, 3) }} g</x-ui.badge>
                        @endforeach
                    </div>
                @endif
                <dl class="rj-dl mt-4 pt-4 border-t border-line-light">
                    <div>
                        <dt>Box</dt>
                        <dd>
                            @if ($packet->box)
                                <a href="{{ route('stock.boxes.show', $packet->box) }}" class="rj-code">{{ $packet->box->code }}</a>
                            @else
                                <span class="text-ink_text-muted">None</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt>Created</dt><dd>{{ $packet->created_at?->format('d M Y') }}</dd></div>
                </dl>
            </x-ui.card>

            <x-stock.qr-panel :qr="$qr" :label="$packet->code" type="packet" />
        </aside>
    </div>

    {{-- Edit --}}
    <x-ui.modal wire:model="showEdit" title="Edit packet" icon="edit" max-width="md" submit="saveEdit" subtitle="Changing the box moves the packet and records it in both boxes' history.">
        <div class="space-y-4">
            <x-ui.field label="Packet code" for="pe-code" error="code">
                <input id="pe-code" type="text" wire:model="code" autofocus class="rj-input rj-code uppercase @error('code') is-invalid @enderror">
            </x-ui.field>
            <x-ui.field label="Label" for="pe-label" error="label" optional>
                <input id="pe-label" type="text" wire:model="label" maxlength="100" class="rj-input">
            </x-ui.field>
            <x-ui.field label="Box" for="pe-box" error="box_id">
                <select id="pe-box" wire:model="box_id" class="rj-select">
                    <option value="">No box</option>
                    @foreach ($boxes as $b)
                        <option value="{{ $b->id }}">{{ $b->code }}{{ $b->label ? ' · ' . $b->label : '' }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="saveEdit" icon="check">Save changes</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Add pieces --}}
    <x-ui.modal wire:model="showAdd" title="Add pieces to {{ $packet->code }}" icon="gem" max-width="xl" submit="addItems"
        subtitle="Search by HUID, internal code, category or description. Sold pieces are hidden.">
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <x-ui.search-input scan wire:model.live.debounce.250ms="itemSearch" placeholder="Search pieces" class="flex-1 min-w-[220px]" />
            <label class="inline-flex items-center gap-2 text-[13px] text-ink_text-secondary cursor-pointer select-none">
                <input type="checkbox" class="rj-checkbox" wire:model.live="onlyUnassigned"> Only pieces not in any packet
            </label>
        </div>
        <div class="border border-line-light rounded-xl max-h-[360px] overflow-y-auto divide-y divide-line-light">
            @forelse ($candidates as $c)
                <label wire:key="ic-{{ $c->id }}" class="flex items-center gap-3 px-3.5 py-2.5 cursor-pointer hover:bg-surface-sunken has-[:checked]:bg-gold-tint">
                    <input type="checkbox" class="rj-checkbox" value="{{ $c->id }}" wire:model.live="addItemIds">
                    <span class="rj-code text-ink_text-primary w-[92px] shrink-0 truncate">{{ $c->label }}</span>
                    <span class="flex-1 min-w-0 text-[13px] text-ink_text-secondary truncate">{{ $c->category }} · {{ ucfirst($c->metal ?? '') }} {{ $c->purity }}</span>
                    <span class="text-[12.5px] tabular text-ink_text-primary">{{ number_format($c->weight, 3) }} g</span>
                    @if ($c->packet)
                        <x-ui.badge size="sm">in {{ $c->packet->code }}</x-ui.badge>
                    @endif
                    <x-ui.status :status="$c->status" size="sm" />
                </label>
            @empty
                <x-ui.empty-state icon="search" title="No pieces found" :message="$onlyUnassigned ? 'Every piece already has a packet. Untick the filter to move pieces from other packets.' : 'Try another search.'" compact />
            @endforelse
        </div>
        @error('addItemIds') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
        <x-slot:footer>
            <span class="mr-auto text-[12.5px] text-ink_text-secondary"><span class="font-semibold text-ink_text-primary tabular">{{ count($addItemIds) }}</span> selected</span>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="addItems" icon="check">Add to packet</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Move pieces --}}
    <x-ui.modal wire:model="showMove" title="Move pieces" icon="move" max-width="lg" submit="moveItems" subtitle="Moves are recorded in both packets' history.">
        <x-ui.field label="Pieces to move" error="moveItemIds">
            <div class="border border-line-light rounded-xl max-h-[220px] overflow-y-auto divide-y divide-line-light">
                @foreach ($items as $item)
                    <label wire:key="mv-{{ $item->id }}" class="flex items-center gap-3 px-3.5 py-2 cursor-pointer hover:bg-surface-sunken has-[:checked]:bg-gold-tint">
                        <input type="checkbox" class="rj-checkbox" value="{{ $item->id }}" wire:model="moveItemIds">
                        <span class="rj-code text-ink_text-primary">{{ $item->label }}</span>
                        <span class="flex-1 text-[12.5px] text-ink_text-secondary truncate">{{ $item->category }}</span>
                        <span class="text-[12.5px] tabular">{{ number_format($item->weight, 3) }} g</span>
                    </label>
                @endforeach
            </div>
        </x-ui.field>
        <x-ui.field label="Destination packet" for="mv-to" error="moveToPacketId" class="mt-4">
            <select id="mv-to" wire:model="moveToPacketId" class="rj-select @error('moveToPacketId') is-invalid @enderror">
                <option value="">Choose a packet</option>
                @foreach ($otherPackets as $op)
                    <option value="{{ $op->id }}">{{ $op->code }}{{ $op->label ? ' · ' . $op->label : '' }}{{ $op->box ? ' (in ' . $op->box->code . ')' : '' }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="moveItems" icon="move">Move pieces</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
