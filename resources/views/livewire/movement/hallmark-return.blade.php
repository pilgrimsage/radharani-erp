<div>
    <x-ui.page-header title="Hallmarking Return" subtitle="Enter the HUID the centre gave, or use the shop's own code. Note who tagged it and the weight lost."
        :crumbs="[['label' => 'Movements'], ['label' => 'Hallmarking Return']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="truck" :href="route('movements.hallmark-dispatch')">Send pieces</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[380px_minmax(0,1fr)] gap-6 items-start">
        <x-ui.card :padding="false" title="At hallmarking" :subtitle="$open->count() . ' ' . \Illuminate\Support\Str::plural('piece', $open->count()) . ' waiting to come back'" icon="shield-check" class="xl:sticky xl:top-24">
            <div class="p-3.5 border-b border-line-light">
                <x-ui.search-input scan wire:model.live.debounce.300ms="search" placeholder="Code, category or centre" />
            </div>
            <ul class="divide-y divide-line-light max-h-[620px] overflow-y-auto">
                @forelse ($open as $m)
                    <li wire:key="h-{{ $m->id }}">
                        <button type="button" wire:click="select({{ $m->id }})" @class([
                            'w-full text-left flex items-start gap-3 px-4 py-3.5 transition-colors',
                            'bg-gold-tint shadow-[inset_3px_0_0_#B8862D]' => $selectedId === $m->id,
                            'hover:bg-surface-sunken' => $selectedId !== $m->id,
                        ])>
                            <x-movement.metal-dot :metal="$m->item?->metal" class="mt-1.5" />
                            <span class="flex-1 min-w-0">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="rj-code text-ink_text-primary">{{ $m->item?->label }}</span>
                                    <x-movement.due :date="$m->expected_return" />
                                </span>
                                <span class="block text-[12px] text-ink_text-muted truncate">{{ $m->item?->category }} · {{ $m->item?->purity }} · <span class="tabular">{{ number_format((float) $m->weight_at_dispatch, 3) }} g</span></span>
                                <span class="block text-[12px] text-ink_text-secondary">{{ $m->counterparty }} · sent {{ $m->created_at->format('j M') }}</span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="check-circle" :title="$search ? 'Nothing matches' : 'Nothing at hallmarking'" :message="$search ? 'Try another search.' : 'Every piece sent for hallmarking has come back.'" compact /></li>
                @endforelse
            </ul>
        </x-ui.card>

        @if (! $selected)
            <x-ui.card>
                <x-ui.empty-state icon="corner-down-right" title="Pick the piece that came back" message="Choose it from the list. You will enter its HUID, who tagged it, and the weight." />
            </x-ui.card>
        @else
            <x-ui.card :padding="false">
                <div class="flex flex-wrap items-start gap-4 px-5 sm:px-6 py-5 border-b border-line-light">
                    <span class="w-12 h-12 shrink-0 rounded-xl bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft/70 flex items-center justify-center">
                        <x-ui.icon name="shield-check" :size="20" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('stock.items.show', $selected->item) }}" class="font-display text-[28px] leading-tight font-semibold text-ink_text-primary hover:text-gold-dark">{{ $selected->item->label }}</a>
                            <x-movement.due :date="$selected->expected_return" size="md" />
                        </div>
                        <div class="text-[13px] text-ink_text-secondary">{{ $selected->item->category }} · {{ ucfirst($selected->item->metal ?? '') }} {{ $selected->item->purity }}</div>
                    </div>
                    <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="clearSelection" aria-label="Close" />
                </div>

                <dl class="rj-dl sm:grid-cols-4 px-5 sm:px-6 py-4 bg-surface-sunken border-b border-line-light">
                    <div><dt>Centre</dt><dd>{{ $selected->counterparty ?: '-' }}</dd></div>
                    <div><dt>Sent</dt><dd>{{ $selected->created_at->format('j M Y') }}</dd></div>
                    <div><dt>By</dt><dd>{{ $selected->user?->name ?? '-' }}</dd></div>
                    <div><dt>Current code</dt><dd class="rj-code">{{ $selected->item->huid_code ?: ($selected->item->internal_code ?: 'None') }}</dd></div>
                </dl>

                <form wire:submit="confirm">
                    <div class="p-5 sm:p-6 space-y-6">
                        <section>
                            <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Identification</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                @foreach ([
                                    'huid' => ['shield-check', 'HUID from the centre', 'The 6-character code they engraved'],
                                    'internal' => ['hash', $selected->item->huid_code ? 'No new HUID given' : "Shop's own code", match (true) {
                                        (bool) $selected->item->huid_code => 'Keeps its HUID ' . $selected->item->huid_code,
                                        (bool) $selected->item->internal_code => 'Keeps ' . $selected->item->internal_code,
                                        default => 'A 5-character code is created',
                                    }],
                                ] as $key => [$icon, $title, $text])
                                    <label @class([
                                        'relative flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors',
                                        'border-gold bg-gold-tint shadow-focus' => $idMode === $key,
                                        'border-line hover:border-line-strong' => $idMode !== $key,
                                    ])>
                                        <input type="radio" wire:model.live="idMode" value="{{ $key }}" class="sr-only">
                                        <x-ui.icon :name="$icon" :size="17" class="mt-0.5 shrink-0 {{ $idMode === $key ? 'text-gold-dark' : 'text-ink_text-muted' }}" />
                                        <span>
                                            <span class="block text-[13.5px] font-semibold text-ink_text-primary">{{ $title }}</span>
                                            <span class="block text-[12px] text-ink_text-secondary">{{ $text }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @if ($idMode === 'huid')
                                    <x-ui.field label="HUID" for="hr-huid" error="huidCode">
                                        <div class="relative">
                                        <input id="hr-huid" type="text" wire:model="huidCode" maxlength="6" autofocus autocomplete="off"
                                            class="rj-input h-11 pr-12 rj-code text-[15px] tracking-[0.2em] uppercase @error('huidCode') is-invalid @enderror" placeholder="AB12CD">
                                        <x-ui.scan-button target="#hr-huid" title="Scan the HUID" class="absolute right-1.5 top-1/2 -translate-y-1/2 !w-8 !h-8" />
                                        </div>
                                    </x-ui.field>
                                @endif
                                <x-ui.field label="Tagged by" for="hr-tagger" error="taggedBy" hint="Staff member or the person at the centre">
                                    <input id="hr-tagger" type="text" list="hr-taggers" wire:model="taggedBy" maxlength="100" autocomplete="off"
                                        class="rj-input {{ $idMode === 'huid' ? 'h-11' : '' }} @error('taggedBy') is-invalid @enderror">
                                    <datalist id="hr-taggers">@foreach ($taggers as $t)<option value="{{ $t }}"></option>@endforeach</datalist>
                                </x-ui.field>
                            </div>
                        </section>

                        <section class="pt-6 border-t border-line-light">
                            <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Weight</h3>
                            <x-movement.weight-fields :sent="$sentWeight" :diff="$scaleDiff" />
                        </section>

                        <x-movement.done-by />

                        <x-ui.field label="Note" for="hr-note" error="note" optional>
                            <input id="hr-note" type="text" wire:model="note" maxlength="255" class="rj-input" placeholder="e.g. certificate number">
                        </x-ui.field>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                        <div class="flex items-center gap-2 text-[12.5px] text-ink_text-secondary">Afterwards: <x-ui.status status="pending_review" size="sm" /></div>
                        <x-ui.button type="submit" size="lg" icon="check" target="confirm">Confirm return</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
</div>
