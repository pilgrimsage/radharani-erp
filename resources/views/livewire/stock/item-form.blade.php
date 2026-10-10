<div>
    {{-- Add / edit piece. Opened from anywhere with Livewire.dispatch('open-item-form', { id }) or { purchaseItemId }. --}}
    <x-ui.modal wire:model="showForm" max-width="2xl" submit="save" icon="gem"
        :title="$editingId ? 'Edit piece' : ($taggingPurchaseItemId ? 'Tag purchased material' : 'Add a piece')"
        :subtitle="$editingId ? 'Changes are recorded in the piece\'s history.' : 'Fill it top to bottom. The price is worked out for you.'">

        @if ($taggingPurchaseItemId)
            <div class="flex items-start gap-3 mb-5 px-4 py-3 rounded-xl bg-gold-tint ring-1 ring-inset ring-gold-soft text-[13px] text-gold-dark">
                <x-ui.icon name="info" :size="16" class="shrink-0 mt-0.5" />
                Pre-filled from a purchase line. Saving creates the piece, links it to that purchase and marks the line as tagged.
            </div>
        @endif

        @if ($this->canManageWebsite())
            <div class="rj-segment mb-6" role="tablist">
                <button type="button" role="tab" wire:click="$set('tab', 'piece')" @class(['is-active' => $tab === 'piece'])><x-ui.icon name="gem" :size="13" /> Piece details</button>
                <button type="button" role="tab" wire:click="$set('tab', 'website')" @class(['is-active' => $tab === 'website'])>
                    <x-ui.icon name="globe" :size="13" /> Website
                    @if ($show_on_website)<span class="w-1.5 h-1.5 rounded-full bg-success"></span>@endif
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-7">
            @if ($tab === 'website')
                @include('livewire.stock.partials.item-form-website')
            @else
            <div class="space-y-7 min-w-0">
                {{-- What it is --}}
                <section>
                    <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">What it is</h3>
                    <div class="space-y-4">
                        <x-ui.field label="Metal" error="metal">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach (\App\Livewire\Stock\ItemForm::METALS as $k => $v)
                                    <label class="relative flex items-center gap-2.5 h-11 px-3 rounded-control border cursor-pointer transition-colors
                                        {{ $metal === $k ? 'border-gold bg-gold-tint shadow-focus' : 'border-line hover:border-line-strong' }}">
                                        <input type="radio" wire:model.live="metal" value="{{ $k }}" class="sr-only">
                                        <span @class([
                                            'w-3.5 h-3.5 rounded-full ring-2 ring-white shadow',
                                            'bg-gradient-to-br from-gold-light to-gold' => $k === 'gold',
                                            'bg-gradient-to-br from-[#E4E4E4] to-[#A9A9A9]' => $k === 'silver',
                                            'bg-gradient-to-br from-[#E9E6E1] to-[#B8B3AA]' => $k === 'platinum',
                                            'bg-gradient-to-br from-[#9EA4AA] to-[#5F666D]' => $k === 'titanium',
                                        ])></span>
                                        <span class="text-[13px] font-semibold {{ $metal === $k ? 'text-ink_text-primary' : 'text-ink_text-secondary' }}">{{ $v }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </x-ui.field>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-ui.field label="Subcategory" for="f-category" error="categoryId" hint="Under {{ \App\Livewire\Stock\ItemForm::METALS[$metal] ?? 'this metal' }}. The owner manages the list in Stock > Categories.">
                                <select id="f-category" wire:model.live="categoryId" autofocus class="rj-select @error('categoryId') is-invalid @enderror">
                                    <option value="">Choose...</option>
                                    @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field label="Purity" for="f-purity" error="purity">
                                <input id="f-purity" type="text" wire:model.live.debounce.400ms="purity" class="rj-input @error('purity') is-invalid @enderror" autocomplete="off"
                                    placeholder="{{ (\App\Livewire\Stock\ItemForm::PURITIES[$metal] ?? ['22K'])[0] }}">
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    @foreach (\App\Livewire\Stock\ItemForm::PURITIES[$metal] ?? [] as $p)
                                        <button type="button" wire:click="$set('purity', '{{ $p }}')"
                                            class="h-7 px-2.5 rounded-md text-[12px] font-semibold ring-1 ring-inset transition-colors
                                            {{ $purity === $p ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $p }}</button>
                                    @endforeach
                                </div>
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-ui.field label="HUID" for="f-huid" error="huid_code" optional
                                hint="Required for hallmarked gold over 2 g. Leave empty and a 5-character code is created.">
                                <div class="relative"><input id="f-huid" type="text" wire:model.live.debounce.500ms="huid_code" maxlength="20" class="rj-input pr-12 rj-code uppercase @error('huid_code') is-invalid @enderror" autocomplete="off"><x-ui.scan-button target="#f-huid" title="Scan the HUID" class="absolute right-1.5 top-1/2 -translate-y-1/2 !w-8 !h-8" /></div>
                            </x-ui.field>
                            <x-ui.field label="HSN code" for="f-hsn" error="hsn_code" optional hint="Usually 7113 for jewellery.">
                                <input id="f-hsn" type="text" wire:model="hsn_code" maxlength="10" class="rj-input tabular">
                            </x-ui.field>
                        </div>
                    </div>
                </section>

                {{-- Weight --}}
                <section class="pt-6 border-t border-line-light">
                    <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Weight and description</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-[180px_minmax(0,1fr)] gap-4">
                        <x-ui.field label="Weight" for="f-weight" error="weight">
                            <div class="relative">
                                <input id="f-weight" type="number" step="0.001" min="0" wire:model.live.debounce.400ms="weight" class="rj-input pr-9 tabular @error('weight') is-invalid @enderror">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">g</span>
                            </div>
                        </x-ui.field>
                        <x-ui.field label="Short description" for="f-desc" error="description" optional>
                            <input id="f-desc" type="text" wire:model="description" maxlength="100" class="rj-input" placeholder="e.g. Temple design with ruby drops">
                        </x-ui.field>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-[180px_minmax(0,1fr)_180px] gap-4 mt-4">
                        <x-ui.field label="Net weight" for="f-net" error="net_weight" optional hint="Without stones. Metal is priced on this.">
                            <div class="relative">
                                <input id="f-net" type="number" step="0.001" min="0" wire:model.live.debounce.400ms="net_weight" class="rj-input pr-9 tabular @error('net_weight') is-invalid @enderror">
                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">g</span>
                            </div>
                        </x-ui.field>
                        <x-ui.field label="Stones" for="f-stones" error="stones" optional>
                            <input id="f-stones" type="text" wire:model="stones" maxlength="120" class="rj-input" placeholder="e.g. Kundan, seed pearls">
                        </x-ui.field>
                        <x-ui.field label="Stone value" for="f-stone-value" error="stone_value" optional>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">₹</span>
                                <input id="f-stone-value" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="stone_value" class="rj-input pl-8 tabular @error('stone_value') is-invalid @enderror">
                            </div>
                        </x-ui.field>
                    </div>
                </section>

                {{-- Making --}}
                <section class="pt-6 border-t border-line-light">
                    <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Making charge</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 mb-4">
                        @foreach ([
                            'percentage' => ['Percentage', 'A % of the metal value'],
                            'flat_per_piece' => ['Per piece', 'A fixed amount for the piece'],
                            'flat_per_gram' => ['Per gram', 'A fixed amount for every gram'],
                        ] as $k => [$name, $desc])
                            <label class="relative block p-3.5 rounded-control border cursor-pointer transition-colors
                                {{ $making_type === $k ? 'border-gold bg-gold-tint shadow-focus' : 'border-line hover:border-line-strong' }}">
                                <input type="radio" wire:model.live="making_type" value="{{ $k }}" class="sr-only">
                                <span class="flex items-center justify-between">
                                    <span class="text-[13px] font-bold text-ink_text-primary">{{ $name }}</span>
                                    <span class="w-4 h-4 rounded-full border-2 {{ $making_type === $k ? 'border-gold bg-gold ring-2 ring-inset ring-white' : 'border-line-strong' }}"></span>
                                </span>
                                <span class="block text-[12px] text-ink_text-secondary mt-1">{{ $desc }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.field label="Making value" for="f-making" error="making_value" class="max-w-[220px]">
                        <div class="relative">
                            @if ($making_type !== 'percentage')
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted pointer-events-none">₹</span>
                            @endif
                            <input id="f-making" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="making_value"
                                class="rj-input tabular {{ $making_type !== 'percentage' ? 'pl-8' : '' }} pr-14 @error('making_value') is-invalid @enderror">
                            <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[12.5px] text-ink_text-muted pointer-events-none">
                                {{ ['percentage' => '%', 'flat_per_piece' => '/ piece', 'flat_per_gram' => '/ gram'][$making_type] }}
                            </span>
                        </div>
                    </x-ui.field>
                </section>

                {{-- Placement --}}
                <section class="pt-6 border-t border-line-light">
                    <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">Where it's kept</h3>
                    <x-ui.field label="Packet" for="f-packet" error="packet_id" optional hint="You can also scan it into a packet later from Change Item location.">
                        <select id="f-packet" wire:model="packet_id" class="rj-select">
                            <option value="">Not in a packet yet</option>
                            @foreach ($packetsByBox as $boxCode => $packets)
                                <optgroup label="{{ $boxCode }}">
                                    @foreach ($packets as $p)
                                        <option value="{{ $p->id }}">{{ $p->code }}{{ $p->label ? ' · ' . $p->label : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </x-ui.field>
                </section>

                {{-- Pair --}}
                <section class="pt-6 border-t border-line-light">
                    <h3 class="text-[13px] font-bold text-ink_text-primary mb-1">Sold as a pair?</h3>
                    <p class="text-[12.5px] text-ink_text-secondary mb-3.5">For earrings or bangles. Each piece keeps its own weight, but they show and sell together.</p>
                    <div class="rj-segment flex-wrap">
                        @if ($currentPair)
                            <button type="button" wire:click="$set('pairMode', 'keep')" class="{{ $pairMode === 'keep' ? 'is-active' : '' }}"><x-ui.icon name="link" :size="13" /> Keep current pair</button>
                        @endif
                        <button type="button" wire:click="$set('pairMode', 'none')" class="{{ $pairMode === 'none' ? 'is-active' : '' }}">{{ $currentPair ? 'Unpair' : 'Single piece' }}</button>
                        @unless ($editingId)
                            <button type="button" wire:click="$set('pairMode', 'new')" class="{{ $pairMode === 'new' ? 'is-active' : '' }}">Add the other piece now</button>
                        @endunless
                        <button type="button" wire:click="$set('pairMode', 'existing')" class="{{ $pairMode === 'existing' ? 'is-active' : '' }}">Pair with an existing piece</button>
                    </div>

                    @if ($currentPair && $pairMode === 'keep')
                        <div class="mt-3 flex items-center gap-2 text-[13px] text-ink_text-secondary">
                            <x-ui.icon name="link" :size="14" class="text-gold" /> Paired with
                            <a href="{{ route('stock.items.show', $currentPair) }}" class="rj-code">{{ $currentPair->label }}</a>
                            <span class="tabular">({{ number_format($currentPair->weight, 3) }} g)</span>
                        </div>
                    @endif

                    @if ($pairMode === 'new')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4 p-4 rounded-xl bg-surface-sunken ring-1 ring-inset ring-line-light">
                            <x-ui.field label="Other piece's weight" for="f-pw" error="partnerWeight">
                                <div class="relative">
                                    <input id="f-pw" type="number" step="0.001" min="0" wire:model="partnerWeight" class="rj-input pr-9 tabular @error('partnerWeight') is-invalid @enderror">
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-[13px] text-ink_text-muted">g</span>
                                </div>
                            </x-ui.field>
                            <x-ui.field label="Other piece's HUID" for="f-ph" error="partnerHuid" optional>
                                <input id="f-ph" type="text" wire:model="partnerHuid" maxlength="20" class="rj-input rj-code uppercase">
                            </x-ui.field>
                            <p class="sm:col-span-2 text-[12px] text-ink_text-muted -mt-1">Everything else (metal, purity, making charge, packet) is copied from this piece.</p>
                        </div>
                    @endif

                    @if ($pairMode === 'existing')
                        <div class="mt-4">
                            <x-ui.search-input scan wire:model.live.debounce.250ms="pairSearch" placeholder="Search unpaired pieces by code or category" class="mb-2.5" />
                            <div class="border border-line-light rounded-xl max-h-[200px] overflow-y-auto divide-y divide-line-light">
                                @forelse ($pairCandidates as $pc)
                                    <label wire:key="pc-{{ $pc->id }}" class="flex items-center gap-3 px-3.5 py-2 cursor-pointer hover:bg-surface-sunken has-[:checked]:bg-gold-tint">
                                        <input type="radio" class="rj-radio" value="{{ $pc->id }}" wire:model="pairWithId">
                                        <span class="rj-code text-ink_text-primary">{{ $pc->label }}</span>
                                        <span class="flex-1 text-[12.5px] text-ink_text-secondary truncate">{{ $pc->category }} · {{ ucfirst($pc->metal ?? '') }} {{ $pc->purity }}</span>
                                        <span class="text-[12.5px] tabular">{{ number_format($pc->weight, 3) }} g</span>
                                    </label>
                                @empty
                                    <div class="px-4 py-5 text-center text-[12.5px] text-ink_text-muted">No unpaired pieces found{{ $category && ! $pairSearch ? ' in ' . $category : '' }}.</div>
                                @endforelse
                            </div>
                            @error('pairWithId') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
                        </div>
                    @endif
                </section>
            </div>
            @endif

            {{-- Price preview --}}
            <aside class="lg:sticky lg:top-0 self-start">
                <div class="rounded-2xl bg-ink text-white p-5 ink-grain ring-1 ring-black/40">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-semibold text-ink-dim">Price today</span>
                        <span wire:loading wire:target="weight,net_weight,stone_value,making_value,making_type,metal,purity,huid_code" class="text-gold-light"><x-ui.icon name="loader" :size="14" class="animate-spin" /></span>
                    </div>
                    @if ($estimate)
                        <div class="font-display text-[38px] leading-none font-semibold text-gold-light mt-2 tabular">₹{{ number_format($estimate['total'], 0) }}</div>
                        <dl class="mt-5 space-y-2.5 text-[12.5px]">
                            <div class="flex justify-between gap-3"><dt class="text-ink-dim">Metal value</dt><dd class="tabular">₹{{ number_format($estimate['metal_value'], 2) }}</dd></div>
                            <div class="-mt-1.5 text-ink-dim/80 text-[11.5px] tabular">{{ number_format($estimate['weight'], 3) }} g × ₹{{ number_format($estimate['rate'], 2) }}</div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-dim">Making charge</dt><dd class="tabular">₹{{ number_format($estimate['making'], 2) }}</dd></div>
                            @if ($estimate['stone_value'] > 0)
                                <div class="flex justify-between gap-3"><dt class="text-ink-dim">Stones</dt><dd class="tabular">₹{{ number_format($estimate['stone_value'], 2) }}</dd></div>
                            @endif
                            @if ($estimate['huid_charge'])
                                <div class="flex justify-between gap-3"><dt class="text-ink-dim">HUID charge</dt><dd class="tabular">₹{{ number_format($estimate['huid_charge'], 2) }}</dd></div>
                            @endif
                            @if ($estimate['discount'] > 0)
                                <div class="flex justify-between gap-3 text-[#8FD9B6]"><dt>Discount rule</dt><dd class="tabular">-₹{{ number_format($estimate['discount'], 2) }}</dd></div>
                            @endif
                        </dl>
                        @if (! $estimate['rate'])
                            <p class="mt-4 text-[12px] text-[#F2C27A]">No {{ $metal }} rate has been entered yet, so the metal value shows as zero.</p>
                        @endif
                    @else
                        <div class="font-display text-[38px] leading-none font-semibold text-white/25 mt-2">₹0</div>
                        <p class="mt-4 text-[12.5px] text-ink-dim">Enter the weight and making charge to see today's price.</p>
                    @endif
                    <p class="mt-5 pt-4 border-t border-white/10 text-[11.5px] leading-relaxed text-ink-dim">Worked out live from the latest {{ $metal }} rate. It isn't saved on the piece, so tomorrow's rate reprices it automatically.</p>
                </div>
            </aside>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="show = false">Cancel</x-ui.button>
            <x-ui.button type="submit" target="save" icon="check">{{ $editingId ? 'Save changes' : ($taggingPurchaseItemId ? 'Create tagged piece' : 'Add to stock') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
