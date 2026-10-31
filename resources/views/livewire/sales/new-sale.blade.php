<div>
    <x-ui.page-header title="New Sale" subtitle="Items, this bill, customer, payment, then review. You can go back to any step."
        :crumbs="[['label' => 'Sales & Billing', 'href' => route('sales.history')], ['label' => 'New Sale']]">
        <x-slot:actions>
            <x-ui.button variant="ghost" icon="x-circle" x-on:click="$dispatch('rj-confirm', { title: 'Cancel this sale?', message: 'Nothing has been saved yet. What you entered on this bill is thrown away.', confirm: 'Cancel the sale', tone: 'danger', action: () => $wire.cancelDraft() })">Cancel sale</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($exchangeCredit !== null)
        <div class="mb-6 flex items-center gap-3 p-4 rounded-card border bg-gold-tint/60 border-gold-soft text-[13px]">
            <x-ui.icon name="coins" :size="16" class="text-gold-dark shrink-0" />
            <span>Using <strong>₹{{ number_format($exchangeCredit, 2) }}</strong> of exchange credit from exchange #{{ $exchangeId }}. It is put on the payment step.</span>
        </div>
    @endif

    @if ($orderModel)
        <x-movement.order-banner :order="$orderModel" :full-url="route('sales.new')" full-label="Start a sale without the order" />
        @if ($waitingReview)
            <div class="mb-6 flex items-center gap-3 p-4 rounded-card border bg-warning-bg border-warning/20 text-[13px]">
                <x-ui.icon name="alert-triangle" :size="16" class="text-warning shrink-0" />
                <span><strong>{{ $waitingReview }} {{ \Illuminate\Support\Str::plural('piece', $waitingReview) }}</strong> made for this order {{ $waitingReview === 1 ? 'is' : 'are' }} still waiting for an admin to confirm {{ $waitingReview === 1 ? 'it' : 'them' }} into stock, so {{ $waitingReview === 1 ? 'it is' : 'they are' }} not in the cart yet.</span>
            </div>
        @endif
    @endif

    <x-ui.stepper :steps="$steps" :current="$step" :reach="$reach" />

    @if ($step === 2)
        <div class="min-w-0">
            {{-- ============================================== 2. THIS BILL (full screen) --}}
            @if ($step === 2)
                <x-ui.card title="This bill" subtitle="Every piece with its price, then the charges, the round off and the discount." icon="receipt">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach ($cart as $id => $line)
                            @include('livewire.sales.partials.piece-breakup', ['line' => $line, 'id' => $id])
                        @endforeach
                    </div>

                    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                        <div>
                            <h3 class="text-[13px] font-bold mb-1">Additional charges</h3>
                            <p class="rj-help !mt-0 mb-2.5">Added after GST, so they are not taxed.</p>
                            @foreach ($extras as $i => $e)
                                <div class="flex gap-2 mb-2" wire:key="ex-{{ $i }}">
                                    <input type="text" wire:model.live.debounce.300ms="extras.{{ $i }}.name" list="rj-charge-names" placeholder="What for, e.g. Sakha" class="rj-input flex-1" maxlength="60" aria-label="Charge name" autocomplete="off">
                                    <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="extras.{{ $i }}.amount" placeholder="₹" class="rj-input tabular w-[130px] @error('extras.' . $i . '.amount') is-invalid @enderror" aria-label="Charge amount" autocomplete="off">
                                    <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removeExtra({{ $i }})" aria-label="Remove charge" />
                                </div>
                                @if (! empty($e['item_id']) && isset($cart[$e['item_id']]))<p class="-mt-1 mb-2 text-[11.5px] text-ink_text-muted">Comes with {{ $cart[$e['item_id']]['label'] }}. You can change or remove it.</p>@endif
                                @error('extras.' . $i . '.amount')<p class="rj-error">{{ $message }}</p>@enderror
                            @endforeach
                            <datalist id="rj-charge-names">@foreach ($chargeNames as $cn)<option value="{{ $cn }}">@endforeach</datalist>
                            <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="addExtra">Add a charge</x-ui.button>

                            <x-ui.field label="Notes" for="sale-notes" optional class="mt-5"><input id="sale-notes" type="text" wire:model="notes" maxlength="255" class="rj-input"></x-ui.field>
                        </div>

                        <div>
                            <dl class="space-y-2 text-[13.5px]">
                                <div class="flex justify-between"><dt class="text-ink_text-secondary">Pieces ({{ count($cart) }})</dt><dd class="tabular">₹{{ number_format($gst['pieces'], 2) }}</dd></div>
                                @if ($gst['gst_jewellery'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">GST {{ rtrim(rtrim(number_format($gst['rates'][0], 2), '0'), '.') }}% on jewellery</dt><dd class="tabular">₹{{ number_format($gst['gst_jewellery'], 2) }}</dd></div>@endif
                                @if ($gst['gst_hallmark'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">GST {{ rtrim(rtrim(number_format($gst['rates'][1], 2), '0'), '.') }}% on hallmarking</dt><dd class="tabular">₹{{ number_format($gst['gst_hallmark'], 2) }}</dd></div>@endif
                                @if ($gst['extras'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Additional charges</dt><dd class="tabular">₹{{ number_format($gst['extras'], 2) }}</dd></div>@endif
                                <div class="flex justify-between"><dt class="text-ink_text-secondary">Round off <span class="text-[11.5px]">(automatic, to a whole rupee)</span></dt><dd class="tabular">{{ $gst['round_off'] >= 0 ? '+' : '-' }} ₹{{ number_format(abs($gst['round_off']), 2) }}</dd></div>
                                <div class="flex justify-between pt-2 border-t border-line-light font-semibold"><dt>Total before discount</dt><dd class="tabular">₹{{ number_format($gst['rounded_gross'], 2) }}</dd></div>
                            </dl>

                            <div class="mt-4 flex gap-2">
                                <select wire:model.live="finalType" class="rj-select w-auto" aria-label="Discount type"><option value="flat">Discount amount</option><option value="final">Final price to pay</option></select>
                                <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="finalValue" class="rj-input tabular flex-1 @error('finalValue') is-invalid @enderror" placeholder="Discount ₹" aria-label="Discount value" autocomplete="off">
                            </div>
                            @error('finalValue')<p class="rj-error mt-1">{{ $message }}</p>@enderror
                            <p class="rj-help">For example the customer will pay 14,500 on a bill of 14,576: type 76, or choose final price and type 14,500. It can not be more than ₹{{ number_format($gst['final_discount_cap'], 2) }}, the making charge left on this bill with its GST. No approval needed.</p>
                            @if ($gst['final_discount'] > 0)
                                <div class="mt-3 flex justify-between text-[14px] font-semibold text-success"><span>Discount</span><span class="tabular">- ₹{{ number_format($gst['final_discount'], 2) }}</span></div>
                            @endif
                            <div class="mt-3 pt-3 border-t border-line-light flex justify-between font-display text-[28px] font-semibold"><span>Total</span><span class="tabular">₹{{ number_format($this->total, 2) }}</span></div>
                        </div>
                    </div>
                </x-ui.card>
            @endif

            <div class="flex justify-between mt-5">
                <x-ui.button variant="secondary" icon="arrow-left" wire:click="goToStep({{ max(1, $step - 1) }})" :disabled="$step === 1">Back</x-ui.button>
                @if ($step < 5)<x-ui.button iconRight="arrow-right" wire:click="next" target="next">Continue</x-ui.button>@endif
            </div>
        </div>
    @else
    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <div class="min-w-0">
            {{-- ============================================== 1. ITEMS --}}
            @if ($step === 1)
                <x-ui.card title="What is being sold" subtitle="Scan or search. The price comes from today's rate and the pricing rules." icon="gem">
                    <form class="flex gap-2.5" x-on:submit.prevent="const v = $refs.scan.value; $refs.scan.value = ''; if (v.trim()) $wire.addByCode(v)">
                        <div class="rj-input-icon flex-1">
                            <x-ui.icon name="scan" :size="17" />
                            <input id="sale-item" x-ref="scan" type="text" wire:model.live.debounce.250ms="itemSearch" class="rj-input h-12" placeholder="HUID, code, category, packet or box" autocomplete="off" autofocus>
                        </div>
                        <x-ui.scan-button target="#sale-item" submit="form" variant="button" label="Camera" class="!h-12" />
                    </form>
                    @if ($itemResults->isNotEmpty())
                        <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">
                            @foreach ($itemResults as $i)
                                <li wire:key="ir-{{ $i->id }}"><button type="button" wire:click="addItem({{ $i->id }})" class="w-full text-left px-4 py-2.5 hover:bg-surface-sunken flex justify-between gap-3">
                                    <span><span class="rj-code">{{ $i->label }}</span> <span class="text-ink_text-muted text-[12.5px]">{{ $i->category }} · {{ number_format($i->weight, 3) }} g</span></span><x-ui.icon name="plus" :size="14" /></button></li>
                            @endforeach
                        </ul>
                    @endif
                    @error('cart')<p class="rj-error mt-3"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror

                    <div class="mt-5 space-y-2">
                        @forelse ($cart as $id => $line)
                            <div class="rounded-xl ring-1 ring-inset ring-line-light p-3.5" wire:key="cl-{{ $id }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 min-w-0"><span class="rj-code text-ink_text-primary">{{ $line['label'] }}</span> <span class="text-[12.5px] text-ink_text-muted">{{ $line['category'] }} · {{ number_format($line['weight_listed'], 3) }} g</span></div>
                                    <span class="tabular font-semibold">₹{{ number_format($line['price']) }}</span>
                                    <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removeItem({{ $id }})" aria-label="Remove {{ $line['label'] }}" />
                                </div>

                                {{-- the weight this bill prices the piece at, and the making charge: both are for this bill only, the piece is never changed --}}
                                @php
                                    $weightChanged = abs($line['weight'] - $line['weight_listed']) > 0.0004;
                                    $kind = $line['making_kind'];
                                    $unit = match ($kind) { 'percentage' => '%', 'per_gram' => '₹ / g', default => '₹ / piece' };
                                    $canBargain = $line['making_default'] > 0 && $line['making_min'] < $line['making_default'];
                                @endphp
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="w-{{ $id }}" class="block text-[12px] font-semibold text-ink_text-secondary mb-1">Weight to price (g)</label>
                                        <input id="w-{{ $id }}" type="text" wire:model.live.blur="cart.{{ $id }}.weight_input" placeholder="{{ number_format($line['weight_listed'], 3, '.', '') }}"
                                            class="rj-input rj-input-sm tabular @error('cart.' . $id . '.weight_input') is-invalid @enderror @if ($weightChanged) !border-warning @endif" inputmode="decimal">
                                        @error('cart.' . $id . '.weight_input')<p class="rj-error">{{ $message }}</p>@enderror
                                        @if ($weightChanged)
                                            <p class="mt-1 text-[11.5px] text-warning">The piece's own weight ({{ number_format($line['weight_listed'], 3) }} g) is not changed.</p>
                                            <input type="text" wire:model="cart.{{ $id }}.weight_note" maxlength="255" placeholder="Why (optional)" class="rj-input rj-input-sm mt-1.5" aria-label="Why the weight is different">
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-baseline justify-between gap-2 mb-1">
                                            <span class="text-[12px] font-semibold text-ink_text-secondary">Making charge</span>
                                            @if ($line['making_saved'] > 0)
                                                <button type="button" wire:click="resetMaking({{ $id }})" class="text-[11.5px] text-ink_text-muted underline hover:text-ink_text-primary">Back to {{ \App\Services\PricingService::rateText($kind, $line['making_default']) }}</button>
                                            @endif
                                        </div>
                                        @if ($line['making_default'] > 0)
                                            <div class="flex items-center gap-2" wire:key="mk-{{ $id }}-{{ $line['making_rate'] }}-{{ $line['making'] }}">
                                                <input type="text" value="{{ $line['making_rate'] }}" @disabled(! $canBargain)
                                                    x-on:change="$wire.setMaking({{ $id }}, 'rate', $event.target.value)" class="rj-input rj-input-sm tabular w-[84px]" aria-label="Making rate ({{ $unit }})" inputmode="decimal">
                                                <span class="text-[12px] text-ink_text-muted shrink-0">{{ $unit }}</span>
                                                <span class="text-ink_text-muted">=</span>
                                                <input type="text" value="{{ number_format($line['making'], 2, '.', '') }}" @disabled(! $canBargain)
                                                    x-on:change="$wire.setMaking({{ $id }}, 'amount', $event.target.value)" class="rj-input rj-input-sm tabular flex-1 min-w-[90px]" aria-label="Making charge (₹)" inputmode="decimal">
                                            </div>
                                            @if ($canBargain)
                                                <div x-data="{ r: {{ $line['making_rate'] }} }" wire:key="mks-{{ $id }}-{{ $line['making_rate'] }}" class="mt-2">
                                                    <input type="range" min="{{ $line['making_min'] }}" max="{{ $line['making_default'] }}" step="{{ $kind === 'percentage' ? 0.25 : ($kind === 'per_gram' ? 1 : 5) }}" x-model.number="r"
                                                        x-on:change="$wire.setMaking({{ $id }}, 'rate', r)" class="w-full accent-gold" aria-label="Making charge slider">
                                                    <div class="flex justify-between text-[11px] text-ink_text-muted tabular">
                                                        <span>{{ rtrim(rtrim(number_format($line['making_min'], 2), '0'), '.') }}{{ $unit === '%' ? '%' : '' }}</span>
                                                        <span x-text="r + '{{ $unit === '%' ? '%' : ' ' . $unit }}'" class="font-semibold text-ink_text-secondary"></span>
                                                        <span>{{ rtrim(rtrim(number_format($line['making_default'], 2), '0'), '.') }}{{ $unit === '%' ? '%' : '' }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                            @if ($line['making_saved'] > 0)
                                                <p class="mt-1 text-[12px] font-semibold text-danger">Making charge reduced by ₹{{ number_format($line['making_saved'], 2) }} (priced at {{ \App\Services\PricingService::rateText($kind, $line['making_default']) }})</p>
                                            @endif
                                        @else
                                            <p class="text-[12.5px] text-ink_text-muted">No making charge on this piece.</p>
                                        @endif
                                    </div>
                                </div>
                                @if ($line['order'])
                                    <div class="mt-2.5 flex items-center gap-2 px-3 py-2 rounded-lg bg-info-bg text-info text-[12.5px]"><x-ui.icon name="clipboard" :size="14" /> Held for a customer: {{ $line['order'] }}.</div>
                                @endif
                            </div>
                        @empty
                            <x-ui.empty-state icon="gem" title="No pieces yet" message="Scan a tag to put it on the bill." compact />
                        @endforelse
                    </div>

                    @if ($hasOrderWarning)
                        <div class="mt-5 p-4 rounded-xl ring-1 ring-inset ring-line-light">
                            @if ($canOverride)
                                <x-ui.field label="Why sell a piece held for an order?" for="sale-override" error="overrideNote" hint="An admin can override. This note is kept on the sale.">
                                    <input id="sale-override" type="text" wire:model="overrideNote" maxlength="255" class="rj-input">
                                </x-ui.field>
                            @else
                                <p class="text-[13px] text-warning">Only an admin can sell a piece held for an order. Ask an admin to do this sale.</p>
                            @endif
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- ============================================== 3. CUSTOMER --}}
            @if ($step === 3)
                <x-ui.card title="Who is buying" icon="user">
                    @if ($customer)
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gold-tint/50 ring-1 ring-inset ring-gold-soft/60">
                            <span class="w-10 h-10 rounded-full gold-sheen text-white flex items-center justify-center font-semibold">{{ mb_substr($customer->name, 0, 1) }}</span>
                            <div class="flex-1"><div class="font-semibold text-ink_text-primary">{{ $customer->name }}</div><div class="text-[12.5px] text-ink_text-secondary tabular">{{ $customer->is_walk_in ? 'No details taken' : $customer->phone }}</div></div>
                            <x-ui.button variant="ghost" size="sm" wire:click="clearCustomer">Change</x-ui.button>
                        </div>
                    @else
                        @if ($walkIn)
                            <button type="button" wire:click="chooseWalkIn" class="press w-full flex items-center gap-3 p-4 rounded-xl border border-line bg-white hover:border-line-strong hover:bg-surface-sunken text-left">
                                <span class="w-10 h-10 rounded-full bg-gold-tint text-gold-dark flex items-center justify-center shrink-0"><x-ui.icon name="user" :size="18" /></span>
                                <span class="flex-1 min-w-0"><span class="block font-semibold text-ink_text-primary">{{ $walkIn->name }}</span><span class="block text-[12.5px] text-ink_text-secondary">For a buyer who gives no details. It has its own ledger.</span></span>
                                <x-ui.icon name="chevron-right" :size="16" class="text-ink_text-muted" />
                            </button>
                            <div class="my-4 flex items-center gap-3 text-[12px] text-ink_text-muted"><span class="flex-1 h-px bg-line-light"></span>or find a customer<span class="flex-1 h-px bg-line-light"></span></div>
                        @endif
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <input id="sale-customer" type="text" wire:model.live.debounce.250ms="customerSearch" placeholder="Search by name or phone" class="rj-input" autocomplete="off">
                        </div>
                        @if ($customerResults->isNotEmpty())
                            <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">
                                @foreach ($customerResults as $c)
                                    <li><button type="button" wire:click="chooseCustomer({{ $c->id }})" class="w-full text-left px-4 py-2.5 hover:bg-surface-sunken flex justify-between"><span class="font-semibold">{{ $c->name }}</span><span class="text-ink_text-muted tabular">{{ $c->phone }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-3"><x-ui.button variant="secondary" size="sm" icon="plus" x-on:click="Livewire.dispatch('open-customer-form', { name: $wire.customerSearch })">New customer</x-ui.button></div>
                    @endif
                    @error('customerId')<p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror

                    @unless ($customer?->is_walk_in)
                        <div class="mt-6 pt-5 border-t border-line-light">
                            <x-ui.field label="Referral code" for="sale-ref" error="referralCode" optional hint="If this customer was referred by someone, enter that person's code.">
                                <input id="sale-ref" type="text" wire:model.live.debounce.400ms="referralCode" class="rj-input rj-code uppercase max-w-[220px] @error('referralCode') is-invalid @enderror" autocomplete="off">
                            </x-ui.field>
                            @if ($referrer)<p class="text-[12.5px] text-success mt-2 flex items-center gap-1.5"><x-ui.icon name="check-circle" :size="13" /> Referred by {{ $referrer->name }}</p>@endif
                        </div>
                    @endunless
                </x-ui.card>
            @endif

            {{-- ============================================== 4. PAYMENT --}}
            @if ($step === 4)
                <x-ui.card title="Payment" subtitle="A bill can be paid in parts. Anything left stays as the balance." icon="coins">
                    <div class="space-y-2">
                        @foreach ($payments as $i => $p)
                            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-2 sm:flex sm:flex-wrap" wire:key="pay-{{ $i }}">
                                @if (($p['mode'] ?? '') === 'exchange' || ($p['mode'] ?? '') === 'advance')
                                    <span class="rj-select w-[130px] flex items-center text-[13px] bg-gold-tint/50 !cursor-default">{{ $p['mode'] === 'advance' ? 'Advance' : 'Exchange credit' }}</span>
                                @elseif (! array_key_exists($p['mode'] ?? '', $modes))
                                    <span class="rj-select w-[130px] flex items-center text-[13px] bg-surface-muted !cursor-default" title="Taken before cash and bank were the only choices">{{ $modeLabels[$p['mode']] ?? $p['mode'] }}</span>
                                @else
                                    <x-ui.mode-picker :model="'payments.' . $i . '.mode'" :value="$p['mode'] ?? 'cash'" :modes="$modes" />
                                @endif
                                <input type="text" inputmode="decimal" wire:model.live.debounce.300ms="payments.{{ $i }}.amount" placeholder="₹" class="rj-input h-11 tabular col-span-3 order-last sm:order-none sm:col-span-1 sm:flex-1 sm:min-w-[140px] @error('payments.' . $i . '.amount') is-invalid @enderror" aria-label="Amount" autocomplete="off">
                                <x-ui.button variant="secondary" size="sm" class="justify-self-start" wire:click="fillBalance({{ $i }})">Rest</x-ui.button>
                                @if (count($payments) > 1)<x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removePayment({{ $i }})" aria-label="Remove payment" />@endif
                            </div>
                        @endforeach
                    </div>
                    @error('payments')<p class="rj-error mt-2"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror
                    @foreach ($payments as $i => $p)@error('payments.' . $i . '.amount')<p class="rj-error mt-2">{{ $message }}</p>@enderror @endforeach
                    @foreach ($credits as $x)
                        <div class="mt-4 flex flex-wrap items-center gap-3 p-3.5 rounded-xl bg-gold-tint/50 ring-1 ring-inset ring-gold-soft/60" wire:key="credit-{{ $x->id }}">
                            <x-ui.icon name="coins" :size="16" class="text-gold-dark" />
                            <div class="flex-1 min-w-[200px] text-[13px]"><strong>₹{{ number_format($x->credit, 2) }}</strong> exchange credit from exchange #{{ $x->id }}<span class="text-ink_text-muted"> · settled {{ $x->settled_at?->format('j M') }}</span></div>
                            <x-ui.button variant="secondary" size="sm" wire:click="useCredit({{ $x->id }})">Use it on this bill</x-ui.button>
                        </div>
                    @endforeach
                    @if ($advanceHeld > 0)
                        <div class="mt-4 flex flex-wrap items-center gap-3 p-3.5 rounded-xl bg-gold-tint/50 ring-1 ring-inset ring-gold-soft/60">
                            <x-ui.icon name="coins" :size="16" class="text-gold-dark" />
                            <div class="flex-1 min-w-[200px] text-[13px]"><strong>₹{{ number_format($advanceHeld, 2) }}</strong> advance held on this customer's account</div>
                            <x-ui.button variant="secondary" size="sm" wire:click="useAdvance">Use it on this bill</x-ui.button>
                        </div>
                    @endif
                    <x-ui.button variant="secondary" size="sm" icon="plus" class="mt-3" wire:click="addPayment">Another payment</x-ui.button>
                    <div class="mt-5 flex justify-between text-[14px]"><span class="text-ink_text-secondary">Balance after these payments</span><span class="tabular font-semibold {{ $this->balance > 0 ? 'text-warning' : 'text-success' }}">₹{{ number_format($this->balance, 2) }}</span></div>
                </x-ui.card>
            @endif

            {{-- ============================================== 5. REVIEW --}}
            @if ($step === 5)
                <x-ui.card title="Review" subtitle="Check it, then save. An admin verifies it before it is final." icon="check-circle">
                    <dl class="rj-dl mb-4"><div><dt>Customer</dt><dd>{{ $customer?->name }}@unless ($customer?->is_walk_in) · <span class="tabular">{{ $customer?->phone }}</span>@endunless</dd></div>
                        @if ($referrer)<div><dt>Referred by</dt><dd>{{ $referrer->name }}</dd></div>@endif</dl>
                    <div class="space-y-3">
                        @foreach ($cart as $id => $line)
                            @include('livewire.sales.partials.piece-breakup', ['line' => $line, 'id' => $id])
                        @endforeach
                    </div>
                    <dl class="mt-4 space-y-1.5 text-[13.5px] max-w-[360px] ml-auto">
                        <div class="flex justify-between"><dt class="text-ink_text-secondary">Pieces</dt><dd class="tabular">₹{{ number_format($gst['pieces'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink_text-secondary">CGST + SGST on jewellery ({{ rtrim(rtrim(number_format($gst['rates'][0], 2), '0'), '.') }}%)</dt><dd class="tabular">₹{{ number_format($gst['gst_jewellery'], 2) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink_text-secondary">CGST + SGST on hallmarking ({{ rtrim(rtrim(number_format($gst['rates'][1], 2), '0'), '.') }}%)</dt><dd class="tabular">₹{{ number_format($gst['gst_hallmark'], 2) }}</dd></div>
                        @if ($gst['extras'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Additional charges</dt><dd class="tabular">₹{{ number_format($gst['extras'], 2) }}</dd></div>@endif
                        @if ($gst['round_off'] != 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Round off</dt><dd class="tabular">{{ $gst['round_off'] > 0 ? '+' : '-' }} ₹{{ number_format(abs($gst['round_off']), 2) }}</dd></div>@endif
                        @if ($gst['final_discount'] > 0)<div class="flex justify-between text-success"><dt>Discount</dt><dd class="tabular">- ₹{{ number_format($gst['final_discount'], 2) }}</dd></div>@endif
                        <div class="flex justify-between pt-2 border-t border-line-light font-semibold"><dt>Total to pay</dt><dd class="tabular">₹{{ number_format($this->total, 2) }}</dd></div>
                    </dl>
                    @if ($hasOrderWarning)<p class="text-[12.5px] text-info mt-3">Sold over an order hold. Note: {{ $overrideNote }}</p>@endif
                    @error('cart')<p class="rj-error mt-3">{{ $message }}</p>@enderror
                    <div x-data="{ copied: false, text: @js($copyText), async copy() { try { await navigator.clipboard.writeText(this.text); } catch (e) { const a = document.createElement('textarea'); a.value = this.text; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e2) {} a.remove(); } this.copied = true; setTimeout(() => this.copied = false, 2200); } }" class="mt-5 flex flex-col sm:flex-row gap-2.5">
                        <x-ui.button size="lg" variant="secondary" icon="copy" class="sm:w-auto" x-on:click="copy()"><span x-text="copied ? 'Copied' : 'Copy details'">Copy details</span></x-ui.button>
                        <x-ui.button size="lg" icon="check" class="flex-1" wire:click="submit" target="submit">Save sale</x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            <div class="flex justify-between mt-5">
                <x-ui.button variant="secondary" icon="arrow-left" wire:click="goToStep({{ max(1, $step - 1) }})" :disabled="$step === 1">Back</x-ui.button>
                @if ($step < 5)<x-ui.button iconRight="arrow-right" wire:click="next" target="next">Continue</x-ui.button>@endif
            </div>
        </div>

        {{-- running figures --}}
        <x-ui.card title="This bill" icon="receipt" class="xl:sticky xl:top-24">
            @foreach ($cart as $id => $line)
                <div class="mb-4 pb-3 border-b border-line-light" wire:key="bk-{{ $id }}">
                    <div class="flex justify-between gap-3 mb-1.5 text-[13px]"><span class="rj-code text-ink_text-primary">{{ $line['label'] }}</span><span class="text-ink_text-muted">{{ number_format($line['weight'], 3) }} g</span></div>
                    <div class="space-y-1 text-[12.5px]">
                        @foreach ($line['breakup'] ?? [] as $bl)
                            <div class="flex justify-between gap-3"><span>{{ $bl['label'] }}@if ($bl['note'])<span class="text-ink_text-muted text-[11.5px]"> · {{ $bl['note'] }}</span>@endif</span><span class="tabular whitespace-nowrap {{ $bl['amount'] < 0 ? 'text-success' : '' }}">{{ $bl['amount'] < 0 ? '- ' : '' }}₹{{ number_format(abs($bl['amount']), 2) }}</span></div>
                        @endforeach
                        <div class="flex justify-between gap-3 pt-1 border-t border-line-light font-semibold"><span>Piece price before GST</span><span class="tabular">₹{{ number_format($line['price'], 2) }}</span></div>
                    </div>
                </div>
            @endforeach
            <dl class="space-y-2 text-[13.5px]">
                <div class="flex justify-between"><dt class="text-ink_text-secondary">Pieces ({{ count($cart) }})</dt><dd class="tabular">₹{{ number_format($gst['pieces'], 2) }}</dd></div>
                @if ($gst['gst_jewellery'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">GST {{ rtrim(rtrim(number_format($gst['rates'][0], 2), '0'), '.') }}% on jewellery</dt><dd class="tabular">₹{{ number_format($gst['gst_jewellery'], 2) }}</dd></div>@endif
                @if ($gst['gst_hallmark'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">GST {{ rtrim(rtrim(number_format($gst['rates'][1], 2), '0'), '.') }}% on hallmarking</dt><dd class="tabular">₹{{ number_format($gst['gst_hallmark'], 2) }}</dd></div>@endif
                @if ($gst['extras'] > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Additional charges</dt><dd class="tabular">₹{{ number_format($gst['extras'], 2) }}</dd></div>@endif
                @if ($gst['round_off'] != 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Round off</dt><dd class="tabular">{{ $gst['round_off'] > 0 ? '+' : '-' }} ₹{{ number_format(abs($gst['round_off']), 2) }}</dd></div>@endif
                        @if ($gst['final_discount'] > 0)<div class="flex justify-between text-success"><dt>Discount</dt><dd class="tabular">- ₹{{ number_format($gst['final_discount'], 2) }}</dd></div>@endif
                <div class="flex justify-between pt-3 border-t border-line-light font-display text-[24px] font-semibold"><dt>Total</dt><dd class="tabular">₹{{ number_format($this->total, 2) }}</dd></div>
                @if ($reach >= 4)
                    <div class="flex justify-between"><dt class="text-ink_text-secondary">Paid now</dt><dd class="tabular">₹{{ number_format($this->paidTotal, 2) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Balance</dt><dd class="tabular {{ $this->balance > 0 ? 'text-warning' : 'text-success' }}">₹{{ number_format($this->balance, 2) }}</dd></div>
                @endif
            </dl>
        </x-ui.card>
    </div>
    @endif


    {{-- A piece still in the vault: a popup, and nothing moves until Move to Counter is pressed --}}
    <x-ui.modal wire:model="showVaultMove" title="Move to Counter" icon="repeat" max-width="md"
        subtitle="These pieces are recorded as in the vault. They have to be at the counter before they can be sold.">
        <ul class="divide-y divide-line-light rounded-xl border border-line-light overflow-hidden">
            @foreach ($vaultPieces as $vp)
                <li class="flex items-center gap-3 px-3.5 py-2.5" wire:key="vp-{{ $vp['id'] }}"><span class="rj-code">{{ $vp['label'] }}</span><span class="text-[12.5px] text-ink_text-muted">{{ $vp['category'] }}</span></li>
            @endforeach
        </ul>
        @cannot('movement.create')
            <p class="mt-3 text-[12.5px] text-warning">You can not move stock. Ask someone who can, then come back to this bill.</p>
        @endcannot
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Not now</x-ui.button>
            @can('movement.create')
                <x-ui.button icon="repeat" wire:click="moveVaultPiecesToCounter" target="moveVaultPiecesToCounter">Move to Counter</x-ui.button>
            @endcan
        </x-slot:footer>
    </x-ui.modal>

    <livewire:customers.customer-form />
</div>
