<div>
    <x-ui.page-header title="Purchases" subtitle="Payment status is fixed at entry and cannot be edited here — see note below."
        :crumbs="[['label' => 'Purchases & Vendors', 'href' => route('purchases.list')], ['label' => 'Purchases']]">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('purchases.new')">New purchase</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card icon="clipboard" label="Total purchases" :value="number_format($stats['total'])" />
        <x-ui.stat-card icon="gem" label="Finished product" :value="number_format($stats['finishedProduct'])" />
        <x-ui.stat-card icon="scale" label="Raw material" :value="number_format($stats['rawMaterial'])" />
        <x-ui.stat-card icon="coins" label="Total spend" :value="'₹'.number_format($stats['totalSpend'], 2)" />
    </div>

    <x-ui.datatable :paginator="$purchases">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Search invoice no. or vendor" class="w-full sm:w-[280px]" />
            <select wire:model.live="typeFilter" class="rj-select rj-input-sm">
                <option value="">All types</option>
                <option value="finished_product">Finished product</option>
                <option value="raw_material">Raw material</option>
            </select>
            <select wire:model.live="statusFilter" class="rj-select rj-input-sm">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
            </select>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th>#</x-ui.th>
            <x-ui.th field="vendor" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Vendor</x-ui.th>
            <x-ui.th>Type</x-ui.th>
            <x-ui.th>Invoice</x-ui.th>
            <x-ui.th align="right">Weight</x-ui.th>
            <x-ui.th field="amount" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Amount</x-ui.th>
            <x-ui.th>Status</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Date</x-ui.th>
        </x-slot:head>

        @forelse ($purchases as $p)
            <tr wire:key="purchase-{{ $p->id }}">
                <td class="text-ink_text-secondary">#{{ $p->id }}</td>
                <td class="text-ink_text-primary font-semibold">{{ $p->vendor->name ?? '—' }}</td>
                <td>
                    @if ($p->type === 'raw_material')
                        <x-ui.badge tone="gold" size="sm">Raw Material</x-ui.badge>
                    @else
                        <x-ui.badge tone="info" size="sm">Finished Product</x-ui.badge>
                    @endif
                </td>
                <td class="text-ink_text-primary">{{ $p->invoice_number ?: '—' }}</td>
                <td class="text-right tabular text-ink_text-secondary">{{ $p->total_weight ? number_format($p->total_weight, 3).' g' : '—' }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($p->total_amount, 2) }}</td>
                <td>
                    @if ($p->payment_status === 'paid')
                        <x-ui.badge tone="success" size="sm" dot>Paid</x-ui.badge>
                    @elseif ($p->payment_status === 'partial')
                        <x-ui.badge tone="neutral" size="sm" dot>Partial</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning" size="sm" dot>Pending</x-ui.badge>
                    @endif
                </td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $p->created_at->format('d M Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No purchases match these filters" message="Try a different search, type, or status.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear filters</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="clipboard" title="No purchases recorded yet" message="Record the first purchase from a karigar or supplier.">
                            <x-ui.button size="sm" icon="plus" :href="route('purchases.new')">New purchase</x-ui.button>
                        </x-ui.empty-state>
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <div class="mt-6 rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5 max-w-[720px]">
        <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
            <x-ui.icon name="info" :size="14" /> Why payment status cannot be edited here
        </div>
        <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
            Payment status can't be changed from "pending" to "paid"/"partial" after the purchase is saved — the rule against ever updating a purchases row applies here the same way it does to sales. Recording a payment update would need a correction mechanism (a new row referencing this one, owner-approved), which does not exist yet.
        </p>
    </div>
</div>
