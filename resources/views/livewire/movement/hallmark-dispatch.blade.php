<div>
    <x-ui.page-header title="Hallmarking Dispatch" subtitle="Send pieces to a hallmarking centre. Scan or search each one, then confirm once."
        :crumbs="[['label' => 'Movements'], ['label' => 'Hallmarking Dispatch']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="corner-down-right" :href="route('movements.hallmark-return')">Record a return</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_400px] gap-6 items-start">
        <x-ui.card :padding="false" title="Pieces going for hallmarking" subtitle="Each piece is marked as out until the centre sends it back." icon="shield-check">
            <form wire:submit="submit">
                <div class="p-5 sm:p-6 space-y-6">
                    <x-movement.item-picker :items="$basketItems" :results="$pickResults" label="Pieces" />

                    @if ($suggested->isNotEmpty())
                        <div>
                            <div class="text-[12px] font-semibold text-ink_text-secondary mb-2">Gold over 2 g without a HUID</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($suggested as $s)
                                    <button type="button" wire:click="addToBasket({{ $s->id }})" wire:key="sug-{{ $s->id }}"
                                        class="h-8 pl-2.5 pr-3 rounded-lg text-[12.5px] font-semibold ring-1 ring-inset ring-line bg-white hover:ring-gold-soft hover:bg-gold-tint/50 inline-flex items-center gap-2 transition-colors">
                                        <x-ui.icon name="plus" :size="12" class="text-gold-dark" />
                                        <span class="rj-code">{{ $s->label }}</span>
                                        <span class="text-ink_text-muted font-medium">{{ $s->category }} · {{ number_format($s->weight, 2) }} g</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-6 border-t border-line-light">
                        <x-ui.field label="Hallmarking centre" for="hd-centre" error="centreId">
                            <select id="hd-centre" wire:model="centreId" class="rj-select @error('centreId') is-invalid @enderror">
                                <option value="">Choose a centre</option>
                                @foreach ($centres as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if ($centres->isEmpty())
                                <p class="rj-help">No centres yet. Add one under Purchases, Vendors, with the type Hallmark centre.</p>
                            @endif
                        </x-ui.field>
                        <x-ui.field label="Expected back" for="hd-due" error="expectedReturn">
                            <input id="hd-due" type="date" wire:model.live="expectedReturn" class="rj-input tabular">
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach ([1, 2, 3, 7] as $d)
                                    @php $val = today()->addDays($d)->toDateString(); @endphp
                                    <button type="button" wire:click="$set('expectedReturn', '{{ $val }}')"
                                        class="h-7 px-2.5 rounded-md text-[12px] font-semibold ring-1 ring-inset transition-colors
                                        {{ $expectedReturn === $val ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $d === 1 ? 'Tomorrow' : $d . ' days' }}</button>
                                @endforeach
                            </div>
                        </x-ui.field>
                    </div>

                    <x-movement.done-by />

                    <x-ui.field label="Note" for="hd-note" error="note" optional>
                        <input id="hd-note" type="text" wire:model="note" maxlength="255" class="rj-input" placeholder="e.g. challan number">
                    </x-ui.field>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                    <p class="text-[12.5px] text-ink_text-secondary">
                        <span class="font-semibold text-ink_text-primary tabular">{{ $basketItems->count() }}</span> {{ \Illuminate\Support\Str::plural('piece', $basketItems->count()) }}
                        @if ($basketItems->isNotEmpty()) · <span class="tabular">{{ number_format($basketItems->sum('weight'), 3) }} g</span> weighed out @endif
                    </p>
                    <x-ui.button type="submit" size="lg" icon="truck" target="submit">Confirm dispatch</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card :padding="false" title="At hallmarking now" :subtitle="$atCentre->count() . ' ' . \Illuminate\Support\Str::plural('piece', $atCentre->count()) . ($overdue ? ', ' . $overdue . ' overdue' : '')" icon="clock" class="xl:sticky xl:top-24">
            <ul class="divide-y divide-line-light max-h-[640px] overflow-y-auto">
                @forelse ($atCentre as $m)
                    <li class="flex items-start gap-3 px-5 py-3.5">
                        <x-movement.metal-dot :metal="$m->item?->metal" class="mt-1.5" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('stock.items.show', $m->trackable_id) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">{{ $m->item?->label }}</a>
                                <x-movement.due :date="$m->expected_return" />
                            </div>
                            <div class="text-[12px] text-ink_text-muted truncate">{{ $m->item?->category }} · <span class="tabular">{{ number_format((float) $m->weight_at_dispatch, 3) }} g</span></div>
                            <div class="text-[12px] text-ink_text-secondary">{{ $m->counterparty }} · sent {{ $m->created_at->format('j M') }}</div>
                        </div>
                    </li>
                @empty
                    <li><x-ui.empty-state icon="check-circle" title="Nothing at hallmarking" message="Pieces you send show up here until they come back." compact /></li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>
</div>
