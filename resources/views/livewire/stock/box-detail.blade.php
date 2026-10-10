<div>
    <x-ui.page-header :title="$box->code" :subtitle="$box->label ?: 'No label set'"
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Boxes', 'href' => route('stock.boxes')], ['label' => $box->code]]">
        <x-slot:meta>
            <x-ui.badge tone="gold" size="lg"><x-ui.icon name="archive" :size="13" /> Box</x-ui.badge>
            @php $lastAudit = $box->audits()->latest('id')->first(); @endphp
            <x-ui.badge size="lg" :tone="$lastAudit ? ($lastAudit->clean ? 'success' : 'warning') : 'neutral'">
                {{ $lastAudit ? 'Last audited ' . $lastAudit->created_at->format('j M Y') . ($lastAudit->clean ? '' : ' (differences)') : 'Never audited' }}
            </x-ui.badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="edit" wire:click="openEdit">Edit</x-ui.button>
            <x-ui.button icon="plus" wire:click="openAdd">Add packets</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <div class="space-y-6 min-w-0">
            <x-ui.card :padding="false" title="Packets inside" :subtitle="$packets->count() . ' ' . \Illuminate\Support\Str::plural('packet', $packets->count()) . ' in this box'" icon="package">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" icon="layers" :href="route('stock.configurator')">Reorganise</x-ui.button>
                </x-slot:actions>
                @if ($packets->isEmpty())
                    <x-ui.empty-state icon="package" title="This box is empty" message="Move existing packets in, or create a new packet here." compact>
                        <x-ui.button size="sm" icon="plus" wire:click="openAdd">Add packets</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <x-ui.table :headers="['Packet', ['label' => 'Pieces', 'class' => 'text-right'], ['label' => 'Weight', 'class' => 'text-right'], ['label' => '', 'class' => 'w-px']]">
                        @foreach ($packets as $packet)
                            <tr wire:key="bp-{{ $packet->id }}">
                                <td>
                                    <a href="{{ route('stock.packets.show', $packet) }}" class="group flex items-center gap-3">
                                        <span class="w-9 h-9 shrink-0 rounded-lg bg-surface-muted text-ink_text-secondary group-hover:bg-gold-tint group-hover:text-gold-dark flex items-center justify-center transition-colors">
                                            <x-ui.icon name="package" :size="16" />
                                        </span>
                                        <span>
                                            <span class="block rj-code text-ink_text-primary group-hover:text-gold-dark">{{ $packet->code }}</span>
                                            <span class="block text-[12.5px] text-ink_text-secondary">{{ $packet->label ?: 'No label' }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="text-right tabular font-semibold">{{ $packet->items_count }}</td>
                                <td class="text-right tabular text-ink_text-secondary whitespace-nowrap">{{ $packet->items_sum_weight ? number_format($packet->items_sum_weight, 3) . ' g' : '-' }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-ui.button variant="ghost" size="icon-sm" icon="unlink" title="Take out of this box" aria-label="Take {{ $packet->code }} out of this box"
                                            x-on:click="$dispatch('rj-confirm', { title: 'Take {{ $packet->code }} out of {{ $box->code }}?', message: 'The packet and its pieces stay as they are. It will not belong to any box until you move it again.', confirm: 'Take it out', action: () => $wire.removePacket({{ $packet->id }}) })" />
                                        <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('stock.packets.show', $packet)">Open</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>

            <x-ui.card title="History" subtitle="Movements, packets added or removed, renames and QR scans" icon="history">
                <x-ui.timeline :events="$events" empty-title="No history recorded yet" empty-message="Movements, reassignments and scans will appear here." />
            </x-ui.card>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Summary" icon="archive">
                <div class="grid grid-cols-3 gap-3 pb-4 mb-4 border-b border-line-light">
                    <div>
                        <div class="font-display text-[28px] leading-none font-semibold tabular">{{ $packets->count() }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">packets</div>
                    </div>
                    <div>
                        <div class="font-display text-[28px] leading-none font-semibold tabular">{{ $pieces }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">pieces</div>
                    </div>
                    <div>
                        <div class="font-display text-[28px] leading-none font-semibold tabular">{{ number_format($weight, 1) }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">grams</div>
                    </div>
                </div>
                <x-stock.status-mix :counts="$statusCounts" :total="$pieces" />
                @if ($metalWeights->filter()->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-line-light flex flex-wrap gap-1.5">
                        @foreach ($metalWeights->filter() as $metal => $w)
                            <x-ui.badge size="sm">{{ ucfirst($metal ?: 'Unspecified') }} · {{ number_format($w, 3) }} g</x-ui.badge>
                        @endforeach
                    </div>
                @endif
                <dl class="rj-dl mt-4 pt-4 border-t border-line-light">
                    <div><dt>Created</dt><dd>{{ $box->created_at?->format('d M Y') }}</dd></div>
                    <div><dt>Last change</dt><dd>{{ $box->updated_at?->diffForHumans() }}</dd></div>
                </dl>
            </x-ui.card>

            <x-stock.qr-panel :qr="$qr" :label="$box->code" type="box" />
        </aside>
    </div>

    {{-- Edit --}}
    <x-ui.modal wire:model="showEdit" title="Edit box" icon="edit" max-width="md" submit="saveEdit" subtitle="Renaming keeps every movement and scan attached to this box.">
        <div class="space-y-4">
            <x-ui.field label="Box code" for="edit-code" error="code">
                <input id="edit-code" type="text" wire:model="code" autofocus class="rj-input rj-code uppercase @error('code') is-invalid @enderror">
            </x-ui.field>
            <x-ui.field label="Label" for="edit-label" error="label" optional>
                <input id="edit-label" type="text" wire:model="label" maxlength="100" class="rj-input">
            </x-ui.field>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="saveEdit" icon="check">Save changes</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Add packets --}}
    <x-ui.modal wire:model="showAdd" title="Add packets to {{ $box->code }}" icon="package" max-width="lg" submit="addPackets">
        <div class="rj-segment mb-5">
            <button type="button" wire:click="$set('addMode', 'existing')" class="{{ $addMode === 'existing' ? 'is-active' : '' }}"><x-ui.icon name="move" :size="14" /> Move existing</button>
            <button type="button" wire:click="$set('addMode', 'new')" class="{{ $addMode === 'new' ? 'is-active' : '' }}"><x-ui.icon name="plus" :size="14" /> Create new</button>
        </div>

        @if ($addMode === 'existing')
            <x-ui.search-input scan wire:model.live.debounce.250ms="packetSearch" placeholder="Search packets by code or label" class="mb-3" />
            <div class="border border-line-light rounded-xl max-h-[320px] overflow-y-auto divide-y divide-line-light">
                @forelse ($candidatePackets as $p)
                    <label wire:key="cand-{{ $p->id }}" class="flex items-center gap-3 px-3.5 py-2.5 cursor-pointer hover:bg-surface-sunken has-[:checked]:bg-gold-tint">
                        <input type="checkbox" class="rj-checkbox" value="{{ $p->id }}" wire:model="addPacketIds">
                        <span class="min-w-0 flex-1">
                            <span class="rj-code text-ink_text-primary">{{ $p->code }}</span>
                            <span class="text-[12.5px] text-ink_text-secondary"> {{ $p->label }}</span>
                        </span>
                        <span class="text-[12px] text-ink_text-muted tabular">{{ $p->items_count }} pcs</span>
                        @if ($p->box)
                            <x-ui.badge size="sm">in {{ $p->box->code }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="warning" size="sm">No box</x-ui.badge>
                        @endif
                    </label>
                @empty
                    <x-ui.empty-state icon="package" title="No other packets found" message="Every packet is already in this box, or none match your search." compact />
                @endforelse
            </div>
            @error('addPacketIds') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
            <p class="rj-help">Packets already in another box will be moved here. Each move is recorded in both boxes' history.</p>
        @else
            <div class="space-y-4">
                <x-ui.field label="Packet code" for="np-code" error="newPacketCode">
                    <input id="np-code" type="text" wire:model="newPacketCode" class="rj-input rj-code uppercase @error('newPacketCode') is-invalid @enderror">
                </x-ui.field>
                <x-ui.field label="Label" for="np-label" error="newPacketLabel" optional>
                    <input id="np-label" type="text" wire:model="newPacketLabel" maxlength="100" class="rj-input">
                </x-ui.field>
            </div>
        @endif

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="addPackets" icon="check">{{ $addMode === 'new' ? 'Create packet' : 'Move into box' }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
