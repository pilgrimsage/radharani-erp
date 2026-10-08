<div>
    <x-ui.page-header title="New Purchase Entry" subtitle="Admin only. Record a purchase from a karigar or supplier."
        :crumbs="[['label' => 'Purchases & Vendors', 'href' => route('purchases.list')], ['label' => 'New Purchase']]">
        <x-slot:actions>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start">
        <x-ui.card>
            <form wire:submit="save">
                {{-- Finished-product purchases hidden: finished goods enter through import (8 Oct change list, 13.1). --}}

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <x-ui.field label="Vendor" for="np-vendor" error="vendorId">
                        <select id="np-vendor" class="rj-select w-full @error('vendorId') is-invalid @enderror" wire:model="vendorId">
                            <option value="">Select vendor…</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->name }} ({{ str($v->type)->replace('_', ' ')->title() }})</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Invoice / Bill No." for="np-invoice" optional>
                        <input id="np-invoice" type="text" class="rj-input w-full" wire:model="invoiceNumber">
                    </x-ui.field>
                    <x-ui.field label="GST (₹)" for="np-gst" optional>
                        <div class="rj-input-icon">
                            <x-ui.icon name="percent" :size="16" />
                            <input id="np-gst" type="number" step="0.01" class="rj-input tabular" wire:model="gst">
                        </div>
                    </x-ui.field>
                    <x-ui.field label="Payment status" for="np-status">
                        <select id="np-status" class="rj-select w-full" wire:model="paymentStatus">
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                    </x-ui.field>
                </div>

                @if ($purchaseType === 'raw_material')
                    <div class="rounded-xl bg-gold-tint ring-1 ring-inset ring-gold-soft px-4 py-3 mb-4 flex items-start gap-2.5">
                        <x-ui.icon name="info" :size="14" class="text-gold-dark shrink-0 mt-0.5" />
                        <p class="text-[12.5px] text-gold-dark leading-relaxed">
                            Raw material arrives untagged. Add a description line per lot below — each is saved as a "pending tag" purchase line with no item yet. Convert them into real items from the Pending Tags section on Stock → Items.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                        <x-ui.field label="Total weight (g)" for="np-tweight" error="totalWeight">
                            <div class="rj-input-icon">
                                <x-ui.icon name="scale" :size="16" />
                                <input id="np-tweight" type="number" step="0.001" class="rj-input tabular @error('totalWeight') is-invalid @enderror" wire:model="totalWeight">
                            </div>
                        </x-ui.field>
                        <x-ui.field label="Total amount (₹)" for="np-tamount" error="totalAmount">
                            <div class="rj-input-icon">
                                <x-ui.icon name="coins" :size="16" />
                                <input id="np-tamount" type="number" step="0.01" class="rj-input tabular @error('totalAmount') is-invalid @enderror" wire:model="totalAmount">
                            </div>
                        </x-ui.field>
                    </div>

                    <div class="font-semibold text-[13px] text-ink_text-primary mb-2.5">Lines (pending tag)</div>
                    <div class="space-y-3 mb-4">
                        @foreach ($rawLines as $i => $line)
                            <div class="grid grid-cols-2 sm:grid-cols-[2fr_1fr_1fr_1fr_1fr_1fr_auto] gap-2.5 items-end p-3 rounded-xl border border-line-light bg-surface-sunken/50">
                                <x-ui.field label="Description">
                                    <input type="text" class="rj-input w-full" wire:model="rawLines.{{ $i }}.description">
                                </x-ui.field>
                                <x-ui.field label="Category">
                                    <input type="text" class="rj-input w-full" wire:model="rawLines.{{ $i }}.category">
                                </x-ui.field>
                                <x-ui.field label="Metal">
                                    <select class="rj-select w-full" wire:model="rawLines.{{ $i }}.metal">
                                        <option value="gold">Gold</option>
                                        <option value="silver">Silver</option>
                                        <option value="titanium">Titanium</option>
                                        <option value="platinum">Platinum</option>
                                    </select>
                                </x-ui.field>
                                <x-ui.field label="Purity">
                                    <input type="text" class="rj-input w-full" placeholder="22K / 92.5" wire:model="rawLines.{{ $i }}.purity">
                                </x-ui.field>
                                <x-ui.field label="Weight (g)">
                                    <input type="number" step="0.001" class="rj-input w-full tabular" wire:model="rawLines.{{ $i }}.weight">
                                </x-ui.field>
                                <x-ui.field label="Rate (₹/g)">
                                    <input type="number" step="0.01" class="rj-input w-full tabular" wire:model="rawLines.{{ $i }}.rate">
                                </x-ui.field>
                                <x-ui.button type="button" variant="ghost" size="icon-sm" icon="x" wire:click="removeRawLine({{ $i }})" title="Remove line" aria-label="Remove line" />
                            </div>
                        @endforeach
                    </div>
                    <x-ui.button type="button" variant="secondary" icon="plus" wire:click="addBlankRawLine" class="mb-5">Add line</x-ui.button>
                @else
                    <x-ui.field label="Total amount (₹)" for="np-tamount2" error="totalAmount" class="max-w-[240px] mb-4">
                        <div class="rj-input-icon">
                            <x-ui.icon name="coins" :size="16" />
                            <input id="np-tamount2" type="number" step="0.01" class="rj-input tabular @error('totalAmount') is-invalid @enderror" wire:model="totalAmount">
                        </div>
                    </x-ui.field>

                    <div class="font-semibold text-[13px] text-ink_text-primary mb-2.5">Items</div>
                    <div class="relative mb-3.5 max-w-[380px]" x-data="{ open: true }" x-on:click.outside="open = false">
                        <div class="rj-input-icon">
                            <x-ui.icon name="search" :size="16" />
                            <x-ui.scan-button target="#purchase-item-search" title="Scan the piece" class="absolute right-1.5 top-1/2 -translate-y-1/2 !w-8 !h-8" />
                            <input id="purchase-item-search" type="text" class="rj-input pr-12" placeholder="Search HUID or internal code to add an item…"
                                wire:model.live.debounce.300ms="itemSearch" x-on:focus="open = true" x-on:input="open = true">
                        </div>
                        @if (strlen($itemSearch) >= 2)
                            <div x-show="open" class="absolute left-0 right-0 mt-2 bg-white border border-line-light rounded-xl shadow-pop p-1.5 z-dropdown">
                                @forelse ($this->searchResults as $result)
                                    <button type="button" wire:click="pickItem({{ count($lines) - 1 }}, {{ $result->id }})"
                                        class="w-full flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted text-left">
                                        <span class="w-8 h-8 rounded-full bg-surface-sunken ring-1 ring-inset ring-line-light flex items-center justify-center text-ink_text-muted shrink-0"><x-ui.icon name="gem" :size="14" /></span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block rj-code text-[12.5px] text-ink_text-primary truncate">{{ $result->huid_code ?: $result->internal_code }}</span>
                                            <span class="block text-[12px] text-ink_text-muted">{{ $result->category }} · {{ $result->weight }}g</span>
                                        </span>
                                    </button>
                                @empty
                                    <div class="px-2.5 py-2 text-[12.5px] text-ink_text-secondary">No matching items.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3 mb-4">
                        @foreach ($lines as $i => $line)
                            <div class="grid grid-cols-[2fr_1fr_1fr_auto] gap-2.5 items-end p-3 rounded-xl border border-line-light bg-surface-sunken/50">
                                <div>
                                    <label class="rj-label">Item</label>
                                    <div class="rj-input bg-surface-muted text-ink_text-secondary">{{ $line['label'] ?: 'Not selected' }}</div>
                                </div>
                                <x-ui.field label="Rate (₹/g)">
                                    <input type="number" step="0.01" class="rj-input w-full tabular" wire:model="lines.{{ $i }}.rate">
                                </x-ui.field>
                                <x-ui.field label="Weight (g)">
                                    <input type="number" step="0.001" class="rj-input w-full tabular" wire:model="lines.{{ $i }}.weight">
                                </x-ui.field>
                                <x-ui.button type="button" variant="ghost" size="icon-sm" icon="x" wire:click="removeLine({{ $i }})" title="Remove line" aria-label="Remove line" />
                            </div>
                        @endforeach
                    </div>
                    <x-ui.button type="button" variant="secondary" icon="plus" wire:click="addBlankLine" class="mb-5">Add line</x-ui.button>
                @endif

                <x-ui.button type="submit" variant="primary" target="save" icon="check">Save Purchase</x-ui.button>
            </form>
        </x-ui.card>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card title="This purchase" icon="clipboard">
                <dl class="rj-dl">
                    <div><dt>Type</dt><dd>{{ $purchaseType === 'raw_material' ? 'Raw material' : 'Finished product' }}</dd></div>
                    <div><dt>Lines</dt><dd class="tabular">{{ $this->lineCount }}</dd></div>
                    <div><dt>Weight</dt><dd class="tabular">{{ number_format($this->lineWeightTotal, 3) }} g</dd></div>
                    <div class="col-span-2 pt-2 mt-1 border-t border-line-light">
                        <dt class="font-bold text-ink_text-primary">Total amount</dt>
                        <dd class="font-display text-[22px] font-semibold tabular text-ink_text-primary">₹{{ number_format((float) ($totalAmount ?: 0), 2) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="rounded-card bg-surface-sunken ring-1 ring-inset ring-line-light p-5">
                <div class="flex items-center gap-2 text-[12.5px] font-semibold text-ink_text-secondary mb-2">
                    <x-ui.icon name="info" :size="14" /> Finished vs. raw material
                </div>
                <p class="text-[12.5px] text-ink_text-secondary leading-relaxed">
                    Finished-product lines attach a real, already-tagged item. Raw-material lines are untagged and stay pending until converted into items from Stock → Items → Pending Tags.
                </p>
            </div>
        </aside>
    </div>
</div>
