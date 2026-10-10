<div>
    @php $toCounter = $direction === 'to_counter'; @endphp
    <x-ui.page-header title="Vault ↔ Counter" subtitle="Scan what goes out to the counter, or what comes back. Several boxes at once is fine. Confirm once when the tray is done."
        :crumbs="[['label' => 'Movements'], ['label' => 'Vault ↔ Counter']]">
        <x-slot:meta>
            <x-ui.badge tone="gold">{{ now()->format('l, j M') }}</x-ui.badge>
        </x-slot:meta>
    </x-ui.page-header>

    {{-- Direction: one screen, one toggle --}}
    <div class="rj-segment mb-6" role="group" aria-label="Direction">
        @foreach (['to_counter' => ['Send to counter', $stats['sent'] . ' sent today'], 'to_vault' => ['Return to vault', $stats['returned'] . ' returned today']] as $key => [$title, $count])
            <button type="button" @class(['is-active' => $direction === $key]) aria-pressed="{{ $direction === $key ? 'true' : 'false' }}"
                @if ($direction !== $key && count($tray))
                    x-on:click="$dispatch('rj-confirm', { title: 'Switch and empty the tray?', message: 'The {{ count($tray) }} scanned {{ \Illuminate\Support\Str::plural('entry', count($tray)) }} have not been confirmed yet.', confirm: 'Switch', tone: 'danger', action: () => $wire.setDirection('{{ $key }}') })"
                @else
                    wire:click="setDirection('{{ $key }}')"
                @endif>
                {{ $title }} <span class="ml-1.5 text-[11.5px] opacity-70 tabular">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start"
         x-data="{ focusScan() { if (window.matchMedia('(pointer: fine)').matches) this.$nextTick(() => this.$refs.scan && this.$refs.scan.focus()) } }"
         x-init="focusScan()" x-on:scan-ready.window="focusScan()">
        <div class="space-y-6 min-w-0">
            {{-- Scanner --}}
            <x-ui.card :title="$toCounter ? 'Scan what is going to the counter' : 'Scan what is going back to the vault'"
                subtitle="Pieces, packets or whole boxes. HUID, internal code or QR sticker. Paste or scan several codes at once." icon="scan">
                <x-ui.scan-button target="#vault-scan" submit="form" continuous variant="button"
                    :title="$toCounter ? 'Scan for the counter' : 'Scan back to the vault'" label="Scan with camera"
                    class="w-full !h-14 !text-[15px] mb-3 sm:hidden" />
                <form x-on:submit.prevent="const v = $refs.scan.value; $refs.scan.value = ''; if (v.trim()) $wire.scan(v)" class="flex gap-2.5">
                    <div class="relative flex-1 min-w-0">
                        <x-ui.icon name="scan" :size="19" class="absolute left-4 top-1/2 -translate-y-1/2 text-gold pointer-events-none" />
                        <input id="vault-scan" x-ref="scan" type="text" autocomplete="off" aria-label="Scan code"
                            class="rj-input h-14 pl-12 pr-11 sm:pr-28 text-[15px] sm:text-[17px] font-mono tracking-wider" placeholder="Waiting for scan">
                        <span class="hidden sm:inline absolute right-3.5 top-1/2 -translate-y-1/2 text-[11.5px] text-ink_text-muted" wire:loading.remove wire:target="scan">Press Enter</span>
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gold" wire:loading wire:target="scan"><x-ui.icon name="loader" :size="17" class="animate-spin" /></span>
                    </div>
                    <x-ui.scan-button target="#vault-scan" submit="form" continuous variant="button"
                        :title="$toCounter ? 'Scan for the counter' : 'Scan back to the vault'" label="Camera"
                        class="max-sm:!hidden !h-14 !px-5" />
                </form>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-[640px]">
                    @if ($toCounter)
                        <x-ui.field label="Going to" for="vc-location" error="locationId" hint="Moving something already out to a different place records a place change.">
                            <select id="vc-location" wire:model.live="locationId" class="rj-select">
                                @foreach ($locations as $loc)<option value="{{ $loc->id }}">{{ $loc->name }}</option>@endforeach
                            </select>
                        </x-ui.field>
                    @endif
                    <x-movement.done-by />
                </div>

                @if ($feedback)
                    <div wire:key="fb-{{ md5(json_encode($feedback) . count($tray)) }}" @class([
                        'mt-3 flex items-center gap-3 px-3.5 py-2.5 rounded-control text-[13px] animate-rise-in',
                        'bg-success-bg text-success' => $feedback['tone'] === 'success',
                        'bg-danger-bg text-danger' => $feedback['tone'] === 'error',
                        'bg-warning-bg text-warning' => $feedback['tone'] === 'warning',
                        'bg-info-bg text-info' => $feedback['tone'] === 'info',
                    ])>
                        <x-ui.icon :name="['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'][$feedback['tone']]" :size="16" class="shrink-0" />
                        <span class="rj-code">{{ $feedback['code'] }}</span>
                        <span class="text-ink_text-secondary">{{ $feedback['message'] }}</span>
                    </div>
                @else
                    <p class="rj-help">A scanner types the code and presses Enter for you, so you can keep scanning without touching the screen.</p>
                @endif
            </x-ui.card>

            {{-- Tray --}}
            <x-ui.card :padding="false" :title="$toCounter ? 'Tray for the counter' : 'Tray for the vault'"
                :subtitle="count($tray) ? count($tray) . ' ' . \Illuminate\Support\Str::plural('entry', count($tray)) . ($trayWeight ? ' · ' . number_format($trayWeight, 3) . ' g in loose pieces' : '') : 'Nothing is recorded until you confirm'"
                icon="{{ $toCounter ? 'arrow-right' : 'archive' }}">
                <x-slot:actions>
                    @if (count($tray))
                        <x-ui.button variant="ghost" size="sm" wire:click="clearTray">Empty tray</x-ui.button>
                    @endif
                </x-slot:actions>

                @if (count($tray))
                    <ul class="divide-y divide-line-light max-h-[420px] overflow-y-auto">
                        @foreach ($tray as $i => $t)
                            <li wire:key="tray-{{ $t['type'] }}-{{ $t['id'] }}" class="flex items-center gap-3.5 px-5 py-3 {{ $i === 0 ? 'animate-rise-in' : '' }}">
                                <span class="w-8 h-8 shrink-0 rounded-lg bg-surface-muted text-ink_text-secondary flex items-center justify-center">
                                    <x-ui.icon :name="['item' => 'gem', 'packet' => 'package', 'box' => 'archive'][$t['type']]" :size="15" />
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rj-code text-ink_text-primary">{{ $t['code'] }}</span>
                                        @if ($t['warning'])<x-ui.badge tone="warning" size="sm">{{ $t['warning'] }}</x-ui.badge>@endif
                                        @if (! empty($t['placeChange']))<x-ui.badge tone="info" size="sm">Place change</x-ui.badge>@endif
                                    </div>
                                    <div class="text-[12.5px] text-ink_text-muted truncate">{{ $t['detail'] }}</div>
                                </div>
                                <button type="button" wire:click="removeFromTray({{ $i }})" aria-label="Remove {{ $t['code'] }}"
                                    class="w-8 h-8 shrink-0 rounded-lg text-ink_text-muted hover:text-danger hover:bg-danger-bg flex items-center justify-center">
                                    <x-ui.icon name="x" :size="15" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex flex-wrap items-center gap-3 px-5 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                        @if (! $toCounter && $missingCount)
                            <span class="flex items-center gap-1.5 text-[12.5px] font-semibold text-warning">
                                <x-ui.icon name="alert-triangle" :size="14" /> {{ $missingCount }} still on the counter not scanned
                            </span>
                        @endif
                        @php
                            $confirmJs = ! $toCounter && $missingCount
                                ? "\$dispatch('rj-confirm', { title: 'Return with {$missingCount} missing?', message: '{$missingCount} still recorded on the counter were not scanned back. They will stay listed as on the counter.', confirm: 'Return anyway', action: () => \$wire.confirmMove() })"
                                : '$wire.confirmMove()';
                        @endphp
                        <x-ui.button size="lg" class="ml-auto" :icon="$toCounter ? 'arrow-right' : 'archive'" target="confirmMove" x-on:click="{{ $confirmJs }}">
                            {{ $toCounter ? 'Send ' . count($tray) . ' to ' . ($locations->firstWhere('id', $locationId)->name ?? 'Counter') : 'Return ' . count($tray) . ' to Vault' }}
                        </x-ui.button>
                    </div>
                @else
                    <x-ui.empty-state icon="scan" title="The tray is empty"
                        :message="$toCounter ? 'Scan each piece, packet or box as it leaves the vault.' : 'Scan everything left unsold. The list on the right shows what is still expected back.'" compact />
                @endif
            </x-ui.card>
        </div>

        {{-- Right: what is on the counter now --}}
        <x-ui.card :padding="false" title="STILL ON COUNTER"
            :subtitle="$boxesOut . ' ' . \Illuminate\Support\Str::plural('box', $boxesOut) . ' out · ' . $expectedCount . ' ' . \Illuminate\Support\Str::plural('entry', $expectedCount) . ' in all'" icon="grid" class="xl:sticky xl:top-24">
            @if ($byUser->isNotEmpty())
                <div class="flex flex-wrap gap-1.5 px-5 py-3 border-b border-line-light">
                    @foreach ($byUser as $name => $n)
                        <x-ui.badge tone="neutral" size="sm">{{ $name ?: 'Unknown' }} · {{ $n }}</x-ui.badge>
                    @endforeach
                </div>
            @endif
            @if (! $toCounter && $expectedCount)
                <div class="px-5 py-3.5 border-b border-line-light">
                    @php $done = $expectedCount - $missingCount; @endphp
                    <div class="flex items-baseline justify-between text-[12.5px] mb-2">
                        <span class="text-ink_text-secondary">Scanned back</span>
                        <span class="font-display text-[22px] leading-none font-semibold tabular">{{ $done }}<span class="text-ink_text-muted text-[15px]"> / {{ $expectedCount }}</span></span>
                    </div>
                    <div class="h-1.5 rounded-full bg-surface-muted overflow-hidden">
                        <div class="h-full gold-sheen transition-[width] duration-300" style="width: {{ $expectedCount ? round($done / $expectedCount * 100) : 0 }}%"></div>
                    </div>
                </div>
            @endif
            <ul class="divide-y divide-line-light max-h-[560px] overflow-y-auto">
                @forelse ($counterRows->sortBy('scanned') as $r)
                    <li wire:key="counter-{{ $r['type'] }}-{{ $r['model']?->id }}" class="flex items-center gap-3 px-5 py-3 {{ $r['sold'] ? 'opacity-60' : '' }}">
                        @if (! $toCounter && ! $r['sold'])
                            <span @class([
                                'w-6 h-6 shrink-0 rounded-full flex items-center justify-center',
                                'bg-success-bg text-success' => $r['scanned'],
                                'ring-1 ring-inset ring-line-strong text-transparent' => ! $r['scanned'],
                            ])><x-ui.icon name="check" :size="12" /></span>
                        @else
                            <x-ui.icon :name="['item' => 'gem', 'packet' => 'package', 'box' => 'archive'][$r['type']]" :size="15" class="text-ink_text-muted shrink-0" />
                        @endif
                        <div class="flex-1 min-w-0">
                            @if ($r['url'])
                                <a href="{{ $r['url'] }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $r['code'] }}</a>
                            @else
                                <span class="rj-code">{{ $r['code'] }}</span>
                            @endif
                            <div class="text-[12px] text-ink_text-muted truncate">{{ $r['place'] ? $r['place'] . ' · ' : '' }}{{ $r['detail'] }}{{ $r['by'] ? ' · ' . $r['by'] : '' }}</div>
                            @if ($r['soldInside'])
                                <x-ui.badge tone="warning" size="sm" class="mt-1">{{ $r['soldInside'] }} sold inside</x-ui.badge>
                            @endif
                        </div>
                        @if ($r['sold'])
                            <x-ui.badge size="sm">Sold</x-ui.badge>
                        @else
                            <span class="text-[11.5px] text-ink_text-muted text-right leading-tight whitespace-nowrap">
                                {{ $r['since']->isToday() ? $r['since']->format('g:i a') : $r['since']->format('j M') }}
                                @unless ($r['since']->isToday())<br><span class="text-warning font-semibold">not today</span>@endunless
                            </span>
                            <x-ui.button size="xs" variant="secondary" icon="archive" wire:click="returnNow('{{ $r['type'] }}', {{ $r['model']?->id }})" target="returnNow" title="Return to the vault now">Return</x-ui.button>
                        @endif
                    </li>
                @empty
                    <li><x-ui.empty-state icon="archive" title="Everything is in the vault" message="Nothing is recorded as on the counter right now." compact /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>

    {{-- Today's log: out and in side by side --}}
    <x-ui.card :padding="false" title="Today's log" :subtitle="$pairs->count() . ' ' . \Illuminate\Support\Str::plural('entry', $pairs->count()) . ' between the vault and the counter'" icon="history" class="mt-6">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('movements.log'))
                <x-ui.button variant="ghost" size="sm" iconRight="arrow-right" :href="route('movements.log', ['type' => 'vault'])">Full log</x-ui.button>
            @endif
        </x-slot:actions>
        @if ($pairs->isEmpty())
            <x-ui.empty-state icon="clock" title="No movements yet today" message="Confirmed trays show up here with who moved what, and when." compact />
        @else
            <div class="overflow-x-auto">
                <x-ui.table :headers="['What', 'Out of vault', 'Back in vault']">
                    @foreach ($pairs as $row)
                        <tr wire:key="pair-{{ $row['key'] }}">
                            <td>
                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $row['code'] }}</a>
                                @else
                                    <span class="rj-code">{{ $row['code'] }}</span>
                                @endif
                                <span class="text-[12.5px] text-ink_text-muted ml-2">{{ $row['detail'] }}</span>
                            </td>
                            <td class="whitespace-nowrap tabular">
                                @if ($row['out']) {{ $row['out']->created_at->format('g:i a') }} <span class="text-ink_text-muted">· {{ $row['out']->user->name ?? '-' }}</span> @else <span class="text-ink_text-muted">-</span> @endif
                            </td>
                            <td class="whitespace-nowrap tabular">
                                @if ($row['in']) {{ $row['in']->created_at->format('g:i a') }} <span class="text-ink_text-muted">· {{ $row['in']->user->name ?? '-' }}</span> @else <span class="text-ink_text-muted">-</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif
    </x-ui.card>
</div>
