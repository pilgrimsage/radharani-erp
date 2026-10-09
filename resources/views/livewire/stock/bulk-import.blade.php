<div>
    <x-ui.page-header title="Bulk Import" subtitle="Enter many pieces at once from a spreadsheet. Nothing is saved until you have reviewed every row."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Bulk Import']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="download" wire:click="downloadTemplate">Template</x-ui.button>
            @if ($step > 1)
                <x-ui.button variant="ghost" icon="refresh" x-on:click="$dispatch('rj-confirm', { title: 'Start over?', message: 'The uploaded file and your column choices will be cleared.', confirm: 'Start over', action: () => $wire.startOver() })">Start over</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Stepper --}}
    <ol class="flex items-center gap-2 sm:gap-3 mb-7 overflow-x-auto pb-1">
        @foreach ([1 => 'Upload file', 2 => 'Match columns', 3 => 'Review rows', 4 => 'Done'] as $n => $name)
            <li class="flex items-center gap-2 sm:gap-3 shrink-0">
                <button type="button" wire:click="backTo({{ $n }})" @disabled($n >= $step || $step === 4)
                    class="flex items-center gap-2.5 h-10 pl-1.5 pr-4 rounded-full transition-colors
                    {{ $n === $step ? 'bg-ink text-white shadow-raised' : ($n < $step ? 'bg-white text-ink_text-primary ring-1 ring-line hover:ring-gold-soft' : 'bg-surface-muted text-ink_text-muted') }}">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-bold
                        {{ $n === $step ? 'gold-sheen text-white' : ($n < $step ? 'bg-success-bg text-success' : 'bg-white text-ink_text-muted') }}">
                        @if ($n < $step) <x-ui.icon name="check" :size="13" /> @else {{ $n }} @endif
                    </span>
                    <span class="text-[13px] font-semibold whitespace-nowrap">{{ $name }}</span>
                </button>
                @unless ($loop->last)
                    <span class="w-6 sm:w-10 h-px {{ $n < $step ? 'bg-gold' : 'bg-line' }}"></span>
                @endunless
            </li>
        @endforeach
    </ol>

    {{-- ============================================================ 1. Upload --}}
    @if ($step === 1)
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
            <x-ui.card>
                <label class="group relative flex flex-col items-center justify-center gap-3 py-16 px-6 rounded-2xl border-2 border-dashed border-line hover:border-gold hover:bg-gold-tint/40 cursor-pointer transition-colors text-center"
                       x-data="{ drag: false }" x-on:dragover.prevent="drag = true" x-on:dragleave="drag = false" x-on:drop="drag = false"
                       :class="drag ? 'border-gold bg-gold-tint/60' : ''">
                    <input type="file" wire:model="file" accept=".csv,.xlsx,.txt" class="absolute inset-0 opacity-0 cursor-pointer">
                    <span class="w-14 h-14 rounded-2xl bg-white ring-1 ring-line shadow-card text-gold-dark flex items-center justify-center">
                        <x-ui.icon name="file-spreadsheet" :size="24" wire:loading.remove wire:target="file" />
                        <x-ui.icon name="loader" :size="24" class="animate-spin" wire:loading wire:target="file" />
                    </span>
                    <span class="font-display text-[24px] font-semibold text-ink_text-primary" wire:loading.remove wire:target="file">Drop your spreadsheet here</span>
                    <span class="font-display text-[24px] font-semibold text-ink_text-primary" wire:loading wire:target="file">Reading the file...</span>
                    <span class="text-[13px] text-ink_text-secondary">or click to choose a file. Excel (.xlsx) or CSV, up to 1,000 rows.</span>
                </label>
                @error('file') <p class="rj-error justify-center"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
            </x-ui.card>

            <x-ui.card title="How the sheet should look" icon="info">
                <ul class="space-y-3 text-[13px] text-ink_text-secondary">
                    <li class="flex gap-2.5"><x-ui.icon name="check" :size="15" class="text-success shrink-0 mt-0.5" /><span>First row holds the column names. Any order, any spelling. You match them in the next step.</span></li>
                    <li class="flex gap-2.5"><x-ui.icon name="check" :size="15" class="text-success shrink-0 mt-0.5" /><span><span class="font-semibold text-ink_text-primary">Category, purity and weight</span> are needed for every piece.</span></li>
                    <li class="flex gap-2.5"><x-ui.icon name="check" :size="15" class="text-success shrink-0 mt-0.5" /><span>Pieces without a HUID get an internal code automatically.</span></li>
                    <li class="flex gap-2.5"><x-ui.icon name="check" :size="15" class="text-success shrink-0 mt-0.5" /><span>Rows that look like pieces already in stock are flagged and left out unless you tick them.</span></li>
                </ul>
                <x-ui.button variant="soft" size="sm" icon="download" class="mt-5" wire:click="downloadTemplate">Download a sample sheet</x-ui.button>
            </x-ui.card>
        </div>
    @endif

    {{-- ============================================================ 2. Map columns --}}
    @if ($step === 2)
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
            <x-ui.card :padding="false" title="Match your columns" :subtitle="count($rows) . ' rows found in ' . $fileName . '. We guessed the matches. Check them.'" icon="sliders">
                @error('mapping')
                    <div class="flex items-start gap-2.5 mx-5 mt-4 px-4 py-3 rounded-xl bg-danger-bg text-danger text-[13px] font-medium"><x-ui.icon name="alert-triangle" :size="15" class="shrink-0 mt-0.5" />{{ $message }}</div>
                @enderror
                <x-ui.table :headers="['Your column', 'Sample values', ['label' => 'Goes into', 'class' => 'w-[240px]']]">
                    @foreach ($headers as $i => $h)
                        <tr wire:key="map-{{ $i }}">
                            <td class="font-semibold">{{ $h ?: 'Column ' . ($i + 1) }}</td>
                            <td class="text-[12.5px] text-ink_text-secondary">
                                <div class="flex flex-wrap gap-1">
                                    @foreach (collect($rows)->pluck($i)->filter()->take(3) as $sample)
                                        <span class="inline-flex h-6 px-2 items-center rounded-md bg-surface-sunken ring-1 ring-inset ring-line-light max-w-[160px] truncate">{{ $sample }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <select wire:model.live="mapping.{{ $i }}" class="rj-select {{ ($mapping[$i] ?? '') ? 'border-gold-soft bg-gold-tint/40' : '' }}">
                                    <option value="">Ignore this column</option>
                                    @foreach (\App\Livewire\Stock\BulkImport::FIELDS as $field => [$label, $required])
                                        <option value="{{ $field }}">{{ $label }}{{ $required ? ' (required)' : '' }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </x-ui.card>

            <x-ui.card title="When a column is missing" subtitle="Used for blank cells too" icon="sliders" class="xl:sticky xl:top-24">
                <div class="space-y-4">
                    <x-ui.field label="Metal" for="d-metal" error="defaultMetal">
                        <select id="d-metal" wire:model="defaultMetal" class="rj-select">
                            @foreach (\App\Livewire\Stock\ItemForm::METALS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                    </x-ui.field>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field label="Making type" for="d-mt" error="defaultMakingType">
                            <select id="d-mt" wire:model="defaultMakingType" class="rj-select">
                                <option value="flat_per_piece">Per piece</option>
                                <option value="flat_per_gram">Per gram</option>
                                <option value="percentage">Percentage</option>
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Making value" for="d-mv" error="defaultMakingValue">
                            <input id="d-mv" type="number" step="0.01" min="0" wire:model="defaultMakingValue" class="rj-input tabular">
                        </x-ui.field>
                    </div>
                    <x-ui.field label="Packet" for="d-packet" error="defaultPacketId" optional>
                        <select id="d-packet" wire:model="defaultPacketId" class="rj-select">
                            <option value="">Leave unpacked</option>
                            @foreach ($packets as $p)<option value="{{ $p->id }}">{{ $p->code }}{{ $p->box ? ' (' . $p->box->code . ')' : '' }}</option>@endforeach
                        </select>
                    </x-ui.field>
                </div>
                <x-ui.button class="w-full mt-6" iconRight="arrow-right" wire:click="confirmMapping">Check the rows</x-ui.button>
            </x-ui.card>
        </div>
    @endif

    {{-- ============================================================ 3. Review --}}
    @if ($step === 3)
        @php
            $visible = collect($reviewRows)->filter(fn ($r) => match ($reviewFilter) {
                'ready' => ! $r['errors'] && ! $r['duplicate'],
                'duplicate' => ! $r['errors'] && $r['duplicate'],
                'error' => (bool) $r['errors'],
                default => true,
            });
        @endphp

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            @foreach ([
                'all' => ['Rows in file', 'file-spreadsheet', ''],
                'ready' => ['Ready to import', 'check-circle', 'text-success'],
                'duplicate' => ['Possible duplicates', 'copy', 'text-warning'],
                'error' => ['Have problems', 'alert-triangle', 'text-danger'],
            ] as $key => [$name, $icon, $color])
                <button type="button" wire:click="$set('reviewFilter', '{{ $key }}')"
                    class="text-left bg-white border rounded-card p-5 shadow-card transition-[border-color,box-shadow] {{ $reviewFilter === $key ? 'border-gold ring-4 ring-gold/10' : 'border-line-light hover:border-gold-soft' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-[12.5px] font-semibold text-ink_text-secondary">{{ $name }}</span>
                        <x-ui.icon :name="$icon" :size="16" class="{{ $color ?: 'text-ink_text-muted' }}" />
                    </div>
                    <div class="font-display text-[34px] leading-none font-semibold tabular mt-3 {{ $counts[$key] ? $color : 'text-ink_text-muted' }}">{{ $counts[$key] }}</div>
                </button>
            @endforeach
        </div>

        <x-ui.datatable>
            <x-slot:toolbar>
                <span class="text-[13px] text-ink_text-secondary">Showing <span class="font-semibold text-ink_text-primary">{{ $visible->count() }}</span> of {{ $counts['all'] }} rows</span>
                <div class="ml-auto flex gap-2">
                    <x-ui.button variant="ghost" size="sm" wire:click="setAll(true)">Tick all clean rows</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" wire:click="setAll(false)">Untick all</x-ui.button>
                </div>
            </x-slot:toolbar>
            <x-slot:head>
                <th class="w-10 !pr-0"><span class="sr-only">Import</span></th>
                <th class="w-14">Row</th><th>HUID</th><th>Piece</th><th>Metal</th><th class="text-right">Weight</th><th>Making</th><th>Packet</th><th>Check</th>
            </x-slot:head>
            @forelse ($visible as $i => $r)
                <tr wire:key="rv-{{ $i }}" @class(['is-selected' => $r['include'], 'bg-danger-bg/40' => $r['errors']])>
                    <td class="!pr-0">
                        <input type="checkbox" class="rj-checkbox" @checked($r['include']) @disabled($r['errors']) wire:click="toggleInclude({{ $i }})" aria-label="Import row {{ $r['line'] }}">
                    </td>
                    <td class="text-ink_text-muted tabular">{{ $r['line'] }}</td>
                    <td class="rj-code text-[12px]">{{ $r['data']['huid_code'] ?: 'Auto code' }}</td>
                    <td>
                        <div class="font-semibold">{{ $r['data']['category'] ?: '(missing)' }} <span class="font-normal text-ink_text-muted">{{ $r['data']['purity'] }}</span></div>
                        <div class="text-[12px] text-ink_text-muted truncate max-w-[220px]">{{ $r['data']['description'] }}</div>
                    </td>
                    <td>{{ $r['data']['metal'] ? ucfirst($r['data']['metal']) : '?' }}</td>
                    <td class="text-right tabular">{{ is_numeric($r['data']['weight']) ? number_format((float) $r['data']['weight'], 3) . ' g' : $r['data']['weight'] }}</td>
                    <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap tabular">
                        @if ($r['data']['making_type'])
                            {{ $r['data']['making_type'] === 'percentage' ? $r['data']['making_value'] . '%' : '₹' . $r['data']['making_value'] . ($r['data']['making_type'] === 'flat_per_gram' ? ' / g' : ' / pc') }}
                        @else ? @endif
                    </td>
                    <td class="rj-code text-[12px]">{{ $r['data']['packet_code'] ?: ($r['packet_id'] ? 'Default' : '-') }}</td>
                    <td class="max-w-[260px]">
                        @if ($r['errors'])
                            <div class="flex flex-wrap gap-1">
                                @foreach ($r['errors'] as $e)<x-ui.badge tone="danger" size="sm">{{ $e }}</x-ui.badge>@endforeach
                            </div>
                        @elseif ($r['duplicate'])
                            <x-ui.badge tone="warning" size="sm" title="{{ $r['duplicate'] }}"><x-ui.icon name="copy" :size="11" /> {{ \Illuminate\Support\Str::limit($r['duplicate'], 34) }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="success" size="sm">Looks good</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><x-ui.empty-state icon="check-circle" title="No rows in this group" compact /></td></tr>
            @endforelse
        </x-ui.datatable>

        <div class="sticky bottom-4 z-[5] mt-6 flex flex-wrap items-center gap-4 px-5 py-4 rounded-card bg-ink text-white shadow-modal ring-1 ring-black/40">
            <div class="flex-1 min-w-[200px]">
                <div class="text-[14px] font-bold"><span class="text-gold-light tabular">{{ $counts['included'] }}</span> {{ \Illuminate\Support\Str::plural('piece', $counts['included']) }} will be added</div>
                <div class="text-[12.5px] text-ink-dim">Rows with problems are never imported. Duplicates only if you tick them.</div>
            </div>
            <button type="button" wire:click="backTo(2)" class="h-10 px-4 rounded-control text-[13px] font-semibold text-ink-fg hover:text-white ring-1 ring-white/15 hover:bg-white/5">Back to columns</button>
            <x-ui.button icon="check" :disabled="! $counts['included']"
                x-on:click="$dispatch('rj-confirm', { title: 'Add {{ $counts['included'] }} piece(s) to stock?', message: 'Each gets its own record and history. This cannot be undone as a batch.', confirm: 'Import now', action: () => $wire.confirmImport() })">
                Import {{ $counts['included'] }}
            </x-ui.button>
        </div>
    @endif

    {{-- ============================================================ 4. Done --}}
    @if ($step === 4)
        <div class="max-w-[720px] mx-auto">
            <x-ui.card class="text-center !p-10">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-success-bg text-success flex items-center justify-center animate-rise-in">
                    <x-ui.icon name="check" :size="30" />
                </div>
                <h2 class="font-display text-[32px] font-semibold mt-5">{{ count($createdIds) }} {{ \Illuminate\Support\Str::plural('piece', count($createdIds)) }} added to stock</h2>
                <p class="text-[13.5px] text-ink_text-secondary mt-1">Each one has its own history starting today.</p>

                @if ($created->isNotEmpty())
                    <div class="flex flex-wrap justify-center gap-1.5 mt-6">
                        @foreach ($created as $c)
                            <a href="{{ route('stock.items.show', $c) }}" class="inline-flex items-center h-7 px-2.5 rounded-md bg-surface-sunken ring-1 ring-inset ring-line-light rj-code text-[12px] text-ink_text-primary hover:ring-gold-soft">{{ $c->label }}</a>
                        @endforeach
                        @if (count($createdIds) > $created->count())
                            <span class="inline-flex items-center h-7 px-2.5 text-[12px] text-ink_text-muted">and {{ count($createdIds) - $created->count() }} more</span>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap justify-center gap-2.5 mt-8">
                    <x-ui.button icon="gem" :href="route('stock.items')">Go to inventory</x-ui.button>
                    <x-ui.button variant="ghost" icon="upload" wire:click="startOver">Import another file</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    @endif
</div>
