<div>
    <x-ui.page-header title="New Sale" subtitle="Customer, items, charges, payment, then review. You can go back to any step."
        :crumbs="[['label' => 'Sales & Billing', 'href' => route('sales.history')], ['label' => 'New Sale']]" />

    <x-ui.stepper :steps="$steps" :current="$step" :reach="$reach" />

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <div class="min-w-0">
            {{-- ============================================== 1. CUSTOMER --}}
            @if ($step === 1)
                <x-ui.card title="Who is buying" icon="user">
                    @if ($customer)
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gold-tint/50 ring-1 ring-inset ring-gold-soft/60">
                            <span class="w-10 h-10 rounded-full gold-sheen text-white flex items-center justify-center font-semibold">{{ mb_substr($customer->name, 0, 1) }}</span>
                            <div class="flex-1"><div class="font-semibold text-ink_text-primary">{{ $customer->name }}</div><div class="text-[12.5px] text-ink_text-secondary tabular">{{ $customer->phone }}</div></div>
                            <x-ui.button variant="ghost" size="sm" wire:click="clearCustomer">Change</x-ui.button>
                        </div>
                    @else
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <input id="sale-customer" type="text" wire:model.live.debounce.250ms="customerSearch" placeholder="Search by name or phone" class="rj-input" autocomplete="off" autofocus>
                        </div>
                        @if ($customerResults->isNotEmpty())
                            <ul class="mt-2 rounded-xl border border-line-light divide-y divide-line-light overflow-hidden">
                                @foreach ($customerResults as $c)
                                    <li><button type="button" wire:click="chooseCustomer({{ $c->id }})" class="w-full text-left px-4 py-2.5 hover:bg-surface-sunken flex justify-between"><span class="font-semibold">{{ $c->name }}</span><span class="text-ink_text-muted tabular">{{ $c->phone }}</span></button></li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-3"><x-ui.button variant="secondary" size="sm" icon="plus" wire:click="$toggle('addingCustomer')">New customer</x-ui.button></div>
                        @if ($addingCustomer)
                            <form wire:submit="saveNewCustomer" class="mt-4 p-4 rounded-xl ring-1 ring-inset ring-line-light grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3 items-end">
                                <x-ui.field label="Name" for="nc-name" error="newName"><input id="nc-name" type="text" wire:model="newName" class="rj-input" maxlength="100"></x-ui.field>
                                <x-ui.field label="Mobile number" for="nc-phone" error="newPhone"><input id="nc-phone" type="text" wire:model="newPhone" class="rj-input tabular" inputmode="numeric"></x-ui.field>
                                <x-ui.button type="submit" icon="check" target="saveNewCustomer">Add</x-ui.button>
                            </form>
                        @endif
                    @endif
                    @error('customerId')<p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror

                    <div class="mt-6 pt-5 border-t border-line-light">
                        <x-ui.field label="Referral code" for="sale-ref" error="referralCode" optional hint="If this customer was referred by someone, enter that person's code.">
                            <input id="sale-ref" type="text" wire:model.live.debounce.400ms="referralCode" class="rj-input rj-code uppercase max-w-[220px] @error('referralCode') is-invalid @enderror" autocomplete="off">
                        </x-ui.field>
                        @if ($referrer)<p class="text-[12.5px] text-success mt-2 flex items-center gap-1.5"><x-ui.icon name="check-circle" :size="13" /> Referred by {{ $referrer->name }}</p>@endif
                    </div>
                </x-ui.card>
            @endif

            {{-- ============================================== 2. ITEMS --}}
            @if ($step === 2)
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
                                    <div class="flex-1 min-w-0"><span class="rj-code text-ink_text-primary">{{ $line['label'] }}</span> <span class="text-[12.5px] text-ink_text-muted">{{ $line['category'] }} · {{ number_format($line['weight'], 3) }} g</span></div>
                                    <span class="tabular font-semibold">₹{{ number_format($line['price']) }}</span>
                                    <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removeItem({{ $id }})" aria-label="Remove {{ $line['label'] }}" />
                                </div>
                                @if ($line['inVault'])
                                    <div class="mt-2.5 flex flex-wrap items-center gap-3 px-3 py-2 rounded-lg bg-warning-bg text-warning text-[12.5px]">
                                        <x-ui.icon name="alert-triangle" :size="14" /> <span class="flex-1">Recorded as in the vault. It has to be at the counter before it can be sold.</span>
                                        <x-ui.button size="xs" variant="secondary" wire:click="moveToCounter({{ $id }})" target="moveToCounter">Move to counter</x-ui.button>
                                    </div>
                                @endif
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

            {{-- ============================================== 3. CHARGES --}}
            @if ($step === 3)
                <x-ui.card title="Charges and adjustment" icon="receipt">
                    <h3 class="text-[13px] font-bold mb-2">Additional charges on this sale</h3>
                    @foreach ($extras as $i => $e)
                        <div class="flex gap-2 mb-2" wire:key="ex-{{ $i }}">
                            <input type="text" wire:model.live.debounce.300ms="extras.{{ $i }}.name" placeholder="What for" class="rj-input flex-1" maxlength="60" aria-label="Charge name">
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="extras.{{ $i }}.amount" placeholder="₹" class="rj-input tabular w-[130px] @error('extras.' . $i . '.amount') is-invalid @enderror" aria-label="Charge amount">
                            <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removeExtra({{ $i }})" aria-label="Remove charge" />
                        </div>
                        @error('extras.' . $i . '.amount')<p class="rj-error">{{ $message }}</p>@enderror
                    @endforeach
                    <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="addExtra">Add a charge</x-ui.button>

                    <div class="mt-6 pt-5 border-t border-line-light">
                        <h3 class="text-[13px] font-bold mb-2">Adjustment (discount)</h3>
                        <div class="flex gap-2 max-w-[420px]">
                            <select wire:model.live="adjustType" class="rj-select w-auto" aria-label="Adjustment type"><option value="flat">Flat amount</option><option value="percent">Percentage</option></select>
                            <input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="adjustValue" class="rj-input tabular flex-1 @error('adjustValue') is-invalid @enderror" placeholder="{{ $adjustType === 'percent' ? '%' : '₹' }}" aria-label="Adjustment value">
                        </div>
                        @error('adjustValue')<p class="rj-error">{{ $message }}</p>@enderror
                        <p class="rj-help">No approval needed.</p>
                    </div>
                    <x-ui.field label="Notes" for="sale-notes" optional class="mt-5"><input id="sale-notes" type="text" wire:model="notes" maxlength="255" class="rj-input"></x-ui.field>
                </x-ui.card>
            @endif

            {{-- ============================================== 4. PAYMENT --}}
            @if ($step === 4)
                <x-ui.card title="Payment" subtitle="A bill can be paid in parts. Anything left stays as the balance." icon="coins">
                    <div class="space-y-2">
                        @foreach ($payments as $i => $p)
                            <div class="flex gap-2 items-start" wire:key="pay-{{ $i }}">
                                <select wire:model="payments.{{ $i }}.mode" class="rj-select w-[130px]" aria-label="Payment mode">@foreach ($modes as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                                <input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="payments.{{ $i }}.amount" placeholder="₹" class="rj-input tabular flex-1 @error('payments.' . $i . '.amount') is-invalid @enderror" aria-label="Amount">
                                <x-ui.button variant="secondary" size="sm" wire:click="fillBalance({{ $i }})">Rest</x-ui.button>
                                @if (count($payments) > 1)<x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="removePayment({{ $i }})" aria-label="Remove payment" />@endif
                            </div>
                        @endforeach
                    </div>
                    @error('payments')<p class="rj-error mt-2"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p>@enderror
                    <x-ui.button variant="secondary" size="sm" icon="plus" class="mt-3" wire:click="addPayment">Another payment</x-ui.button>
                    <div class="mt-5 flex justify-between text-[14px]"><span class="text-ink_text-secondary">Balance after these payments</span><span class="tabular font-semibold {{ $this->balance > 0 ? 'text-warning' : 'text-success' }}">₹{{ number_format($this->balance, 2) }}</span></div>
                </x-ui.card>
            @endif

            {{-- ============================================== 5. REVIEW --}}
            @if ($step === 5)
                <x-ui.card title="Review" subtitle="Check it, then save. An admin verifies it before it is final." icon="check-circle">
                    <dl class="rj-dl mb-4"><div><dt>Customer</dt><dd>{{ $customer?->name }} · <span class="tabular">{{ $customer?->phone }}</span></dd></div>
                        @if ($referrer)<div><dt>Referred by</dt><dd>{{ $referrer->name }}</dd></div>@endif</dl>
                    <x-ui.table :headers="['Piece', 'Category', 'Weight', 'Price']">
                        @foreach ($cart as $id => $line)<tr><td class="rj-code">{{ $line['label'] }}</td><td>{{ $line['category'] }}</td><td class="tabular">{{ number_format($line['weight'], 3) }} g</td><td class="tabular">₹{{ number_format($line['price']) }}</td></tr>@endforeach
                    </x-ui.table>
                    @if ($hasOrderWarning)<p class="text-[12.5px] text-info mt-3">Sold over an order hold. Note: {{ $overrideNote }}</p>@endif
                    @error('cart')<p class="rj-error mt-3">{{ $message }}</p>@enderror
                    <x-ui.button size="lg" icon="check" class="w-full mt-5" wire:click="submit" target="submit">Save sale</x-ui.button>
                </x-ui.card>
            @endif

            <div class="flex justify-between mt-5">
                <x-ui.button variant="secondary" icon="arrow-left" wire:click="goToStep({{ max(1, $step - 1) }})" :disabled="$step === 1">Back</x-ui.button>
                @if ($step < 5)<x-ui.button iconRight="arrow-right" wire:click="next" target="next">Continue</x-ui.button>@endif
            </div>
        </div>

        {{-- running figures --}}
        <x-ui.card title="This bill" icon="receipt" class="xl:sticky xl:top-24">
            <dl class="space-y-2 text-[13.5px]">
                <div class="flex justify-between"><dt class="text-ink_text-secondary">Pieces ({{ count($cart) }})</dt><dd class="tabular">₹{{ number_format($this->subtotal) }}</dd></div>
                @if ($this->extrasTotal > 0)<div class="flex justify-between"><dt class="text-ink_text-secondary">Additional charges</dt><dd class="tabular">₹{{ number_format($this->extrasTotal, 2) }}</dd></div>@endif
                @if ($this->adjustment > 0)<div class="flex justify-between text-success"><dt>Adjustment</dt><dd class="tabular">- ₹{{ number_format($this->adjustment, 2) }}</dd></div>@endif
                <div class="flex justify-between pt-3 border-t border-line-light font-display text-[24px] font-semibold"><dt>Total</dt><dd class="tabular">₹{{ number_format($this->total) }}</dd></div>
                @if ($reach >= 4)
                    <div class="flex justify-between"><dt class="text-ink_text-secondary">Paid now</dt><dd class="tabular">₹{{ number_format($this->paidTotal, 2) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Balance</dt><dd class="tabular {{ $this->balance > 0 ? 'text-warning' : 'text-success' }}">₹{{ number_format($this->balance, 2) }}</dd></div>
                @endif
            </dl>
        </x-ui.card>
    </div>
</div>
