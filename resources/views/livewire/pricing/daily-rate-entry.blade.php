<div>
    <x-ui.page-header title="Daily Rate Entry" subtitle="Deliberately minimal — today's rates, and when they were last touched."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Daily Rate Entry']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="history" :href="route('pricing.rates.history')">Rate history</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach (\App\Livewire\Pricing\DailyRateEntry::METALS as $metal)
            <x-ui.stat-card icon="coins" :label="ucfirst($metal).' — ₹/g'"
                :value="$last[$metal] ? number_format($last[$metal]->rate, 2) : '—'"
                :hint="$last[$metal]?->created_at?->diffForHumans() ?? 'never entered'" />
        @endforeach
    </div>

    @if ($zeroWarning)
        <div class="mb-4 flex items-start gap-3 px-4 py-3 rounded-control bg-warning-bg text-warning text-[13px]">
            <x-ui.icon name="alert-triangle" :size="16" class="shrink-0 mt-0.5" /> {{ $zeroWarning }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card title="Enter today's rates" subtitle="One entry per metal, logged with the time it was set." icon="coins">
            <form wire:submit="save">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach (\App\Livewire\Pricing\DailyRateEntry::METALS as $metal)
                        <x-ui.field :label="ucfirst($metal).' rate (₹/g)'" :for="'rate-'.$metal" :error="'rates.'.$metal"
                            :hint="'Last updated: '.($last[$metal]?->created_at?->diffForHumans() ?? 'never')">
                            <div class="rj-input-icon">
                                <x-ui.icon name="coins" :size="16" />
                                <input id="rate-{{ $metal }}" type="number" step="0.01" wire:model="rates.{{ $metal }}"
                                    class="rj-input tabular @error('rates.'.$metal) is-invalid @enderror">
                            </div>
                        </x-ui.field>
                    @endforeach
                </div>

                <x-ui.button type="submit" variant="primary" target="save" icon="check" class="w-full mt-6">Save Today's Rates</x-ui.button>
            </form>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Right now" icon="clock">
                <dl class="rj-dl">
                    @foreach (\App\Livewire\Pricing\DailyRateEntry::METALS as $metal)
                        <div>
                            <dt>{{ ucfirst($metal) }}</dt>
                            <dd class="tabular">{{ $last[$metal] ? '₹'.number_format($last[$metal]->rate, 2) : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Why this reprices everything
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    Price is never stored on an item — it's calculated live from the latest rate here. Saving updates the whole catalogue instantly, with nothing to individually re-price.
                </p>
            </div>
        </aside>
    </div>
</div>
