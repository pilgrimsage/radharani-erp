<div>
    <x-ui.page-header title="Stock audit" subtitle="Take a box, scan every piece in it, and keep the result."
        :crumbs="[['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Stock audit']]">
        @if ($box)
            <x-slot:actions><x-ui.button variant="secondary" wire:click="cancel">Cancel audit</x-ui.button></x-slot:actions>
        @endif
    </x-ui.page-header>

    @if (! $box)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
            <x-ui.card :padding="false" title="Choose a box to audit" subtitle="The last audit date is shown for each box" icon="archive">
                <ul class="divide-y divide-line-light max-h-[520px] overflow-y-auto">
                    @forelse ($boxes as $b)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="ab-{{ $b->id }}">
                            <span class="flex-1 min-w-0">
                                <span class="rj-code text-ink_text-primary">{{ $b->code }}</span>
                                <span class="block text-[12.5px] text-ink_text-muted">
                                    {{ $b->audits_max_created_at ? 'Last audited ' . \Illuminate\Support\Carbon::parse($b->audits_max_created_at)->format('j M Y, g:i a') : 'Never audited' }}
                                </span>
                            </span>
                            <x-ui.button size="sm" icon="scan" wire:click="start({{ $b->id }})">Start</x-ui.button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="archive" title="No boxes" compact /></li>
                    @endforelse
                </ul>
            </x-ui.card>

            <x-ui.card :padding="false" title="Past audits" icon="history">
                <ul class="divide-y divide-line-light">
                    @forelse ($history as $a)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="ah-{{ $a->id }}">
                            <span class="flex-1 min-w-0">
                                <span class="rj-code">{{ $a->box->code }}</span>
                                <span class="block text-[12.5px] text-ink_text-muted">{{ $a->created_at->format('j M Y, g:i a') }} · {{ $a->user->name }}</span>
                            </span>
                            @if ($a->clean)
                                <x-ui.badge tone="success" size="sm">Matched</x-ui.badge>
                            @else
                                <x-ui.badge tone="warning" size="sm">{{ $a->missing_count }} missing · {{ $a->extra_count }} extra</x-ui.badge>
                            @endif
                            <x-ui.button variant="ghost" size="sm" wire:click="view({{ $a->id }})">Details</x-ui.button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="history" title="No audits yet" message="Saved audits show up here." compact /></li>
                    @endforelse
                </ul>
            </x-ui.card>
        </div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start"
             x-data="{ focusScan() { if (window.matchMedia('(pointer: fine)').matches) this.$nextTick(() => this.$refs.scan && this.$refs.scan.focus()) } }"
             x-init="focusScan()" x-on:scan-ready.window="focusScan()">
            <div class="space-y-6 min-w-0">
                <x-ui.card :title="'Auditing ' . $box->code" subtitle="Scan each piece in the box. HUID, internal code or QR sticker." icon="scan">
                    <form x-on:submit.prevent="const v = $refs.scan.value; $refs.scan.value = ''; if (v.trim()) $wire.scan(v)" class="flex gap-2.5">
                        <input id="audit-scan" x-ref="scan" type="text" autocomplete="off" aria-label="Scan code" placeholder="Waiting for scan"
                            class="rj-input h-14 flex-1 text-[17px] font-mono tracking-wider">
                        <x-ui.scan-button target="#audit-scan" submit="form" continuous variant="button" title="Scan" label="Camera" class="!h-14 !px-5" />
                    </form>
                    @if ($feedback)
                        <div wire:key="afb-{{ md5(json_encode($feedback) . count($present) . count($extra)) }}" @class([
                            'mt-3 flex items-center gap-3 px-3.5 py-2.5 rounded-control text-[13px]',
                            'bg-success-bg text-success' => $feedback['tone'] === 'success',
                            'bg-warning-bg text-warning' => $feedback['tone'] === 'warning',
                            'bg-info-bg text-info' => $feedback['tone'] === 'info',
                        ])>
                            <span class="rj-code">{{ $feedback['code'] }}</span>
                            <span class="text-ink_text-secondary">{{ $feedback['message'] }}</span>
                        </div>
                    @endif
                </x-ui.card>

                @if (count($extra))
                    <x-ui.card :padding="false" title="Extra" subtitle="Scanned, but not expected in this box" icon="alert-triangle">
                        <ul class="divide-y divide-line-light">
                            @foreach ($extra as $i => $e)
                                <li class="flex items-center gap-3 px-5 py-2.5" wire:key="ax-{{ $i }}-{{ $e['code'] }}">
                                    <span class="rj-code flex-1">{{ $e['code'] }}</span>
                                    <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="unmarkExtra({{ $i }})" aria-label="Remove {{ $e['code'] }}" />
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                <x-ui.card :padding="false" title="Still to find" :subtitle="$missing->count() . ' of ' . $expected->count() . ' not scanned yet'" icon="search">
                    <ul class="divide-y divide-line-light max-h-[360px] overflow-y-auto">
                        @forelse ($missing as $m)
                            <li class="flex items-center gap-3 px-5 py-2.5" wire:key="am-{{ $m->id }}">
                                <span class="rj-code">{{ $m->label }}</span>
                                <span class="text-[12.5px] text-ink_text-muted">{{ $m->category }} · {{ number_format($m->weight, 3) }} g</span>
                            </li>
                        @empty
                            <li><x-ui.empty-state icon="check-circle" title="All scanned" message="Every expected piece has been scanned." compact /></li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>

            <x-ui.card class="xl:sticky xl:top-24" title="This audit" icon="clipboard">
                <dl class="rj-dl">
                    <div><dt>Expected</dt><dd class="tabular">{{ $expected->count() }}</dd></div>
                    <div><dt>Present</dt><dd class="tabular text-success font-semibold">{{ count($present) }}</dd></div>
                    <div><dt>Missing so far</dt><dd class="tabular {{ $missing->count() ? 'text-warning font-semibold' : '' }}">{{ $missing->count() }}</dd></div>
                    <div><dt>Extra</dt><dd class="tabular {{ count($extra) ? 'text-warning font-semibold' : '' }}">{{ count($extra) }}</dd></div>
                </dl>
                <x-ui.field label="Note" for="audit-note" optional class="mt-4">
                    <input id="audit-note" type="text" wire:model="note" maxlength="255" class="rj-input">
                </x-ui.field>
                @php
                    $js = $missing->count() ? "\$dispatch('rj-confirm', { title: 'Save with {$missing->count()} missing?', message: 'Pieces not scanned are recorded as missing.', confirm: 'Save audit', action: () => \$wire.save() })" : '$wire.save()';
                @endphp
                <x-ui.button class="w-full mt-4" size="lg" icon="check" target="save" x-on:click="{{ $js }}">Save audit</x-ui.button>
            </x-ui.card>
        </div>
    @endif

    @if ($viewing)
        <x-ui.modal wire:model="showView" :title="'Audit of ' . $viewing->box->code" :subtitle="$viewing->created_at->format('j M Y, g:i a') . ' · ' . $viewing->user->name" icon="clipboard" max-width="lg">
            <p class="text-[13px] text-ink_text-secondary mb-3">{{ $viewing->expected_count }} expected · {{ $viewing->present_count }} present · {{ $viewing->missing_count }} missing · {{ $viewing->extra_count }} extra</p>
            @if ($viewing->note)<p class="text-[13px] mb-3">{{ $viewing->note }}</p>@endif
            <ul class="divide-y divide-line-light max-h-[320px] overflow-y-auto">
                @forelse ($viewing->lines->where('result', '!=', 'present') as $l)
                    <li class="flex items-center gap-3 py-2"><span class="rj-code flex-1">{{ $l->code }}</span>
                        <x-ui.badge size="sm" :tone="$l->result === 'missing' ? 'danger' : 'warning'">{{ ucfirst($l->result) }}</x-ui.badge></li>
                @empty
                    <li class="py-3 text-[13px] text-success">Everything matched.</li>
                @endforelse
            </ul>
            <x-slot:footer><x-ui.button variant="secondary" x-on:click="show = false">Close</x-ui.button></x-slot:footer>
        </x-ui.modal>
    @endif
</div>
