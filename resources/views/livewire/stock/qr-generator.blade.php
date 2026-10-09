<div>
    <x-ui.page-header title="QR Codes" subtitle="Stickers for packets, boxes and pieces. Scanning one opens that record straight away."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'QR Codes']]">
        <x-slot:actions>
            <div class="rj-segment">
                <button type="button" wire:click="$set('mode', 'single')" class="{{ $mode === 'single' ? 'is-active' : '' }}"><x-ui.icon name="qr-code" :size="14" /> Single label</button>
                <button type="button" wire:click="$set('mode', 'batch')" class="{{ $mode === 'batch' ? 'is-active' : '' }}"><x-ui.icon name="layers" :size="14" /> Batch</button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @php $typeNames = ['packet' => 'Packet', 'box' => 'Box', 'item' => 'Piece']; @endphp

    {{-- ================================================================ SINGLE --}}
    @if ($mode === 'single')
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-6 mb-8 items-start">
            <x-ui.card title="Issue a label now" subtitle="Pick what it is for, then type or scan its code" icon="qr-code">
                <div class="rj-segment mb-5">
                    @foreach ($typeNames as $k => $v)
                        <button type="button" wire:click="$set('singleType', '{{ $k }}')" class="{{ $singleType === $k ? 'is-active' : '' }}">
                            <x-ui.icon :name="['packet' => 'package', 'box' => 'archive', 'item' => 'gem'][$k]" :size="14" /> {{ $v }}
                        </button>
                    @endforeach
                </div>

                <form wire:submit="generateSingle" class="relative" x-data="{ open: true }" x-on:click.outside="open = false">
                    <label for="single-code" class="rj-label">{{ $singleType === 'item' ? 'HUID or internal code' : $typeNames[$singleType] . ' code' }}</label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="rj-input-icon flex-1">
                            <x-ui.icon name="search" :size="16" />
                            <x-ui.scan-button target="#single-code" title="Scan the code" class="absolute right-1.5 top-1/2 -translate-y-1/2 !w-8 !h-8" />
                            <input id="single-code" type="text" wire:model.live.debounce.250ms="singleCode" x-on:focus="open = true" x-on:input="open = true; $wire.set('singleQrId', null, false)"
                                autocomplete="off" autofocus class="rj-input h-11 pr-12 rj-code text-[14px] @if($singleError) is-invalid @endif"
                                placeholder="{{ ['packet' => 'PKT-1-2', 'box' => 'BOX-01', 'item' => 'HUID or 5-character code'][$singleType] }}">
                        </div>
                        <x-ui.button type="submit" size="lg" icon="qr-code" target="generateSingle">Issue label</x-ui.button>
                    </div>
                    @if ($singleError)
                        <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $singleError }}</p>
                    @endif

                    @if ($suggestions->isNotEmpty())
                        <div x-show="open" class="absolute left-0 right-0 sm:right-[140px] mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                            @foreach ($suggestions as $s)
                                <button type="button" wire:click="pickSuggestion('{{ $s['code'] }}')" class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left">
                                    <span class="rj-code text-ink_text-primary">{{ $s['code'] }}</span>
                                    <span class="flex-1 text-[12.5px] text-ink_text-muted truncate">{{ $s['sub'] }}</span>
                                    <x-ui.icon name="arrow-right" :size="13" class="text-ink_text-muted" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                </form>

                <div class="mt-6 grid grid-cols-3 gap-3">
                    @foreach ($typeNames as $k => $v)
                        <div class="p-3.5 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
                            <div class="font-display text-[26px] leading-none font-semibold tabular">{{ $counts[$k] ?? 0 }}</div>
                            <div class="text-[12px] text-ink_text-muted mt-1">{{ \Illuminate\Support\Str::plural(strtolower($v)) }} labelled</div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>

            {{-- Preview --}}
            <div class="rounded-card bg-ink ink-grain p-6 ring-1 ring-black/40 shadow-raised text-white">
                @if ($singleQr && $singleTarget)
                    <div class="bg-white rounded-2xl p-5 text-center shadow-modal animate-rise-in">
                        <div class="w-[200px] h-[200px] mx-auto">{!! $singleQr->svg() !!}</div>
                        <div class="rj-code text-[16px] text-ink_text-primary mt-3">{{ \App\Models\Stock\QrCode::labelFor($singleTarget) }}</div>
                        <div class="text-[12px] text-ink_text-secondary">
                            {{ $typeNames[$singleQr->target_type] }} · sticker {{ $singleQr->code }}
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <a href="{{ route('stock.qr.print', ['ids' => $singleQr->id]) }}" target="_blank"
                           class="press flex-1 h-10 rounded-control gold-sheen text-white text-[13px] font-semibold inline-flex items-center justify-center gap-2 shadow-gold hover:text-white">
                            <x-ui.icon name="printer" :size="15" /> Print
                        </a>
                        <a href="{{ $singleQr->detailUrl() }}" class="press h-10 px-4 rounded-control ring-1 ring-white/15 text-ink-fg hover:text-white hover:bg-white/5 text-[13px] font-semibold inline-flex items-center gap-2">
                            Open <x-ui.icon name="arrow-right" :size="14" />
                        </a>
                    </div>
                @else
                    <div class="aspect-square max-w-[240px] mx-auto rounded-2xl border-2 border-dashed border-white/15 flex flex-col items-center justify-center text-center p-6">
                        <x-ui.icon name="qr-code" :size="40" class="text-white/25" />
                        <p class="text-[13px] text-ink-dim mt-3">The label appears here, ready to print.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ================================================================ BATCH --}}
    @if ($mode === 'batch')
        <x-ui.card title="Issue labels in a batch" subtitle="Prepare a sheet of stickers ahead of time" icon="layers" class="mb-8">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-8">
                <div class="space-y-5">
                    <x-ui.field label="Labels for">
                        <div class="rj-segment">
                            @foreach ($typeNames as $k => $v)
                                <button type="button" wire:click="$set('batchType', '{{ $k }}')" class="{{ $batchType === $k ? 'is-active' : '' }}">{{ \Illuminate\Support\Str::plural($v) }}</button>
                            @endforeach
                        </div>
                    </x-ui.field>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @if ($batchType !== 'box')
                            <x-ui.field label="In box" for="b-box">
                                <select id="b-box" wire:model.live="batchBox" class="rj-select">
                                    <option value="">Any box</option>
                                    @foreach ($boxes as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                                </select>
                            </x-ui.field>
                        @endif
                        @if ($batchType === 'item')
                            <x-ui.field label="Status" for="b-status">
                                <select id="b-status" wire:model.live="batchStatus" class="rj-select">
                                    <option value="">Any status</option>
                                    @foreach (\App\Livewire\Stock\ItemForm::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                </select>
                            </x-ui.field>
                        @endif
                        <x-ui.field label="How many at most" for="b-limit" error="batchLimit">
                            <input id="b-limit" type="number" min="1" max="300" wire:model.live.debounce.400ms="batchLimit" class="rj-input tabular">
                        </x-ui.field>
                    </div>
                    <label class="inline-flex items-center gap-2.5 text-[13px] text-ink_text-primary cursor-pointer select-none">
                        <input type="checkbox" class="rj-checkbox" wire:model.live="batchOnlyMissing">
                        Only those that don't have a label yet
                    </label>
                </div>
                <div class="rounded-2xl bg-surface-sunken ring-1 ring-inset ring-line-light p-5 flex flex-col">
                    <div class="text-[12.5px] text-ink_text-secondary">Matching right now</div>
                    <div class="font-display text-[46px] leading-none font-semibold tabular mt-1">{{ $batchMatches }}</div>
                    <div class="text-[12.5px] text-ink_text-muted mt-1">
                        {{ \Illuminate\Support\Str::plural(strtolower($typeNames[$batchType]), $batchMatches) }}{{ $batchMatches > $batchLimit ? ', the first ' . $batchLimit . ' will be labelled' : '' }}
                    </div>
                    <x-ui.button class="mt-auto pt-0 w-full" icon="printer" wire:click="generateBatch" :disabled="! $batchMatches">
                        Issue and print {{ min($batchMatches, $batchLimit) }}
                    </x-ui.button>
                    <p class="text-[11.5px] text-ink_text-muted mt-2.5">Existing labels are reused, never replaced.</p>
                </div>
            </div>
        </x-ui.card>
    @endif

    {{-- ================================================================ REGISTER --}}
    <div class="flex items-end justify-between gap-4 mb-3.5">
        <div>
            <h2 class="font-display text-[24px] font-semibold leading-tight">Issued labels</h2>
            <p class="text-[13px] text-ink_text-secondary">Every sticker ever issued. Tick some to reprint them together.</p>
        </div>
    </div>

    @php $pageIds = $codes->pluck('id')->map(fn ($id) => (string) $id)->all(); @endphp
    <x-ui.datatable :paginator="$codes">
        <x-slot:toolbar>
            <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="Sticker code" class="w-full sm:w-[240px]" />
            <div class="rj-segment">
                <button type="button" wire:click="$set('typeFilter', '')" class="{{ $typeFilter === '' ? 'is-active' : '' }}">All</button>
                @foreach ($typeNames as $k => $v)
                    <button type="button" wire:click="$set('typeFilter', '{{ $k }}')" class="{{ $typeFilter === $k ? 'is-active' : '' }}">{{ \Illuminate\Support\Str::plural($v) }}</button>
                @endforeach
            </div>
        </x-slot:toolbar>

        @if (count($selected))
            <x-slot:bulk>
                <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 bg-ink text-white animate-fade-in">
                    <span class="text-[13px] font-semibold mr-1"><span class="text-gold-light tabular">{{ count($selected) }}</span> selected</span>
                    <div class="w-px h-5 bg-white/10"></div>
                    <button type="button" wire:click="printSelected" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-[12.5px] font-semibold text-ink-fg hover:text-white hover:bg-white/10">
                        <x-ui.icon name="printer" :size="14" /> Reprint
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
            <th class="w-16"><span class="sr-only">QR</span></th>
            <x-ui.th field="code" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Sticker</x-ui.th>
            <x-ui.th field="type" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">For</x-ui.th>
            <x-ui.th field="issued" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Issued</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($codes as $qr)
            @php $t = $targets[$qr->target_type][$qr->target_id] ?? null; @endphp
            <tr wire:key="qr-{{ $qr->id }}" @class(['is-selected' => in_array((string) $qr->id, $selected, true)])>
                <td class="!pr-0"><input type="checkbox" class="rj-checkbox" value="{{ $qr->id }}" wire:model.live="selected"></td>
                <td><div class="w-11 h-11 p-0.5 bg-white rounded-md ring-1 ring-line">{!! $qr->svg() !!}</div></td>
                <td class="rj-code">{{ $qr->code }}</td>
                <td>
                    @if ($t)
                        <a href="{{ $qr->detailUrl() }}" class="group inline-flex items-center gap-2">
                            <x-ui.badge size="sm">{{ $typeNames[$qr->target_type] }}</x-ui.badge>
                            <span class="rj-code text-ink_text-primary group-hover:text-gold-dark">{{ \App\Models\Stock\QrCode::labelFor($t) }}</span>
                        </a>
                    @else
                        <x-ui.badge tone="danger" size="sm">Record missing</x-ui.badge>
                    @endif
                </td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $qr->created_at?->format('d M Y, g:i a') }}</td>
                <td class="text-right">
                </td>
            </tr>
        @empty
            <tr><td colspan="6">
                <x-ui.empty-state icon="qr-code" title="No labels issued yet" message="Issue a single label above, or switch to Batch to prepare a whole sheet." />
            </td></tr>
        @endforelse
    </x-ui.datatable>
</div>
