<div>
    @php
        $crumbs = [['label' => 'Stock', 'href' => route('stock.items')], ['label' => 'Inventory', 'href' => route('stock.items')]];
        $crumbs[] = ['label' => $item->label];
        $makingLabel = [
            'percentage' => 'Percentage of metal value',
            'flat_per_gram' => 'Flat amount per gram',
            'flat_per_piece' => 'Flat amount per piece',
        ][$item->making_type] ?? 'Flat amount per piece';
        $makingValue = match ($item->making_type) {
            'percentage' => rtrim(rtrim(number_format($item->making_value, 2), '0'), '.') . '%',
            'flat_per_gram' => '₹' . number_format($item->making_value, 2) . ' / g',
            default => '₹' . number_format($item->making_value, 2) . ' / piece',
        };
        $expected = $lastMovement?->expected_return ? \Carbon\Carbon::parse($lastMovement->expected_return) : null;
        $overdue = $expected && $item->status === 'dispatched' && $expected->isPast();
    @endphp

    <x-ui.page-header :title="$item->label" :subtitle="$item->category . ($item->description ? ' · ' . $item->description : '')" :crumbs="$crumbs">
        <x-slot:meta>
            <x-ui.status :status="$item->status" size="lg" />
            @if ($item->huid_code)
                <x-ui.badge tone="gold" size="lg"><x-ui.icon name="shield-check" :size="13" /> HUID</x-ui.badge>
            @else
                <x-ui.badge size="lg">Internal code</x-ui.badge>
            @endif
            @if ($pair)
                <x-ui.badge size="lg"><x-ui.icon name="link" :size="12" /> Pair</x-ui.badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="package" wire:click="openMove">{{ $item->packet ? 'Move' : 'Put in packet' }}</x-ui.button>
            @if (! $item->deletionBlocker())
                <x-ui.button variant="danger-soft" icon="trash"
                    x-on:click="$dispatch('rj-confirm', { title: 'Delete {{ $item->label }}?', message: 'The piece is hidden from stock but kept in the audit trail.', confirm: 'Delete', tone: 'danger', action: () => $wire.deleteItem() })">Delete</x-ui.button>
            @endif
            <x-ui.button icon="edit" x-on:click="Livewire.dispatch('open-item-form', { id: {{ $item->id }} })">Edit piece</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Key facts --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white border border-line-light rounded-card shadow-card mb-6 divide-y lg:divide-y-0 divide-line-light lg:divide-x overflow-hidden">
        <div class="p-5">
            <div class="text-[12px] font-semibold text-ink_text-muted">Weight</div>
            <div class="font-display text-[30px] leading-tight font-semibold tabular mt-1">{{ number_format($item->weight, 3) }}<span class="text-[16px] text-ink_text-muted ml-1">g</span></div>
        </div>
        <div class="p-5 border-l border-line-light lg:border-l-0">
            <div class="text-[12px] font-semibold text-ink_text-muted">Metal</div>
            <div class="font-display text-[30px] leading-tight font-semibold mt-1">{{ $item->metal ? ucfirst($item->metal) : 'Unspecified' }} <span class="text-[18px] text-ink_text-secondary">{{ $item->purity }}</span></div>
        </div>
        <div class="p-5">
            <div class="text-[12px] font-semibold text-ink_text-muted">Kept in</div>
            <div class="mt-2 flex items-center gap-1.5 flex-wrap">
                @if ($item->packet)
                    @if ($item->packet->box)
                        <a href="{{ route('stock.boxes.show', $item->packet->box) }}" class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg bg-surface-sunken ring-1 ring-inset ring-line-light text-ink_text-primary hover:ring-gold-soft">
                            <x-ui.icon name="archive" :size="13" class="text-ink_text-muted" /><span class="rj-code">{{ $item->packet->box->code }}</span>
                        </a>
                        <x-ui.icon name="chevron-right" :size="13" class="text-ink_text-muted" />
                    @endif
                    <a href="{{ route('stock.packets.show', $item->packet) }}" class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg bg-surface-sunken ring-1 ring-inset ring-line-light text-ink_text-primary hover:ring-gold-soft">
                        <x-ui.icon name="package" :size="13" class="text-ink_text-muted" /><span class="rj-code">{{ $item->packet->code }}</span>
                    </a>
                @else
                    <span class="text-[14px] font-semibold text-ink_text-muted">Not in a packet</span>
                @endif
            </div>
        </div>
        <div class="p-5 border-l border-line-light lg:border-l-0 bg-gradient-to-br from-gold-tint to-white">
            <div class="text-[12px] font-semibold text-gold-dark">{{ $sale ? 'Sold at' : 'Price today' }}</div>
            <div class="font-display text-[30px] leading-tight font-semibold tabular mt-1 text-ink_text-primary">
                @if ($sale && $item->status === 'sold')
                    ₹{{ number_format($sale->pivot->price_at_sale, 0) }}
                @elseif ($price)
                    ₹{{ number_format($price['total'], 0) }}
                @else
                    -
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
        <div class="space-y-6 min-w-0">
            {{-- Where it is right now --}}
            @if ($lastMovement && in_array($item->status, ['dispatched', 'pending_review'], true))
                <div class="flex flex-wrap items-start gap-4 p-5 rounded-card border {{ $overdue ? 'bg-danger-bg border-danger/20' : ($item->status === 'pending_review' ? 'bg-info-bg border-info/15' : 'bg-warning-bg border-warning/20') }}">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-white/80 flex items-center justify-center {{ $overdue ? 'text-danger' : ($item->status === 'pending_review' ? 'text-info' : 'text-warning') }}">
                        <x-ui.icon :name="$item->status === 'pending_review' ? 'clock' : 'truck'" :size="18" />
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <div class="text-[14px] font-bold text-ink_text-primary">
                            @if ($item->status === 'pending_review')
                                Back in the shop, waiting for an admin to confirm it into stock
                            @else
                                {{ \App\Services\StockHistoryService::MOVEMENT_LABELS[$lastMovement->movement_type] ?? 'Out of the shop' }}{{ $lastMovement->counterparty ? ': ' . $lastMovement->counterparty : '' }}
                            @endif
                        </div>
                        <div class="text-[12.5px] text-ink_text-secondary mt-0.5">
                            Since {{ $lastMovement->created_at->format('d M Y') }} ({{ $lastMovement->created_at->diffForHumans() }})
                            @if ($expected && $item->status === 'dispatched')
                                · {{ $overdue ? 'Was due back' : 'Due back' }} {{ $expected->format('d M Y') }}
                            @endif
                        </div>
                    </div>
                    @if ($item->status === 'pending_review' && \Illuminate\Support\Facades\Route::has('movements.pending-review'))
                        <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('movements.pending-review')">Review queue</x-ui.button>
                    @endif
                </div>
            @elseif ($sale)
                <div class="flex flex-wrap items-center gap-4 p-5 rounded-card border bg-gold-tint border-gold-soft">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-white text-gold-dark flex items-center justify-center"><x-ui.icon name="receipt" :size="18" /></div>
                    <div class="flex-1 min-w-[220px]">
                        <div class="text-[14px] font-bold text-ink_text-primary">
                            {{ $sale->confirmed_by_accountant ? 'Sold' : 'In a sale awaiting verification' }}{{ $sale->customer ? ' to ' . $sale->customer->name : '' }}
                        </div>
                        <div class="text-[12.5px] text-ink_text-secondary mt-0.5">Invoice {{ $sale->invoice_number }} · {{ $sale->created_at->format('d M Y') }} · ₹{{ number_format($sale->pivot->price_at_sale, 2) }}</div>
                    </div>
                    <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="route('sales.invoice', $sale)">Invoice</x-ui.button>
                </div>
            @endif

            <x-ui.card title="History" subtitle="Every place it has been, from the day it was entered until now" icon="history">
                <x-ui.timeline :events="$events" />
            </x-ui.card>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Pricing setup" icon="coins">
                <dl class="rj-dl">
                    <div class="col-span-2"><dt>Making charge</dt><dd>{{ $makingLabel }} · <span class="tabular">{{ $makingValue }}</span></dd></div>
                </dl>
                @if ($price)
                    <div class="mt-4 pt-4 border-t border-line-light space-y-2 text-[13px]">
                        <div class="flex justify-between"><span class="text-ink_text-secondary">Metal value <span class="text-ink_text-muted text-[12px]">({{ number_format($price['weight'], 3) }} g{{ $item->net_weight ? ' net' : '' }} × ₹{{ number_format($price['rate'], 2) }})</span></span><span class="tabular">₹{{ number_format($price['metal_value'], 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-ink_text-secondary">Making charge</span><span class="tabular">₹{{ number_format($price['making'], 2) }}</span></div>
                        @if ($price['stone_value'] > 0)
                            <div class="flex justify-between"><span class="text-ink_text-secondary">Stones</span><span class="tabular">₹{{ number_format($price['stone_value'], 2) }}</span></div>
                        @endif
                        @if ($price['huid_charge'])
                            <div class="flex justify-between"><span class="text-ink_text-secondary">Hallmarking charge</span><span class="tabular">₹{{ number_format($price['huid_charge'], 2) }}</span></div>
                        @endif
                        @foreach ($price['additional_lines'] as $line)
                            <div class="flex justify-between"><span class="text-ink_text-secondary">{{ $line['name'] }}</span><span class="tabular">₹{{ number_format($line['amount'], 2) }}</span></div>
                        @endforeach
                        @if ($price['discount'] > 0)
                            <div class="flex justify-between text-success"><span>Discount ({{ $price['discount_rule']->scope }} rule)</span><span class="tabular">-₹{{ number_format($price['discount'], 2) }}</span></div>
                        @endif
                        <div class="flex justify-between pt-2 border-t border-line-light font-bold text-[14px]"><span>Today's price</span><span class="tabular">₹{{ number_format($price['total'], 2) }}</span></div>
                    </div>
                    <p class="text-[12px] text-ink_text-muted mt-3">
                        Calculated live from the {{ $price['metal'] }} rate{{ $price['rate_at'] ? ' of ' . $price['rate_at']->format('d M Y') : '' }}. Never stored, so a rate change reprices it instantly.
                    </p>
                @elseif ($sale)
                    <p class="text-[12.5px] text-ink_text-secondary mt-4 pt-4 border-t border-line-light">The price was frozen at ₹{{ number_format($sale->pivot->price_at_sale, 2) }} when it was sold.</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Details" icon="file-text">
                <dl class="rj-dl">
                    <div><dt>HUID</dt><dd class="rj-code">{{ $item->huid_code ?: 'None' }}</dd></div>
                    <div><dt>Internal code</dt><dd class="rj-code">{{ $item->internal_code ?: 'None' }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $item->category }}</dd></div>
                    <div><dt>HSN code</dt><dd class="tabular">{{ $item->hsn_code ?: 'Not set' }}</dd></div>
                    <div class="col-span-2"><dt>Description</dt><dd>{{ $item->description ?: 'No description' }}</dd></div>
                    <div class="col-span-2">
                        <dt>Paired with</dt>
                        <dd>
                            @if ($pair)
                                <a href="{{ route('stock.items.show', $pair) }}" class="rj-code">{{ $pair->label }}</a>
                                <span class="text-ink_text-muted tabular">· {{ number_format($pair->weight, 3) }} g ·</span>
                                <x-ui.status :status="$pair->status" size="sm" />
                            @else
                                <span class="text-ink_text-muted">Single piece</span>
                            @endif
                        </dd>
                    </div>
                    @if ($item->sourcePurchaseItem)
                        <div class="col-span-2"><dt>Came from</dt><dd>Purchase #{{ $item->sourcePurchaseItem->purchase_id }}{{ $item->sourcePurchaseItem->purchase?->vendor ? ' · ' . $item->sourcePurchaseItem->purchase->vendor->name : '' }}</dd></div>
                    @elseif ($item->sourceKarigarBatch)
                        <div class="col-span-2"><dt>Came from</dt><dd>Karigar raw-material batch #{{ $item->sourceKarigarBatch->id }}</dd></div>
                    @endif
                    <div><dt>Entered</dt><dd>{{ $item->created_at?->format('d M Y') }}</dd></div>
                    <div><dt>Last updated</dt><dd>{{ $item->updated_at?->diffForHumans() }}</dd></div>
                </dl>
            </x-ui.card>

            {{-- Per-piece QR stickers hidden: no reprinting for individual pieces (8 Oct change list, 4.4). --}}
        </aside>
    </div>

    <livewire:stock.item-form />

    <x-ui.modal wire:model="showMove" title="Move {{ $item->label }}" icon="package" max-width="sm" submit="move" subtitle="The move is recorded in the piece's and both packets' history.">
        <div class="mb-4">
            <label for="im-scan" class="block text-[12.5px] font-semibold text-ink_text-primary mb-1.5">Scan the packet or box</label>
            {{-- Not a <form>: this sits inside the modal's own form, where Enter would save the move. --}}
            <div class="flex gap-2">
                <input id="im-scan" x-ref="dscan" type="text" autocomplete="off" placeholder="PKT-1-2 or BOX-01" class="rj-input rj-code flex-1"
                    x-on:keydown.enter.prevent="const v = $refs.dscan.value; $refs.dscan.value = ''; if (v.trim()) $wire.scanDestination(v)">
                <x-ui.scan-button target="#im-scan" submit="enter" title="Scan the packet or box" variant="button" label="Camera" />
            </div>
            @if ($moveScanError)<p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $moveScanError }}</p>@endif
            @if ($moveBox)<p class="rj-help">Showing packets in {{ $moveBox }}. <button type="button" class="text-gold-dark font-semibold" wire:click="$set('moveBox', null)">Show all</button></p>@endif
        </div>
        <x-ui.field label="Packet" for="im-packet" error="moveToPacketId">
            <select id="im-packet" wire:model="moveToPacketId" autofocus class="rj-select">
                <option value="">Not in a packet</option>
                @foreach ($packetsByBox as $boxCode => $packets)
                    <optgroup label="{{ $boxCode }}">
                        @foreach ($packets as $p)
                            <option value="{{ $p->id }}">{{ $p->code }}{{ $p->label ? ' · ' . $p->label : '' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </x-ui.field>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="move" icon="check">Move piece</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
