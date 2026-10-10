<div>
    <x-ui.page-header title="Daily Rate Entry" subtitle="Today's rate for each metal and carat, per gram. Each is entered separately."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Daily Rate Entry']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="history" :href="route('pricing.rates.history')">Rate history</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($zeroWarning)
        <div class="mb-4 flex items-start gap-3 px-4 py-3 rounded-control bg-warning-bg text-warning text-[13px]">
            <x-ui.icon name="alert-triangle" :size="16" class="shrink-0 mt-0.5" /> {{ $zeroWarning }}
        </div>
    @endif

    <form wire:submit="save">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
            @foreach ($carats as $metal => $list)
                <x-ui.card :title="ucfirst($metal)" subtitle="₹ per gram" icon="coins">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($list as $carat)
                            <x-ui.field :label="$carat" :for="'rate-' . $metal . '-' . $loop->index" :error="'rates.' . $metal . '.' . \App\Livewire\Pricing\DailyRateEntry::key($carat)"
                                :hint="'Last set: ' . ($last[$metal][$carat]?->created_at?->diffForHumans() ?? 'never') . ($last[$metal][$carat] && (float) $last[$metal][$carat]->rate <= 0 ? ' · hidden from display' : '')">
                                <div class="rj-input-icon">
                                    <x-ui.icon name="coins" :size="16" />
                                    <input id="rate-{{ $metal }}-{{ $loop->index }}" type="number" step="0.01" min="0" wire:model="rates.{{ $metal }}.{{ \App\Livewire\Pricing\DailyRateEntry::key($carat) }}"
                                        class="rj-input tabular @error('rates.' . $metal . '.' . \App\Livewire\Pricing\DailyRateEntry::key($carat)) is-invalid @enderror">
                                </div>
                            </x-ui.field>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card title="Exchange deductions" subtitle="Percent taken off old gold and silver, by metal and carat" icon="scale" class="mt-6">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                @foreach ($carats as $metal => $list)
                    <div>
                        <div class="text-[12px] font-bold uppercase tracking-wide text-ink_text-muted mb-2">{{ ucfirst($metal) }}</div>
                        <div class="space-y-2">
                            @foreach ($list as $carat)
                                <div class="flex items-center gap-2"><span class="w-[64px] text-[13px] text-ink_text-secondary">{{ $carat }}</span>
                                    <input type="number" step="0.01" min="0" max="100" wire:model="deductions.{{ $metal }}.{{ \App\Livewire\Pricing\DailyRateEntry::key($carat) }}" class="rj-input tabular" aria-label="Deduction for {{ ucfirst($metal) }} {{ $carat }}"><span class="text-[13px] text-ink_text-muted">%</span></div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.button type="submit" variant="primary" target="save" icon="check" class="mt-6">Save today's rates</x-ui.button>
        <p class="rj-help mt-3">Price is never stored on a piece, so saving reprices the whole catalogue at once. A rate of 0 takes that carat off the website display.</p>
    </form>
</div>
