<div>
    <x-ui.page-header :title="'Sale ' . $sale->bill_number" subtitle="Pieces, charges and payments as recorded on this sale."
        :crumbs="[['label' => 'Sales History', 'href' => route('sales.history')], ['label' => $sale->bill_number]]">
        <x-slot:meta>
            @if ($sale->cancellation)
                <x-ui.badge tone="danger" size="lg">Cancelled</x-ui.badge>
            @else
                <x-ui.badge :tone="$sale->confirmed_by_accountant ? 'success' : 'warning'" size="lg">{{ $sale->confirmed_by_accountant ? 'Verified' : 'Waiting for admin' }}</x-ui.badge>
            @endif
            @if ($sale->balance > 0)<x-ui.badge tone="danger" size="lg">Balance ₹{{ number_format($sale->balance, 2) }}</x-ui.badge>@endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($canCancel)
                <x-ui.button variant="danger-soft" icon="x-circle" wire:click="startCancel({{ $sale->id }})" target="startCancel">Cancel sale</x-ui.button>
            @endif
            <x-ui.button variant="secondary" icon="printer" :href="route('sales.bill', $sale)" target="_blank">Print bill</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($sale->cancellation)
        <div class="bg-danger-bg text-danger rounded-control px-3.5 py-3 mb-5 text-[13px] max-w-[860px]">
            <div class="flex items-center gap-2 font-semibold"><x-ui.icon name="x-circle" :size="14" class="shrink-0" /> This sale was cancelled on {{ $sale->cancellation->created_at->format('j M Y, g:i a') }} by {{ $sale->cancellation->user?->name }}.</div>
            <p class="mt-1">Reason: {{ $sale->cancellation->reason }}.
                The pieces are back in stock.
                @if ((float) $sale->cancellation->credit_returned > 0) ₹{{ number_format($sale->cancellation->credit_returned, 2) }} of exchange credit and advance went back to the customer.@endif
                @if ((float) $sale->cancellation->held_on_account > 0) ₹{{ number_format($sale->cancellation->held_on_account, 2) }} that was paid is held on the customer's account as an advance.@endif
            </p>
        </div>
    @endif

    @unless ($sale->confirmed_by_accountant || $sale->cancellation)
        <div class="bg-warning-bg text-warning rounded-control px-3.5 py-2.5 mb-5 text-[12.5px] flex items-center gap-2 max-w-[860px]">
            <x-ui.icon name="alert-triangle" :size="13" class="shrink-0" /> The pieces are held for this sale. It becomes final when an admin verifies it.
        </div>
    @endunless

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
        <x-ui.card :padding="false" title="Pieces" icon="gem">
            <x-ui.table :headers="['Piece', 'Category', 'Weight', 'Price']">
                @foreach ($sale->items as $item)
                    <tr><td><a href="{{ route('stock.items.show', $item) }}" class="rj-code hover:text-gold-dark">{{ $item->label }}</a></td><td>{{ $item->category }}</td>
                        <td class="tabular">{{ number_format($item->pivot->weight_at_sale ?? $item->weight, 3) }} g
                            @if ($item->pivot->weight_at_sale !== null && $item->pivot->weight_listed !== null && abs($item->pivot->weight_at_sale - $item->pivot->weight_listed) > 0.0004)
                                <span class="block text-[11.5px] text-warning font-semibold" title="{{ $item->pivot->weight_note }}">record {{ number_format($item->pivot->weight_listed, 3) }} g{{ $item->pivot->weight_note ? ' · ' . $item->pivot->weight_note : '' }}</span>
                            @endif
                            @if ($item->pivot->making_rate !== null && abs($item->pivot->making_rate - $item->pivot->making_default_rate) > 0.004)
                                <span class="block text-[11.5px] text-danger font-semibold">making {{ \App\Services\PricingService::rateText($item->pivot->making_kind, (float) $item->pivot->making_rate) }}, priced at {{ \App\Services\PricingService::rateText($item->pivot->making_kind, (float) $item->pivot->making_default_rate) }}</span>
                            @endif
                        </td><td class="tabular">₹{{ number_format($item->pivot->price_at_sale) }}</td></tr>
                @endforeach
                @if ($sale->discount > 0)
                    <tr class="text-success"><td colspan="3">Adjustment{{ $sale->adjustment_type === 'percent' ? ' (' . rtrim(rtrim(number_format($sale->adjustment_value, 2), '0'), '.') . '%)' : '' }}</td><td class="tabular">- ₹{{ number_format($sale->discount, 2) }}</td></tr>
                @endif
                @if ($sale->gst_amount > 0)
                    @php $gstJewel = round($sale->gst_amount - $sale->gst_hallmark_amount, 2); @endphp
                    @if ($gstJewel > 0)<tr><td colspan="3" class="text-ink_text-secondary">CGST {{ rtrim(rtrim(number_format($sale->gst_jewellery_percent / 2, 2), '0'), '.') }}% + SGST {{ rtrim(rtrim(number_format($sale->gst_jewellery_percent / 2, 2), '0'), '.') }}% on jewellery</td><td class="tabular">₹{{ number_format($gstJewel, 2) }}</td></tr>@endif
                    @if ($sale->gst_hallmark_amount > 0)<tr><td colspan="3" class="text-ink_text-secondary">CGST {{ rtrim(rtrim(number_format($sale->gst_hallmark_percent / 2, 2), '0'), '.') }}% + SGST {{ rtrim(rtrim(number_format($sale->gst_hallmark_percent / 2, 2), '0'), '.') }}% on hallmarking</td><td class="tabular">₹{{ number_format($sale->gst_hallmark_amount, 2) }}</td></tr>@endif
                @endif
                @foreach ($sale->additional_charges ?? [] as $c)
                    <tr><td colspan="3" class="text-ink_text-secondary">{{ $c['name'] }}</td><td class="tabular">₹{{ number_format($c['amount'], 2) }}</td></tr>
                @endforeach
                @if ((float) $sale->round_off != 0)
                    <tr><td colspan="3" class="text-ink_text-secondary">Round off</td><td class="tabular">{{ $sale->round_off > 0 ? '+' : '-' }} ₹{{ number_format(abs($sale->round_off), 2) }}</td></tr>
                @endif
                @if ((float) $sale->final_discount > 0)
                    <tr class="text-success"><td colspan="3">Discount</td><td class="tabular">- ₹{{ number_format($sale->final_discount, 2) }}</td></tr>
                @endif
                <tr class="font-semibold bg-surface-sunken"><td colspan="3">Total</td><td class="tabular">₹{{ number_format($sale->total, 2) }}</td></tr>
            </x-ui.table>
        </x-ui.card>

        <div class="space-y-6">
            <x-ui.card title="Payments" icon="coins">
                @forelse ($sale->payments as $p)
                    <div class="flex justify-between py-2 border-b border-line-light last:border-0 text-[13.5px]" wire:key="sp-{{ $p->id }}">
                        <span>{{ $modes[$p->mode] }} <span class="text-[12px] text-ink_text-muted">{{ $p->created_at->format('j M, g:i a') }} · {{ $p->user?->name }}</span></span><span class="tabular font-semibold">₹{{ number_format($p->amount, 2) }}</span>
                    </div>
                @empty <p class="text-[13px] text-ink_text-muted">Nothing paid yet.</p> @endforelse
                <div class="flex justify-between mt-3 pt-3 border-t border-line-light font-semibold"><span>Balance</span><span class="tabular {{ $sale->balance > 0 ? 'text-warning' : 'text-success' }}">₹{{ number_format($sale->balance, 2) }}</span></div>

                @if ($sale->balance > 0 && ! $sale->cancellation)
                    <form wire:submit="addPayment" class="mt-5 pt-4 border-t border-line-light space-y-3">
                        <div class="flex gap-2 items-center"><x-ui.mode-picker model="mode" :value="$mode" />
                            <input type="text" inputmode="decimal" autocomplete="off" wire:model="amount" placeholder="₹" class="rj-input tabular flex-1 @error('amount') is-invalid @enderror" aria-label="Amount"></div>
                        @error('amount')<p class="rj-error">{{ $message }}</p>@enderror
                        <x-ui.button type="submit" size="sm" icon="plus" target="addPayment">Record a payment</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            <x-ui.card title="Details" icon="file-text">
                <dl class="rj-dl">
                    <div><dt>Customer</dt><dd>{{ $sale->customer->name }}<span class="block text-[12.5px] text-ink_text-muted tabular">{{ $sale->customer->phone }}</span>@if ($sale->customer->gstin)<span class="block text-[12.5px] text-ink_text-muted rj-code">GSTIN {{ $sale->customer->gstin }}</span>@endif @if (! $sale->customer->is_walk_in && trim((string) $sale->customer->address) !== '')<span class="block text-[12.5px] text-ink_text-muted">{{ $sale->customer->address }}</span>@endif</dd></div>
                    <div><dt>Entered by</dt><dd>{{ $sale->created_at->format('j M Y, g:i a') }} · {{ $sale->creator?->name }}</dd></div>
                    @if ($sale->confirmed_by_accountant)<div><dt>Tally bill number</dt><dd class="rj-code">{{ $sale->invoice_number }}</dd></div>@endif
                    @if ($sale->referrer)<div><dt>Referred by</dt><dd>{{ $sale->referrer->name }}</dd></div>@endif
                    @if ($sale->order_override_note)<div><dt>Sold over an order hold</dt><dd>{{ $sale->order_override_note }}</dd></div>@endif
                    @if ($sale->accountant_note)<div><dt>Notes</dt><dd>{{ $sale->accountant_note }}</dd></div>@endif
                </dl>
            </x-ui.card>
        </div>
    </div>
    <x-sales.cancel-modal />
</div>
