<div>
    <x-ui.page-header title="Sale Verification Queue" subtitle="Verifying confirms the sale with the original Tally bill number, flips its pieces from reserved to sold, and queues the customer message."
        :crumbs="[['label' => 'Sales & Billing', 'href' => route('sales.history')], ['label' => 'Verification Queue']]" />

    <x-ui.datatable :paginator="$pending">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Sale number, customer name or phone" class="w-full sm:w-[280px]" />
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="invoice" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Sale</x-ui.th>
            <x-ui.th>Customer</x-ui.th>
            <x-ui.th align="right">Items</x-ui.th>
            <x-ui.th field="total" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Total</x-ui.th>
            <x-ui.th align="right">Balance</x-ui.th>
            <x-ui.th>By</x-ui.th>
            <x-ui.th field="created" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Reserved</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($pending as $sale)
            <tr wire:key="pending-sale-{{ $sale->id }}">
                <td><a href="{{ route('sales.invoice', $sale) }}" class="rj-code text-ink_text-primary hover:text-gold-dark">#{{ $sale->id }}</a></td>
                <td class="text-ink_text-primary">{{ $sale->customer->name ?? '—' }}</td>
                <td class="text-right tabular">{{ $sale->items->count() }}</td>
                <td class="text-right tabular text-ink_text-primary">₹{{ number_format($sale->total, 2) }}</td>
                <td class="text-right tabular {{ $sale->total - (float) $sale->payments_sum_amount > 0.005 ? 'text-warning' : '' }}">₹{{ number_format($sale->total - (float) $sale->payments_sum_amount, 2) }}</td>
                <td class="text-ink_text-secondary">{{ $sale->creator->name ?? '—' }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $sale->created_at?->format('d M Y, g:i a') }}</td>
                <td>
                    <div class="flex items-center justify-end">
                        <x-ui.button variant="primary" size="sm" icon="check" wire:click="startVerify({{ $sale->id }})" target="startVerify">Verify</x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    @if ($this->hasActiveFilters())
                        <x-ui.empty-state icon="search" title="No sales match this search" message="Try a different invoice or customer name.">
                            <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">Clear search</x-ui.button>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="check-circle" title="Nothing waiting on verification" message="Reserved sales from New Sale will show up here." />
                    @endif
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <x-ui.modal wire:model="showVerify" title="Verify this sale" icon="check-circle" max-width="md" submit="verify"
        subtitle="The pieces become sold. This can not be undone. A balance does not stop verification.">
        <x-ui.field label="Tally bill number" for="vq-tally" error="tallyNumber" hint="The original bill number from Tally. It replaces the system's holding reference.">
            <input id="vq-tally" type="text" wire:model="tallyNumber" maxlength="50" class="rj-input rj-code @error('tallyNumber') is-invalid @enderror" autocomplete="off" autofocus>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="verify" icon="check">Verify sale</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
