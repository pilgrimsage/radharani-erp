<div>
    <x-ui.page-header title="Change Item location" subtitle="Keep the system matching the stock room. Put pieces into packets and packets into boxes."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Change Item location']]">
        <x-slot:actions>
            <div class="rj-segment">
                <button type="button" wire:click="setMode('scan')" class="{{ $mode === 'scan' ? 'is-active' : '' }}"><x-ui.icon name="scan" :size="14" /> Scan</button>
                <button type="button" wire:click="setMode('pick')" class="{{ $mode === 'pick' ? 'is-active' : '' }}"><x-ui.icon name="list" :size="14" /> Pick from a list</button>
                <button type="button" wire:click="setMode('sheet')" class="{{ $mode === 'sheet' ? 'is-active' : '' }}"><x-ui.icon name="file-spreadsheet" :size="14" /> Spreadsheet</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ================================================================ SCAN --}}
    @if ($mode === 'scan')
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px] gap-6 items-start"
             x-data x-on:scan-ready.window="window.matchMedia('(pointer: fine)').matches && $nextTick(() => $refs.scan && $refs.scan.focus())"
             x-on:dest-cleared.window="$nextTick(() => $refs.dest && $refs.dest.focus())">
            <div class="space-y-6 min-w-0">
                {{-- Destination --}}
                @if (! $dest)
                    <x-ui.card title="Where are they going?" subtitle="Scan the packet or box sticker, or type its code" icon="archive">
                        <form wire:submit="setDestination" class="flex flex-col sm:flex-row gap-3">
                            <x-ui.scan-button target="#assign-dest" submit="form" title="Scan the packet or box" variant="button" label="Scan with camera" class="!h-12 sm:hidden" />
                            <div class="rj-input-icon flex-1">
                                <x-ui.icon name="scan" :size="17" />
                                <input id="assign-dest" x-ref="dest" type="text" wire:model="destCode" autofocus autocomplete="off" placeholder="e.g. PKT-1-2 or BOX-01"
                                    class="rj-input h-12 text-[15px] rj-code @if($destError) is-invalid @endif">
                            </div>
                            <x-ui.scan-button target="#assign-dest" submit="form" title="Scan the packet or box" variant="button" label="Camera" class="max-sm:!hidden !h-12" />
                            <x-ui.button type="submit" size="lg" target="setDestination" iconRight="arrow-right" class="h-12">Set destination</x-ui.button>
                        </form>
                        @if ($destError)
                            <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $destError }}</p>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">
                            <div class="flex gap-3 p-3.5 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
                                <x-ui.icon name="package" :size="18" class="text-gold-dark shrink-0 mt-0.5" />
                                <div class="text-[12.5px] text-ink_text-secondary"><span class="font-semibold text-ink_text-primary">Scan a packet</span>, then scan pieces to put them inside it.</div>
                            </div>
                            <div class="flex gap-3 p-3.5 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
                                <x-ui.icon name="archive" :size="18" class="text-gold-dark shrink-0 mt-0.5" />
                                <div class="text-[12.5px] text-ink_text-secondary"><span class="font-semibold text-ink_text-primary">Scan a box</span>, then scan packets to put them inside it.</div>
                            </div>
                        </div>
                    </x-ui.card>
                @else
                    <div class="rounded-card bg-ink ink-grain text-white p-6 ring-1 ring-black/40 shadow-raised animate-rise-in">
                        <div class="flex flex-wrap items-start gap-4">
                            <div class="w-12 h-12 shrink-0 rounded-xl gold-sheen text-ink flex items-center justify-center shadow-gold">
                                <x-ui.icon :name="$destType === 'packet' ? 'package' : 'archive'" :size="22" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[12px] font-semibold text-ink-dim">Now putting {{ $destType === 'packet' ? 'pieces' : 'packets' }} into</div>
                                <div class="font-display text-[34px] leading-tight font-semibold text-gold-light">{{ $dest->code }}</div>
                                <div class="text-[13px] text-ink-fg">
                                    {{ $dest->label ?: 'No label' }}
                                    @if ($destType === 'packet')
                                        · {{ $dest->items_count }} {{ \Illuminate\Support\Str::plural('piece', $dest->items_count) }} inside{{ $dest->box ? ' · in ' . $dest->box->code : '' }}
                                    @else
                                        · {{ $dest->packets_count }} {{ \Illuminate\Support\Str::plural('packet', $dest->packets_count) }} inside
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ $destType === 'packet' ? route('stock.packets.show', $dest) : route('stock.boxes.show', $dest) }}" target="_blank"
                                   class="press h-9 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white ring-1 ring-white/10 hover:bg-white/5 inline-flex items-center gap-1.5">
                                    <x-ui.icon name="external-link" :size="13" /> Open
                                </a>
                                <button type="button" wire:click="clearDestination" class="press h-9 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white ring-1 ring-white/10 hover:bg-white/5 inline-flex items-center gap-1.5">
                                    <x-ui.icon name="repeat" :size="13" /> Change
                                </button>
                            </div>
                        </div>

                        <x-ui.scan-button target="#scan-input" submit="form" continuous variant="button"
                            :title="$destType === 'packet' ? 'Scan pieces into ' . $dest->code : 'Scan packets into ' . $dest->code"
                            label="Scan with camera" class="w-full mt-6 !h-12 !bg-gold-light !text-ink !border-gold-light hover:!brightness-105" />
                        <form class="mt-4" x-on:submit.prevent="const v = $refs.scan.value; $refs.scan.value = ''; if (v.trim()) $wire.scan(v)">
                            <label for="scan-input" class="block text-[12.5px] font-semibold text-ink-fg mb-2">
                                Scan {{ $destType === 'packet' ? 'a piece (HUID, internal code or its QR sticker)' : 'a packet (its code or QR sticker)' }}
                            </label>
                            <div class="relative">
                                <x-ui.icon name="scan" :size="19" class="absolute left-4 top-1/2 -translate-y-1/2 text-gold-light pointer-events-none" />
                                <input id="scan-input" x-ref="scan" type="text" autocomplete="off" autofocus
                                    class="w-full h-14 pl-12 pr-28 rounded-xl bg-white/[.06] border border-white/10 text-white text-[17px] font-mono tracking-wider placeholder:text-white/30
                                           focus:outline-none focus:border-gold-light focus:ring-4 focus:ring-gold/20"
                                    placeholder="Waiting for scan">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11.5px] text-ink-dim" wire:loading.remove wire:target="scan">Press Enter</span>
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gold-light" wire:loading wire:target="scan"><x-ui.icon name="loader" :size="17" class="animate-spin" /></span>
                            </div>
                            <p class="text-[12px] text-ink-dim mt-2">Scanners type the code and press Enter for you, so you can keep scanning without touching the screen.</p>
                        </form>
                    </div>

                    @if ($recent->isNotEmpty())
                        <x-ui.card :padding="false" title="Most recently placed here" icon="history">
                            <ul class="divide-y divide-line-light">
                                @foreach ($recent as $r)
                                    <li class="flex items-center gap-3 px-5 py-3">
                                        <span class="rj-code text-ink_text-primary">{{ $destType === 'packet' ? $r->label : $r->code }}</span>
                                        <span class="flex-1 text-[12.5px] text-ink_text-secondary truncate">{{ $destType === 'packet' ? $r->category . ' · ' . number_format($r->weight, 3) . ' g' : ($r->label ?: '') }}</span>
                                        <span class="text-[12px] text-ink_text-muted">{{ $r->updated_at->diffForHumans() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui.card>
                    @endif
                @endif
            </div>

            {{-- Session log --}}
            <x-ui.card :padding="false" title="This session" icon="list" class="xl:sticky xl:top-24">
                <x-slot:actions>
                    @if (count($log))
                        <x-ui.button variant="ghost" size="sm" wire:click="clearLog">Clear</x-ui.button>
                    @endif
                </x-slot:actions>
                <div class="grid grid-cols-2 border-b border-line-light">
                    <div class="px-5 py-3.5">
                        <div class="font-display text-[28px] leading-none font-semibold text-success tabular">{{ $logCounts['success'] }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">assigned</div>
                    </div>
                    <div class="px-5 py-3.5 border-l border-line-light">
                        <div class="font-display text-[28px] leading-none font-semibold tabular {{ $logCounts['error'] ? 'text-danger' : 'text-ink_text-muted' }}">{{ $logCounts['error'] }}</div>
                        <div class="text-[12px] text-ink_text-muted mt-1">not found or blocked</div>
                    </div>
                </div>
                <ul class="max-h-[520px] overflow-y-auto divide-y divide-line-light">
                    @forelse ($log as $i => $entry)
                        <li wire:key="log-{{ count($log) - $i }}" data-log class="flex items-start gap-3 px-5 py-3 {{ $i === 0 ? 'animate-rise-in' : '' }} {{ $entry['undone'] ? 'opacity-50' : '' }}">
                            <span @class([
                                'mt-0.5 w-6 h-6 shrink-0 rounded-full flex items-center justify-center',
                                'bg-success-bg text-success' => $entry['tone'] === 'success',
                                'bg-danger-bg text-danger' => $entry['tone'] === 'error',
                                'bg-info-bg text-info' => $entry['tone'] === 'info',
                            ])>
                                <x-ui.icon :name="['success' => 'check', 'error' => 'x', 'info' => 'info'][$entry['tone']]" :size="12" />
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="rj-code text-ink_text-primary truncate {{ $entry['undone'] ? 'line-through' : '' }}">{{ $entry['code'] }}</span>
                                    <span class="text-[11px] text-ink_text-muted tabular shrink-0">{{ $entry['at'] }}</span>
                                </div>
                                <div class="text-[12.5px] {{ $entry['tone'] === 'error' ? 'text-danger' : 'text-ink_text-secondary' }}">
                                    {{ $entry['undone'] ? 'Undone' : $entry['message'] }}{{ $entry['detail'] && ! $entry['undone'] ? ' · ' . $entry['detail'] : '' }}
                                </div>
                            </div>
                            @if ($entry['undo'] && ! $entry['undone'])
                                <button type="button" wire:click="undo({{ $i }})" class="text-[12px] font-semibold text-ink_text-secondary hover:text-gold-dark shrink-0 mt-0.5">Undo</button>
                            @endif
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="scan" title="Nothing scanned yet" message="Each scan shows up here, newest first, with an undo in case of a mistake." compact /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    @endif

    {{-- ================================================================ PICK --}}
    @if ($mode === 'pick')
        <div class="grid grid-cols-1 xl:grid-cols-[340px_minmax(0,1fr)] gap-6 items-start">
            <x-ui.card title="Destination" icon="archive" class="xl:sticky xl:top-24">
                <div class="space-y-4">
                    <x-ui.field label="What are you moving?">
                        <div class="rj-segment w-full">
                            <button type="button" wire:click="$set('pickKind', 'items')" class="flex-1 justify-center {{ $pickKind === 'items' ? 'is-active' : '' }}">Pieces</button>
                            <button type="button" wire:click="$set('pickKind', 'packets')" class="flex-1 justify-center {{ $pickKind === 'packets' ? 'is-active' : '' }}">Packets</button>
                        </div>
                    </x-ui.field>
                    <x-ui.field :label="$pickKind === 'items' ? 'Into packet' : 'Into box'" for="pick-dest" error="pickDestId">
                        <select id="pick-dest" wire:model.live="pickDestId" class="rj-select @error('pickDestId') is-invalid @enderror">
                            <option value="">Choose...</option>
                            @if ($pickKind === 'items')
                                @foreach ($packets as $boxCode => $group)
                                    <optgroup label="{{ $boxCode }}">
                                        @foreach ($group as $p)
                                            <option value="{{ $p->id }}">{{ $p->code }}{{ $p->label ? ' · ' . $p->label : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            @else
                                @foreach ($boxes as $b)
                                    <option value="{{ $b->id }}">{{ $b->code }}{{ $b->label ? ' · ' . $b->label : '' }}</option>
                                @endforeach
                            @endif
                        </select>
                    </x-ui.field>
                    <div class="pt-4 border-t border-line-light">
                        <div class="flex items-baseline justify-between mb-3">
                            <span class="text-[13px] text-ink_text-secondary">Selected</span>
                            <span class="font-display text-[28px] leading-none font-semibold tabular">{{ count($pickSelected) }}</span>
                        </div>
                        @error('pickSelected') <p class="rj-error mb-2"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
                        <x-ui.button class="w-full" icon="check" wire:click="assignPicked">Assign selected</x-ui.button>
                    </div>
                </div>
            </x-ui.card>

            @php $rowIds = $pickRows->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
            <x-ui.datatable>
                <x-slot:toolbar>
                    <x-ui.search-input scan wire:model.live.debounce.300ms="pickSearch" :placeholder="$pickKind === 'items' ? 'Search pieces' : 'Search packets'" class="w-full sm:w-[260px]" />
                    @if ($pickKind === 'items')
                        <select wire:model.live="pickCategory" class="rj-select w-auto min-w-[150px]" aria-label="Category">
                            <option value="">All categories</option>
                            @foreach ($categories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                        </select>
                    @endif
                    <label class="inline-flex items-center gap-2 text-[13px] text-ink_text-secondary cursor-pointer select-none">
                        <input type="checkbox" class="rj-checkbox" wire:model.live="pickUnassigned">
                        {{ $pickKind === 'items' ? 'Only pieces not in a packet' : 'Only packets not in a box' }}
                    </label>
                    <span class="ml-auto text-[12.5px] text-ink_text-muted">{{ $pickRows->count() }}{{ $pickRows->count() === 100 ? '+' : '' }} shown</span>
                </x-slot:toolbar>
                <x-slot:head>
                    <th class="w-10 !pr-0">
                        <input type="checkbox" class="rj-checkbox" aria-label="Select all shown"
                            @checked(count($rowIds) && ! array_diff($rowIds, $pickSelected))
                            x-on:change="$wire.set('pickSelected', $event.target.checked ? @js($rowIds) : [])">
                    </th>
                    @if ($pickKind === 'items')
                        <th>Piece</th><th>Metal</th><th class="text-right">Weight</th><th>Now in</th><th>Status</th>
                    @else
                        <th>Packet</th><th class="text-right">Pieces</th><th>Now in</th>
                    @endif
                </x-slot:head>
                @forelse ($pickRows as $r)
                    <tr wire:key="pick-{{ $pickKind }}-{{ $r->id }}" @class(['is-selected' => in_array((string) $r->id, $pickSelected, true)])>
                        <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $r->id }}" wire:model.live="pickSelected"></td>
                        @if ($pickKind === 'items')
                            <td>
                                <div class="rj-code">{{ $r->label }}</div>
                                <div class="text-[12.5px] text-ink_text-muted">{{ $r->category }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ ucfirst($r->metal ?? '-') }} <span class="text-ink_text-muted text-[12.5px]">{{ $r->purity }}</span></td>
                            <td class="text-right tabular">{{ number_format($r->weight, 3) }} g</td>
                            <td>@if ($r->packet)<span class="rj-code text-[12px]">{{ $r->packet->code }}</span>@else<span class="text-[12.5px] text-ink_text-muted">No packet</span>@endif</td>
                            <td><x-ui.status :status="$r->status" size="sm" /></td>
                        @else
                            <td>
                                <div class="rj-code">{{ $r->code }}</div>
                                <div class="text-[12.5px] text-ink_text-muted">{{ $r->label ?: 'No label' }}</div>
                            </td>
                            <td class="text-right tabular">{{ $r->items_count }}</td>
                            <td>@if ($r->box)<span class="rj-code text-[12px]">{{ $r->box->code }}</span>@else<span class="text-[12.5px] text-ink_text-muted">No box</span>@endif</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-ui.empty-state icon="check-circle" :title="$pickUnassigned ? ($pickKind === 'items' ? 'Every piece is in a packet' : 'Every packet is in a box') : 'Nothing matches'"
                            :message="$pickUnassigned ? 'Untick the filter to move things that are already placed.' : 'Try a different search.'" compact />
                    </td></tr>
                @endforelse
            </x-ui.datatable>
        </div>
    @endif

    {{-- ================================================================ SHEET --}}
    @if ($mode === 'sheet')
        <div class="grid grid-cols-1 xl:grid-cols-[380px_minmax(0,1fr)] gap-6 items-start">
            <x-ui.card title="Upload a spreadsheet" icon="file-spreadsheet" class="xl:sticky xl:top-24">
                <p class="text-[13px] text-ink_text-secondary mb-4">Two columns: the <span class="font-semibold text-ink_text-primary">code</span> of a piece or packet, and the <span class="font-semibold text-ink_text-primary">destination</span> packet or box. CSV or Excel (.xlsx).</p>

                <label class="group relative flex flex-col items-center justify-center gap-2 p-6 rounded-xl border-2 border-dashed border-line hover:border-gold hover:bg-gold-tint/40 cursor-pointer transition-colors text-center"
                       x-data="{ drag: false }" x-on:dragover.prevent="drag = true" x-on:dragleave="drag = false" x-on:drop="drag = false"
                       :class="drag ? 'border-gold bg-gold-tint/60' : ''">
                    <input type="file" wire:model="sheet" accept=".csv,.xlsx,.txt" class="absolute inset-0 opacity-0 cursor-pointer">
                    <span class="w-11 h-11 rounded-xl bg-white ring-1 ring-line shadow-card text-gold-dark flex items-center justify-center">
                        <x-ui.icon name="upload" :size="19" wire:loading.remove wire:target="sheet" />
                        <x-ui.icon name="loader" :size="19" class="animate-spin" wire:loading wire:target="sheet" />
                    </span>
                    <span class="text-[13.5px] font-semibold text-ink_text-primary">{{ $sheetName ?: 'Drop a file here or click to choose' }}</span>
                    <span class="text-[12px] text-ink_text-muted">Up to 1,000 rows, 5 MB</span>
                </label>
                @error('sheet') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror

                <div class="flex flex-wrap gap-2 mt-4">
                    <x-ui.button variant="ghost" size="sm" icon="download" wire:click="downloadTemplate">Download template</x-ui.button>
                    @if ($sheetRows)
                        <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetSheet">Start over</x-ui.button>
                    @endif
                </div>

                @if ($sheetRows)
                    <div class="mt-5 pt-5 border-t border-line-light space-y-2.5 text-[13px]">
                        <div class="flex justify-between"><span class="text-ink_text-secondary">Ready to apply</span><span class="font-bold text-success tabular">{{ $sheetCounts['ok'] }}</span></div>
                        <div class="flex justify-between"><span class="text-ink_text-secondary">Already in place</span><span class="font-semibold tabular">{{ $sheetCounts['same'] }}</span></div>
                        <div class="flex justify-between"><span class="text-ink_text-secondary">Problems</span><span class="font-semibold tabular {{ $sheetCounts['error'] ? 'text-danger' : '' }}">{{ $sheetCounts['error'] }}</span></div>
                        @if ($sheetCounts['done'])
                            <div class="flex justify-between"><span class="text-ink_text-secondary">Applied</span><span class="font-semibold tabular text-gold-dark">{{ $sheetCounts['done'] }}</span></div>
                        @endif
                    </div>
                    <x-ui.button class="w-full mt-5" icon="check" :disabled="! $sheetCounts['ok']"
                        x-on:click="$dispatch('rj-confirm', { title: 'Apply {{ $sheetCounts['ok'] }} row(s)?', message: 'Rows with problems or already in place are skipped. Every move is recorded in the history.', confirm: 'Apply', action: () => $wire.applySheet() })">
                        Apply {{ $sheetCounts['ok'] }} {{ \Illuminate\Support\Str::plural('row', $sheetCounts['ok']) }}
                    </x-ui.button>
                @endif
            </x-ui.card>

            <x-ui.card :padding="false" title="Preview" subtitle="Nothing is changed until you apply" icon="eye">
                @if (! $sheetRows)
                    <x-ui.empty-state icon="file-spreadsheet" title="No file yet" message="Upload a spreadsheet to see, row by row, what will move where." />
                @else
                    <div class="max-h-[640px] overflow-y-auto">
                        <x-ui.table :headers="['Row', 'Code', 'From', 'To', 'Result']">
                            @foreach ($sheetRows as $r)
                                <tr wire:key="sr-{{ $r['line'] }}" @class(['bg-danger-bg/40' => $r['status'] === 'error'])>
                                    <td class="text-ink_text-muted tabular">{{ $r['line'] }}</td>
                                    <td class="rj-code">{{ $r['code'] ?: '(empty)' }}</td>
                                    <td class="rj-code text-[12px] text-ink_text-secondary">{{ $r['from'] ?: '-' }}</td>
                                    <td class="rj-code text-[12px]">{{ $r['dest'] ?: '(empty)' }}</td>
                                    <td>
                                        @switch($r['status'])
                                            @case('ok') <x-ui.badge tone="success" size="sm">Will move</x-ui.badge> @break
                                            @case('done') <x-ui.badge tone="gold" size="sm">Moved</x-ui.badge> @break
                                            @case('same') <x-ui.badge size="sm">Already there</x-ui.badge> @break
                                            @default <x-ui.badge tone="danger" size="sm">{{ $r['message'] }}</x-ui.badge>
                                        @endswitch
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
