<div>
    <x-ui.page-header title="Exchanges" subtitle="Open one to carry on from the step it reached. Settled ones can be read but not changed."
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'Exchanges']]">
        <x-slot:actions><x-ui.button icon="plus" :href="route('exchange.new')">New exchange</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable :paginator="$exchanges">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Customer name, phone or #" class="w-full sm:w-[260px]" />
            <div class="rj-segment">@foreach (['open' => 'Open (' . $open . ')', 'settled' => 'Settled', 'all' => 'All'] as $v => $l)<button type="button" wire:click="$set('show', '{{ $v }}')" class="{{ $show === $v ? 'is-active' : '' }}">{{ $l }}</button>@endforeach</div>
            @if ($this->hasNonDefaultFilters())<x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>@endif
        </x-slot:toolbar>
        <x-slot:head>
            <x-ui.th field="id" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Exchange</x-ui.th>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th>Metal</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Gross</x-ui.th>
            <x-ui.th align="right">Payable</x-ui.th>
            <x-ui.th>Stage</x-ui.th>
            <x-ui.th field="updated" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Updated</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Open</span></x-ui.th>
        </x-slot:head>
        @forelse ($exchanges as $x)
            <tr wire:key="ex-{{ $x->id }}" class="cursor-pointer hover:bg-surface-sunken" onclick="window.location='{{ route('exchange.new', ['tx' => $x->id]) }}'">
                <td class="rj-code">#{{ $x->id }}</td>
                <td class="font-semibold text-ink_text-primary">{{ $x->customer?->name }}<span class="block text-[12px] text-ink_text-muted tabular font-normal">{{ $x->customer?->phone }}</span></td>
                <td>{{ ucfirst($x->metal) }}</td>
                <td class="text-right tabular">{{ number_format($x->gross_weight, 3) }} g</td>
                <td class="text-right tabular">{{ $x->deductable_weight !== null ? number_format($x->deductable_weight, 3) . ' g' : '-' }}</td>
                <td><x-ui.badge size="sm" :tone="['received' => 'warning', 'melted' => 'warning', 'tested' => 'info', 'valued' => 'info', 'settled' => 'success'][$x->stage]">{{ ucfirst($x->stage) }}</x-ui.badge></td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $x->updated_at->format('j M, g:i a') }}</td>
                <td class="text-right"><x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('exchange.new', ['tx' => $x->id])">{{ $x->stage === 'settled' ? 'View' : 'Continue' }}</x-ui.button></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-ui.empty-state icon="flame" title="No exchanges here" message="Start one with New exchange." compact /></td></tr>
        @endforelse
    </x-ui.datatable>
</div>
