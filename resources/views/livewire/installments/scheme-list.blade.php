<div>
    <x-ui.page-header title="Installment Schemes" subtitle="All enrolments, filterable by status.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="calendar" :href="route('installments.monthly-status')">Monthly status</x-ui.button>
            <x-ui.button icon="plus" :href="route('installments.enrol')">Enrol customer</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="check-circle" label="Active" :value="number_format($stats['active'])" />
        <x-ui.stat-card icon="check" label="Completed" :value="number_format($stats['completed'])" />
        <x-ui.stat-card icon="alert-triangle" label="Defaulted" :value="number_format($stats['defaulted'])" />
    </div>

    <x-ui.datatable :paginator="$schemes">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search customer name or phone" class="w-full sm:w-[280px]" />
            <div class="rj-segment">
                @foreach (['' => 'All', 'active' => 'Active', 'completed' => 'Completed', 'defaulted' => 'Defaulted'] as $value => $name)
                    <button type="button" wire:click="$set('statusFilter', '{{ $value }}')" class="{{ $statusFilter === $value ? 'is-active' : '' }}">{{ $name }}</button>
                @endforeach
            </div>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th field="amount" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Monthly amount</x-ui.th>
            <x-ui.th align="right">Months paid</x-ui.th>
            <x-ui.th align="right">Months pending</x-ui.th>
            <x-ui.th align="right">Amount pending</x-ui.th>
            <x-ui.th field="started" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Started</x-ui.th>
            <x-ui.th>Completes</x-ui.th>
            <x-ui.th field="status" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Status</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($schemes as $scheme)
            <tr wire:key="scheme-{{ $scheme->id }}">
                <td class="text-ink_text-primary font-semibold">{{ $scheme->customer->name ?? '—' }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($scheme->monthly_amount, 2) }}</td>
                <td class="text-right tabular text-ink_text-secondary">{{ $scheme->months_paid }} / {{ $scheme->total_months }}</td>
                <td class="text-right tabular {{ $scheme->months_pending ? '' : 'text-success' }}">{{ $scheme->months_pending }}</td>
                <td class="text-right tabular">₹{{ number_format($scheme->amount_pending, 2) }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($scheme->start_date)->format('d M Y') }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $scheme->completion_date?->format('d M Y') }}</td>
                <td>
                    <x-ui.badge :tone="$scheme->status === 'active' ? 'success' : ($scheme->status === 'completed' ? 'info' : 'danger')" size="sm" dot>
                        {{ $scheme->status === 'active' && $scheme->is_matured ? 'Matured' : ucfirst($scheme->status) }}
                    </x-ui.badge>
                    @if ($scheme->maturity_outcome)<span class="block text-[11.5px] text-ink_text-muted">{{ \App\Models\Customer\InstallmentScheme::OUTCOMES[$scheme->maturity_outcome] }}</span>@endif
                </td>
                <td>
                    <div class="flex items-center justify-end gap-1.5">
                        @if ($scheme->status === 'active')
                            @if ($scheme->is_matured)
                                <x-ui.button type="button" size="sm" icon="check" wire:click="openOutcome({{ $scheme->id }})">Choose outcome</x-ui.button>
                            @endif
                            <x-ui.button type="button" variant="danger-soft" size="sm"
                                x-on:click="$dispatch('rj-confirm', { title: 'Mark scheme defaulted?', message: 'The scheme is closed as defaulted and the member is queued a message.', confirm: 'Mark defaulted', tone: 'danger', action: () => $wire.markDefaulted({{ $scheme->id }}) })">
                                Mark defaulted
                            </x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No schemes match these filters" message="Try a different search or status, or clear the filters.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="calendar" title="No schemes yet" message="Enrol the first customer into the installment scheme.">
                            <x-ui.button size="sm" icon="plus" :href="route('installments.enrol')">Enrol customer</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showOutcome" title="The scheme has matured" icon="check-circle" max-width="lg"
        :subtitle="$outcomeScheme ? $outcomeScheme->customer->name . ' has paid ₹' . number_format($outcomeScheme->paid_in) . '. Choose what happens next.' : ''">
        @if ($outcomeScheme)
            <div class="space-y-3">
                <a href="{{ route('orders.new', ['customer' => $outcomeScheme->customer_id, 'scheme' => $outcomeScheme->id]) }}" class="block p-4 rounded-xl ring-1 ring-inset ring-line hover:ring-gold-soft">
                    <div class="font-semibold text-ink_text-primary">Create an order</div><div class="text-[12.5px] text-ink_text-secondary">A custom order for this customer, with the scheme money as the advance.</div></a>
                <a href="{{ route('sales.new', ['customer' => $outcomeScheme->customer_id, 'scheme' => $outcomeScheme->id]) }}" class="block p-4 rounded-xl ring-1 ring-inset ring-line hover:ring-gold-soft">
                    <div class="font-semibold text-ink_text-primary">Make a sale</div><div class="text-[12.5px] text-ink_text-secondary">Sell pieces now, with the scheme money as the first payment.</div></a>
                <div class="p-4 rounded-xl ring-1 ring-inset ring-line">
                    <div class="font-semibold text-ink_text-primary">Reserve a product in stock</div>
                    @if ($reserveItem)
                        <div class="flex items-center gap-3 mt-2"><span class="rj-code">{{ $reserveItem->label }}</span><span class="text-[12.5px] text-ink_text-secondary flex-1">{{ $reserveItem->category }} · {{ number_format($reserveItem->weight, 3) }} g</span>
                            <x-ui.button size="sm" icon="check" wire:click="reserveProduct" target="reserveProduct">Reserve it</x-ui.button></div>
                    @else
                        <input type="text" wire:model.live.debounce.250ms="reserveSearch" placeholder="HUID, code, packet or box" class="rj-input mt-2" aria-label="Find the product" autocomplete="off">
                        @if ($reserveResults->isNotEmpty())
                            <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">@foreach ($reserveResults as $i)<li><button type="button" wire:click="chooseReserve({{ $i->id }})" class="w-full text-left px-4 py-2 hover:bg-surface-sunken"><span class="rj-code">{{ $i->label }}</span> <span class="text-[12.5px] text-ink_text-muted">{{ $i->category }} · {{ number_format($i->weight, 3) }} g</span></button></li>@endforeach</ul>
                        @endif
                    @endif
                    @error('reserveItemId')<p class="rj-error">{{ $message }}</p>@enderror
                </div>
            </div>
        @endif
        <x-slot:footer><x-ui.button variant="secondary" x-on:click="show = false">Close</x-ui.button></x-slot:footer>
    </x-ui.modal>
</div>
