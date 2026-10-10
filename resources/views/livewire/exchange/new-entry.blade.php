<div>
    <x-ui.page-header title="New Exchange Entry" subtitle="A guided, one-way flow for old gold/silver taken in. Each step locks in before the next opens."
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'New Entry']]" />

    <x-ui.stepper :steps="['1' => 'Received', '2' => 'Melted', '3' => 'Tested', '4' => 'Deduction', '5' => 'Summary']" :current="$step" />

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card :padding="true">

            {{-- ==================================================== Step 1: Received --}}
            @if ($step === 1)
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-9 h-9 rounded-xl bg-gold-tint text-gold-dark flex items-center justify-center"><x-ui.icon name="inbox" :size="17" /></span>
                    <div>
                        <div class="font-display text-[20px] font-semibold leading-tight">As received from the customer</div>
                        <div class="text-[12.5px] text-ink_text-secondary">Step 1 of 5</div>
                    </div>
                </div>

                <div class="relative" x-data="{ open: true }" x-on:click.outside="open = false">
                    <x-ui.field label="Customer" error="customerId">
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <input type="text" wire:model.live.debounce.300ms="customerSearch" x-on:focus="open = true" x-on:input="open = true"
                                autocomplete="off" placeholder="Search by name or phone..." class="rj-input">
                        </div>
                    </x-ui.field>

                    @if ($customerId && ! $customerSearch)
                        <div class="mt-2 flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-gold-tint ring-1 ring-gold-soft">
                            <x-ui.icon name="user-check" :size="15" class="text-gold-dark" />
                            <span class="text-[13px] font-semibold text-ink_text-primary">Customer selected</span>
                        </div>
                    @endif

                    @if ($customerSearch && $customerResults->isNotEmpty())
                        <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                            @foreach ($customerResults as $c)
                                <button type="button" wire:click="$set('customerId', {{ $c->id }})"
                                    class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left {{ $customerId === $c->id ? 'bg-gold-tint' : '' }}">
                                    <span class="w-8 h-8 rounded-full bg-surface-sunken ring-1 ring-inset ring-line-light flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="user" :size="14" /></span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-[13px] font-semibold text-ink_text-primary truncate">{{ $c->name }}</span>
                                        <span class="block text-[12px] text-ink_text-muted">{{ $c->phone }}</span>
                                    </span>
                                    @if ($customerId === $c->id) <x-ui.icon name="check" :size="14" class="text-gold-dark shrink-0" /> @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <x-ui.field label="Gross weight, as received" for="ne-gross" error="grossWeight" hint="Grams, before anything is melted">
                        <div class="rj-input-icon">
                            <x-ui.icon name="scale" :size="16" />
                            <input id="ne-gross" type="number" step="0.001" min="0" wire:model="grossWeight" class="rj-input tabular">
                        </div>
                    </x-ui.field>
                    <x-ui.field label="Description" for="ne-desc" optional>
                        <input id="ne-desc" type="text" wire:model="description" placeholder="e.g. broken chain, mixed studs" class="rj-input">
                    </x-ui.field>
                </div>

                <x-ui.button wire:click="next" iconRight="arrow-right" class="w-full mt-6">Continue to Melting</x-ui.button>
            @endif

            {{-- ==================================================== Step 2: Melted --}}
            @if ($step === 2)
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-9 h-9 rounded-xl bg-gold-tint text-gold-dark flex items-center justify-center"><x-ui.icon name="flame" :size="17" /></span>
                    <div>
                        <div class="font-display text-[20px] font-semibold leading-tight">Net weight after melting</div>
                        <div class="text-[12.5px] text-ink_text-secondary">Step 2 of 5</div>
                    </div>
                </div>

                <div class="rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light px-4 py-3 mb-5 flex items-center justify-between">
                    <span class="text-[12.5px] text-ink_text-secondary">Gross weight received</span>
                    <span class="font-display text-[20px] font-semibold tabular">{{ number_format($grossWeight, 3) }}<span class="text-[13px] text-ink_text-muted ml-1">g</span></span>
                </div>

                <x-ui.field label="Net weight, after melting" for="ne-net" error="netWeight" hint="The shop's own measurement is authoritative">
                    <div class="rj-input-icon">
                        <x-ui.icon name="scale" :size="16" />
                        <input id="ne-net" type="number" step="0.001" min="0" wire:model="netWeight" class="rj-input tabular">
                    </div>
                </x-ui.field>

                <div class="flex gap-2.5 mt-6">
                    <x-ui.button wire:click="back" variant="secondary" icon="arrow-left">Back</x-ui.button>
                    <x-ui.button wire:click="next" iconRight="arrow-right" class="flex-1">Continue to Testing</x-ui.button>
                </div>
            @endif

            {{-- ==================================================== Step 3: Tested --}}
            @if ($step === 3)
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-9 h-9 rounded-xl bg-gold-tint text-gold-dark flex items-center justify-center"><x-ui.icon name="shield-check" :size="17" /></span>
                    <div>
                        <div class="font-display text-[20px] font-semibold leading-tight">Two independent purity tests</div>
                        <div class="text-[12.5px] text-ink_text-secondary">Step 3 of 5</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <x-ui.field label="Test 1" for="ne-p1" error="purityTest1">
                        <div class="rj-input-icon">
                            <x-ui.icon name="percent" :size="16" />
                            <input id="ne-p1" type="number" step="0.01" min="0" max="100" wire:model.live="purityTest1" class="rj-input tabular">
                        </div>
                    </x-ui.field>
                    <x-ui.field label="Test 2" for="ne-p2" error="purityTest2">
                        <div class="rj-input-icon">
                            <x-ui.icon name="percent" :size="16" />
                            <input id="ne-p2" type="number" step="0.01" min="0" max="100" wire:model.live="purityTest2" class="rj-input tabular">
                        </div>
                    </x-ui.field>
                </div>

                <div class="rounded-xl bg-gold-tint ring-1 ring-inset ring-gold-soft px-4 py-3.5 mb-2 flex items-center justify-between">
                    <span class="text-[12.5px] font-semibold text-gold-dark">System-computed average</span>
                    <span class="font-display text-[24px] font-semibold tabular text-ink_text-primary">{{ $this->averagePurity }}<span class="text-[14px] text-gold-dark ml-0.5">%</span></span>
                </div>

                <div class="flex gap-2.5 mt-6">
                    <x-ui.button wire:click="back" variant="secondary" icon="arrow-left">Back</x-ui.button>
                    <x-ui.button wire:click="next" iconRight="arrow-right" class="flex-1">Continue to Deduction</x-ui.button>
                </div>
            @endif

            {{-- ==================================================== Step 4: Deduction --}}
            @if ($step === 4)
                <div class="flex items-center gap-2.5 mb-1.5">
                    <span class="w-9 h-9 rounded-xl bg-gold-tint text-gold-dark flex items-center justify-center"><x-ui.icon name="percent" :size="17" /></span>
                    <div>
                        <div class="font-display text-[20px] font-semibold leading-tight">Shop's preset deduction</div>
                        <div class="text-[12.5px] text-ink_text-secondary">Step 4 of 5</div>
                    </div>
                </div>
                <p class="text-[12.5px] text-ink_text-muted mb-4">Applied automatically — not typed per transaction.</p>

                <dl class="rj-dl bg-surface-sunken ring-1 ring-inset ring-line-light rounded-xl px-4 !py-3.5">
                    <div><dt>Net weight</dt><dd class="tabular">{{ number_format($netWeight, 3) }} g</dd></div>
                    <div><dt>Preset deduction</dt><dd class="tabular">{{ number_format($presetDeductionPercent, 2) }}%</dd></div>
                    <div class="col-span-2 pt-2 mt-1 border-t border-line-light">
                        <dt class="font-bold text-ink_text-primary">Net payable weight</dt>
                        <dd class="font-display text-[22px] font-semibold tabular text-ink_text-primary">{{ number_format($this->deductedWeight, 3) }} g</dd>
                    </div>
                </dl>

                <div class="flex gap-2.5 mt-6">
                    <x-ui.button wire:click="back" variant="secondary" icon="arrow-left">Back</x-ui.button>
                    <x-ui.button wire:click="next" iconRight="arrow-right" class="flex-1">Compile Summary</x-ui.button>
                </div>
            @endif

            {{-- ==================================================== Step 5: Summary --}}
            @if ($step === 5)
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-9 h-9 rounded-xl bg-success-bg text-success flex items-center justify-center"><x-ui.icon name="check-circle" :size="17" /></span>
                    <div>
                        <div class="font-display text-[20px] font-semibold leading-tight">Ready to send to accounts</div>
                        <div class="text-[12.5px] text-ink_text-secondary">Transaction #{{ $transactionId }} · stage "Tested"</div>
                    </div>
                </div>

                <div class="rounded-xl bg-surface-bg ring-1 ring-inset ring-line-light p-4">
                    <pre class="text-[12.5px] font-mono text-ink_text-primary whitespace-pre-wrap leading-relaxed">{{ $this->summaryText }}</pre>
                </div>

                <div class="flex gap-2.5 mt-6">
                    <x-ui.button wire:click="back" variant="secondary" icon="arrow-left">Back</x-ui.button>
                    <x-ui.button variant="primary" icon="copy" class="flex-1"
                        x-on:click="navigator.clipboard.writeText(@js($this->summaryText)); $dispatch('toast', { message: 'Summary copied.', type: 'success' })">
                        Copy Summary for Accounts
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>

        {{-- Live running total --}}
        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="This transaction" icon="scale">
                <dl class="rj-dl">
                    <div><dt>Gross received</dt><dd class="tabular">{{ $step >= 1 ? number_format($grossWeight, 3) . ' g' : '—' }}</dd></div>
                    <div><dt>Net after melt</dt><dd class="tabular">{{ $step >= 2 ? number_format($netWeight, 3) . ' g' : '—' }}</dd></div>
                    <div><dt>Avg. purity</dt><dd class="tabular">{{ $step >= 3 && $this->averagePurity ? $this->averagePurity . '%' : '—' }}</dd></div>
                    <div><dt>Deduction</dt><dd class="tabular">{{ $step >= 4 ? number_format($presetDeductionPercent, 2) . '%' : '—' }}</dd></div>
                    <div class="col-span-2 pt-2 mt-1 border-t border-line-light">
                        <dt class="font-bold text-ink_text-primary">Net payable weight</dt>
                        <dd class="font-display text-[22px] font-semibold tabular text-ink_text-primary">{{ $step >= 4 ? number_format($this->deductedWeight, 3) . ' g' : '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Why so many steps?
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    Gross weight, melt result and both purity readings are kept as separate steps on purpose — nothing here is combined or estimated, so every figure can be checked later against exactly what was measured.
                </p>
            </div>
        </aside>
    </div>
</div>
