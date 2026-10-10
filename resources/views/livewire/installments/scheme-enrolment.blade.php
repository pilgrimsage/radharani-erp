<div>
    <x-ui.page-header title="Scheme Enrolment" subtitle="Enrol a customer into the monthly installment scheme.">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="list" :href="route('installments.list')">Scheme list</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card>
            <div class="relative mb-4" x-data="{ open: true }" x-on:click.outside="open = false">
                <x-ui.field label="Customer" error="customerId">
                    @if ($this->customerObject)
                        <div class="rj-input w-full flex justify-between items-center">
                            <span class="text-ink_text-primary">{{ $this->customerObject->name }} ({{ $this->customerObject->phone }})</span>
                            <button type="button" wire:click="$set('customerId', null)" class="text-gold font-semibold text-xs">Change</button>
                        </div>
                    @else
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <input type="text" wire:model.live.debounce.300ms="customerSearch" x-on:focus="open = true" x-on:input="open = true"
                                autocomplete="off" placeholder="Search by name or phone..." class="rj-input">
                        </div>
                    @endif
                </x-ui.field>

                @if ($customerSearch && ! $this->customerObject)
                    <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                        @forelse ($customerResults as $c)
                            <button type="button" wire:click="pickCustomer({{ $c->id }})"
                                class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left">
                                <span class="w-8 h-8 rounded-full bg-surface-sunken ring-1 ring-inset ring-line-light flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="user" :size="14" /></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-semibold text-ink_text-primary truncate">{{ $c->name }}</span>
                                    <span class="block text-[12px] text-ink_text-muted">{{ $c->phone }}</span>
                                </span>
                            </button>
                        @empty
                            <div class="px-2.5 py-2 text-[12.5px] text-ink_text-secondary">No matches.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <x-ui.field label="Monthly amount (₹)" for="se-amount" error="monthlyAmount">
                    <div class="rj-input-icon">
                        <x-ui.icon name="coins" :size="16" />
                        <input id="se-amount" type="number" step="0.01" wire:model="monthlyAmount" class="rj-input tabular">
                    </div>
                </x-ui.field>
                <x-ui.field label="Start date" for="se-start" error="startDate">
                    <input id="se-start" type="date" wire:model="startDate" class="rj-input w-full">
                </x-ui.field>
                <x-ui.field label="Months in the scheme" for="se-months" error="totalMonths" hint="The completion date is the start date plus these months.">
                    <input id="se-months" type="number" min="1" max="60" wire:model="totalMonths" class="rj-input tabular">
                </x-ui.field>
            </div>

            <div class="rounded-xl ring-1 ring-inset ring-line-light p-4 mb-5">
                <label class="flex items-center gap-2.5 text-[13.5px] font-semibold"><input type="checkbox" wire:model.live="existingMember" class="rj-checkbox"> Already a member</label>
                @if ($existingMember)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <x-ui.field label="Months already paid" for="se-paid" error="monthsAlreadyPaid"><input id="se-paid" type="number" min="0" wire:model="monthsAlreadyPaid" class="rj-input tabular"></x-ui.field>
                        <x-ui.field label="Amount still pending (₹)" for="se-pending" error="amountPending"><input id="se-pending" type="number" step="0.01" min="0" wire:model="amountPending" class="rj-input tabular"></x-ui.field>
                    </div>
                @endif
            </div>

            <x-ui.button type="button" wire:click="enrol" target="enrol" variant="primary" icon="check">Enrol</x-ui.button>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="Recent enrolments" icon="calendar">
                @if ($recentEnrolments->isEmpty())
                    <p class="text-[12.5px] text-ink_text-secondary">Enrolments you record will show up here.</p>
                @else
                    <dl class="rj-dl">
                        @foreach ($recentEnrolments as $s)
                            <div>
                                <dt>{{ $s->customer->name ?? '—' }}</dt>
                                <dd class="tabular">₹{{ number_format($s->monthly_amount, 2) }}/mo</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Fully manual
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    No automatic payment detection — each month's payment is marked from Monthly Payment Status by staff, so a partial or late payment is always traceable to a real entry.
                </p>
            </div>
        </aside>
    </div>
</div>
