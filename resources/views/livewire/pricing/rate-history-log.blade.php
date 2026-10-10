<div>
    <x-ui.page-header title="Rate History Log" subtitle="Every past rate entry, most recent first."
        :crumbs="[['label' => 'Pricing & Rates', 'href' => route('pricing.rates')], ['label' => 'Rate History']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="coins" :href="route('pricing.rates')">Enter today's rates</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach (\App\Livewire\Pricing\DailyRateEntry::METALS as $metal)
            <x-ui.stat-card icon="coins" :label="ucfirst($metal).' — ₹/g'"
                :value="$latest[$metal] ? number_format($latest[$metal]->rate, 2) : '—'"
                :hint="$latest[$metal]?->created_at?->diffForHumans() ?? 'no entries yet'" />
        @endforeach
    </div>

    <x-ui.datatable :paginator="$rates">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search by source" class="w-full sm:w-[260px]" />
            <div class="rj-segment">
                @foreach (['' => 'All'] + collect(\App\Livewire\Pricing\DailyRateEntry::METALS)->mapWithKeys(fn ($m) => [$m => ucfirst($m)])->all() as $value => $name)
                    <button type="button" wire:click="$set('metal', '{{ $value }}')" class="{{ $metal === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date & time</x-ui.th>
            <x-ui.th field="metal" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Metal and carat</x-ui.th>
            <x-ui.th field="rate" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Rate (₹/g)</x-ui.th>
            <x-ui.th field="source" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Source</x-ui.th>
            <x-ui.th>By</x-ui.th>
        </x-slot:head>

        @forelse ($rates as $r)
            <tr wire:key="rate-{{ $r->id }}">
                <td class="text-ink_text-primary whitespace-nowrap">{{ $r->created_at->format('d M Y, g:i a') }}</td>
                <td><x-ui.badge :tone="$r->metal === 'gold' ? 'gold' : 'neutral'">{{ strtoupper($r->metal) }}{{ $r->purity ? ' ' . $r->purity : '' }}</x-ui.badge></td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($r->rate, 2) }}</td>
                <td class="text-ink_text-primary capitalize">{{ $r->source }}</td>
                <td class="text-ink_text-secondary">{{ $r->updater->name ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No entries match these filters" message="Try a different metal or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="history" title="No rate entries yet" message="Enter today's rates to start the log.">
                            <x-ui.button size="sm" icon="coins" :href="route('pricing.rates')">Enter today's rates</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
