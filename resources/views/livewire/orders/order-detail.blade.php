<div>
    @php
        $o = $order;
        $tones = ['placed' => 'warning', 'confirmed' => 'warning', 'ready' => 'gold', 'delivered' => 'success', 'cancelled' => 'danger'];
        $overdue = $o->expected_ready_date && ! in_array($o->status, ['ready', 'delivered', 'cancelled'], true) && $o->expected_ready_date->isPast();
    @endphp

    <x-ui.page-header :title="$o->product_description" :subtitle="'Order #' . $o->id . ' for ' . ($o->customer?->name ?? 'Unknown customer')"
        :crumbs="[['label' => 'Custom Orders'], ['label' => 'Status Board', 'href' => route('orders.board')], ['label' => '#' . $o->id]]">
        <x-slot:meta>
            <x-ui.badge :tone="$tones[$o->status] ?? 'neutral'" size="lg" dot>{{ ucfirst($o->status) }}</x-ui.badge>
            @if ($overdue)
                <x-ui.badge tone="danger" size="lg"><x-ui.icon name="alert-triangle" :size="12" /> Overdue</x-ui.badge>
            @endif
        </x-slot:meta>
        @if (! in_array($o->status, ['delivered', 'cancelled']))
            <x-slot:actions>
                @if ($o->status === 'placed')
                    <x-ui.button icon="check-circle" wire:click="confirm">Confirm Order</x-ui.button>
                @endif
                @if ($o->status === 'confirmed')
                    <x-ui.button icon="gift" wire:click="markReady">Mark Ready</x-ui.button>
                @endif
                @if ($o->status === 'ready')
                    <x-ui.button icon="check" wire:click="deliver">Mark Delivered</x-ui.button>
                @endif
                <x-ui.button variant="danger-soft" icon="x-circle"
                    x-on:click="$dispatch('rj-confirm', { title: 'Cancel this order?', message: 'This cannot be undone. The customer will need a new order if they still want it.', confirm: 'Cancel order', tone: 'danger', action: () => $wire.cancel() })">
                    Cancel Order
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    {{-- Key facts --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 bg-white border border-line-light rounded-card shadow-card mb-6 divide-y lg:divide-y-0 divide-line-light lg:divide-x overflow-hidden">
        <div class="p-5">
            <div class="text-[12px] font-semibold text-ink_text-muted">Estimated value</div>
            <div class="font-display text-[28px] leading-tight font-semibold tabular mt-1">₹{{ number_format((float) $o->estimated_value) }}</div>
        </div>
        <div class="p-5 border-l border-line-light lg:border-l-0">
            <div class="text-[12px] font-semibold text-ink_text-muted">Advance paid</div>
            <div class="font-display text-[28px] leading-tight font-semibold tabular mt-1">₹{{ number_format((float) $o->advance_amount) }}</div>
        </div>
        <div class="p-5">
            <div class="text-[12px] font-semibold text-ink_text-muted">Weight / metal</div>
            <div class="font-display text-[24px] leading-tight font-semibold mt-1">
                {{ $o->estimated_weight ? number_format($o->estimated_weight, 3) . 'g' : '—' }}
                <span class="text-[15px] text-ink_text-secondary">{{ $o->metal ? ucfirst($o->metal) : '' }}</span>
            </div>
        </div>
        <div class="p-5 border-l border-line-light lg:border-l-0 {{ $o->rate_locked ? 'bg-gradient-to-br from-gold-tint to-white' : '' }}">
            <div class="text-[12px] font-semibold {{ $o->rate_locked ? 'text-gold-dark' : 'text-ink_text-muted' }}">Rate</div>
            <div class="font-display text-[22px] leading-tight font-semibold mt-1 text-ink_text-primary">
                @if ($o->rate_locked)
                    ₹{{ number_format((float) $o->locked_rate) }} <span class="text-[13px] text-ink_text-secondary font-normal">locked</span>
                @else
                    <span class="text-[16px]">Applies at delivery</span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-6 items-start">
        <div class="space-y-6 min-w-0">
            @if ($overdue)
                <div class="flex flex-wrap items-start gap-4 p-5 rounded-card border bg-danger-bg border-danger/20">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-white/80 flex items-center justify-center text-danger"><x-ui.icon name="alert-triangle" :size="18" /></div>
                    <div class="flex-1 min-w-[220px]">
                        <div class="text-[14px] font-bold text-ink_text-primary">Past the expected ready date</div>
                        <div class="text-[12.5px] text-ink_text-secondary mt-0.5">Was expected {{ $o->expected_ready_date->format('d M Y') }} ({{ $o->expected_ready_date->diffForHumans() }}) and still hasn't moved to Ready.</div>
                    </div>
                </div>
            @endif

            @php
                $stepLabels = ['karigar' => 'Karigar', 'hallmark' => 'Hallmarking', 'sales' => 'Sales'];
                $stepLinks = ['karigar' => route('movements.karigar', ['tab' => 'issue', 'order' => $o->id]), 'hallmark' => route('movements.hallmark', ['tab' => 'dispatch', 'order' => $o->id]), 'sales' => route('sales.new', ['order' => $o->id])];
                $stepTone = ['todo' => 'neutral', 'doing' => 'warning', 'done' => 'success'];
            @endphp
            <x-ui.card :title="'Path: ' . \App\Models\Orders\Order::SOURCING[$o->sourcing][0]" subtitle="Each step links to its screen with this order already chosen" icon="repeat">
                <ol class="space-y-2.5">
                    @foreach ($o->pathSteps() as $st)
                        <li class="flex items-center gap-3 p-3.5 rounded-xl ring-1 ring-inset ring-line-light">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-bold {{ $st['status'] === 'done' ? 'bg-success-bg text-success' : 'bg-surface-muted text-ink_text-muted' }}">@if ($st['status'] === 'done')<x-ui.icon name="check" :size="13" />@else{{ $loop->iteration }}@endif</span>
                            <div class="flex-1"><div class="font-semibold text-ink_text-primary">{{ $stepLabels[$st['step']] }}</div><div class="text-[12.5px] text-ink_text-muted">{{ $st['detail'] }}</div></div>
                            <x-ui.badge size="sm" :tone="$stepTone[$st['status']]">{{ ['todo' => 'To do', 'doing' => 'In progress', 'done' => 'Done'][$st['status']] }}</x-ui.badge>
                            @if ($st['status'] !== 'done' && ! in_array($o->status, ['delivered', 'cancelled']))
                                <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" :href="$stepLinks[$st['step']]">Open</x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>

            @if ($o->images->isNotEmpty())
                <x-ui.card title="Reference images" icon="image">
                    <div class="flex flex-wrap gap-3">
                        @foreach ($o->images as $img)@if ($img->url)<a href="{{ $img->url }}" target="_blank"><img src="{{ $img->url }}" alt="Reference" class="w-28 h-28 rounded-xl object-cover ring-1 ring-line"></a>@endif @endforeach
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card title="History" subtitle="Every stage this order has actually moved through" icon="history">
                <x-ui.timeline :events="$this->timeline" />
            </x-ui.card>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Customer" icon="user">
                <dl class="rj-dl">
                    <div class="col-span-2"><dt>Name</dt><dd>{{ $o->customer?->name ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt>Phone</dt><dd>{{ $o->customer?->phone ?? '—' }}</dd></div>
                </dl>
                <x-ui.button variant="secondary" size="sm" iconRight="arrow-right" class="w-full mt-4" :href="route('portal.login')">Customer tracking portal</x-ui.button>
            </x-ui.card>

            <x-ui.card title="Confirmation message" icon="file-text" subtitle="Copy and send manually">
                <div class="rounded-xl bg-surface-bg ring-1 ring-inset ring-line-light p-3.5">
                    <pre class="text-[12px] font-mono text-ink_text-primary whitespace-pre-wrap leading-relaxed">{{ $this->confirmationMessage }}</pre>
                </div>
                <x-ui.button variant="secondary" size="sm" icon="copy" class="w-full mt-3"
                    x-on:click="navigator.clipboard.writeText(@js($this->confirmationMessage)); $dispatch('toast', { message: 'Message copied.', type: 'success' })">
                    Copy message
                </x-ui.button>
            </x-ui.card>

            <x-ui.card title="Details" icon="clipboard">
                <dl class="rj-dl">
                    <div><dt>Category</dt><dd>{{ $o->category ?: '—' }}</dd></div>
                    <div><dt>Placed</dt><dd>{{ $o->created_at->format('d M Y') }}</dd></div>
                    <div class="col-span-2">
                        <dt>Stock</dt>
                        <dd>
                            @if ($o->out_of_stock)
                                To be made
                            @elseif ($o->stockItem)
                                <a href="{{ route('stock.items.show', $o->stockItem) }}" class="rj-code text-gold-dark">{{ $o->stockItem->huid_code ?? $o->stockItem->internal_code }}</a>
                            @else
                                In stock
                            @endif
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt>Expected ready by</dt>
                        <dd>{{ $o->expected_ready_date?->format('d M Y') ?? 'Not set' }}</dd>
                    </div>
                    @if ($o->convertedSale)
                        <div class="col-span-2"><dt>Converted sale</dt><dd><a href="{{ route('sales.invoice', $o->convertedSale) }}" class="rj-code text-gold-dark">Sale #{{ $o->convertedSale->id }}</a></dd></div>
                    @endif
                </dl>
            </x-ui.card>
        </aside>
    </div>
</div>
