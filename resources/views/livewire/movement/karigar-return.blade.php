<div>
    <x-ui.page-header title="Karigar Return" subtitle="Record what has come back. Weigh it, and type in the weight lost. Returned stock waits for an admin to confirm it."
        :crumbs="[['label' => 'Movements'], ['label' => 'Karigar Return']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="truck" :href="route('movements.karigar-dispatch')">Send something out</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="rj-segment mb-6 flex-wrap h-auto">
        @foreach (['pieces' => ['gem', 'Pieces from repair'], 'customer' => ['user', "Customers' material"], 'raw' => ['flame', 'New pieces from raw material']] as $key => [$icon, $label])
            <button type="button" wire:click="setTab('{{ $key }}')" class="{{ $tab === $key ? 'is-active' : '' }}">
                <x-ui.icon :name="$icon" :size="14" /> {{ $label }}
                <span class="min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-bold tabular inline-flex items-center justify-center {{ $tab === $key ? 'bg-gold-tint text-gold-dark' : 'bg-white text-ink_text-muted' }}">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[380px_minmax(0,1fr)] gap-6 items-start">
        {{-- What is out --}}
        <x-ui.card :padding="false" class="xl:sticky xl:top-24">
            <div class="p-3.5 border-b border-line-light">
                <x-ui.search-input scan wire:model.live.debounce.300ms="search"
                    :placeholder="['pieces' => 'Code, category or karigar', 'customer' => 'Customer, phone or item', 'raw' => 'Batch number or karigar'][$tab]" />
            </div>
            <ul class="divide-y divide-line-light max-h-[620px] overflow-y-auto">
                @if ($tab === 'pieces')
                    @forelse ($pieces as $m)
                        <li wire:key="p-{{ $m->id }}">
                            <button type="button" wire:click="select({{ $m->id }})" @class([
                                'w-full text-left flex items-start gap-3 px-4 py-3.5 transition-colors',
                                'bg-gold-tint shadow-[inset_3px_0_0_#B8862D]' => $selectedId === $m->id,
                                'hover:bg-surface-sunken' => $selectedId !== $m->id,
                            ])>
                                <x-movement.metal-dot :metal="$m->item?->metal" class="mt-1.5" />
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="rj-code text-ink_text-primary">{{ $m->item?->label }}</span>
                                        <x-movement.due :date="$m->expected_return" />
                                    </span>
                                    <span class="block text-[12px] text-ink_text-muted truncate">{{ $m->item?->category }} · {{ $m->purpose_label }} · <span class="tabular">{{ number_format((float) $m->weight_at_dispatch, 3) }} g</span></span>
                                    <span class="block text-[12px] text-ink_text-secondary">{{ $m->counterparty }} · sent {{ $m->created_at->format('j M') }}</span>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="check-circle" :title="$search ? 'Nothing matches' : 'No pieces are out'" :message="$search ? 'Try another search.' : 'Every piece sent for repair has come back.'" compact /></li>
                    @endforelse
                @elseif ($tab === 'customer')
                    @forelse ($customerJobs as $j)
                        <li wire:key="c-{{ $j->id }}">
                            <button type="button" wire:click="select({{ $j->id }})" @class([
                                'w-full text-left flex items-start gap-3 px-4 py-3.5 transition-colors',
                                'bg-gold-tint shadow-[inset_3px_0_0_#B8862D]' => $selectedId === $j->id,
                                'hover:bg-surface-sunken' => $selectedId !== $j->id,
                            ])>
                                <x-movement.metal-dot :metal="$j->metal" class="mt-1.5" />
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-[13px] font-semibold text-ink_text-primary truncate">{{ $j->customer?->name }}</span>
                                        <x-movement.due :date="$j->expected_return" />
                                    </span>
                                    <span class="block text-[12px] text-ink_text-muted truncate">{{ $j->description }} · <span class="tabular">{{ number_format((float) $j->weight_out, 3) }} g</span></span>
                                    <span class="block text-[12px] text-ink_text-secondary">{{ $j->vendor?->name }} · sent {{ $j->created_at->format('j M') }}</span>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="check-circle" :title="$search ? 'Nothing matches' : 'No customer material is out'" :message="$search ? 'Try another search.' : 'Every customer job has come back.'" compact /></li>
                    @endforelse
                @else
                    @forelse ($rawBatches as $b)
                        <li wire:key="r-{{ $b->id }}">
                            <button type="button" wire:click="select({{ $b->id }})" @class([
                                'w-full text-left flex items-start gap-3 px-4 py-3.5 transition-colors',
                                'bg-gold-tint shadow-[inset_3px_0_0_#B8862D]' => $selectedId === $b->id,
                                'hover:bg-surface-sunken' => $selectedId !== $b->id,
                            ])>
                                <x-movement.metal-dot :metal="$b->metal" class="mt-1.5" />
                                <span class="flex-1 min-w-0">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-[13px] font-semibold text-ink_text-primary">Batch #{{ $b->id }}</span>
                                        <x-movement.due :date="$b->expected_return" />
                                    </span>
                                    <span class="block text-[12px] text-ink_text-muted truncate">{{ ucfirst($b->metal) }} {{ $b->purity }} · <span class="tabular">{{ number_format((float) $b->weight_out, 3) }} g</span>{{ $b->purpose_label ? ' · ' . $b->purpose_label : '' }}</span>
                                    <span class="block text-[12px] text-ink_text-secondary">{{ $b->vendor?->name }} · issued {{ $b->created_at->format('j M') }}</span>
                                </span>
                            </button>
                        </li>
                    @empty
                        <li><x-ui.empty-state icon="check-circle" :title="$search ? 'Nothing matches' : 'No raw material is out'" :message="$search ? 'Try another search.' : 'Every raw-material batch has come back.'" compact /></li>
                    @endforelse
                @endif
            </ul>
        </x-ui.card>

        {{-- Return form --}}
        @if (! $selected)
            <x-ui.card>
                <x-ui.empty-state icon="corner-down-right" title="Pick what came back"
                    :message="['pieces' => 'Choose the piece from the list. You will weigh it and record any loss.', 'customer' => 'Choose the customer job from the list. You will weigh it and record any loss.', 'raw' => 'Choose the raw-material batch. You will weigh the new piece and tag it here.'][$tab]" />
            </x-ui.card>
        @else
            <x-ui.card :padding="false">
                {{-- What went out --}}
                <div class="flex flex-wrap items-start gap-4 px-5 sm:px-6 py-5 border-b border-line-light">
                    <span class="w-12 h-12 shrink-0 rounded-xl bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft/70 flex items-center justify-center">
                        <x-ui.icon :name="['pieces' => 'gem', 'customer' => 'user', 'raw' => 'flame'][$tab]" :size="20" />
                    </span>
                    <div class="flex-1 min-w-0">
                        @if ($tab === 'pieces')
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('stock.items.show', $selected->item) }}" class="font-display text-[28px] leading-tight font-semibold text-ink_text-primary hover:text-gold-dark">{{ $selected->item->label }}</a>
                                <x-movement.due :date="$selected->expected_return" size="md" />
                            </div>
                            <div class="text-[13px] text-ink_text-secondary">{{ $selected->item->category }} · {{ ucfirst($selected->item->metal ?? '') }} {{ $selected->item->purity }}</div>
                        @elseif ($tab === 'customer')
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-display text-[28px] leading-tight font-semibold text-ink_text-primary">{{ $selected->customer?->name }}</span>
                                <x-movement.due :date="$selected->expected_return" size="md" />
                            </div>
                            <div class="text-[13px] text-ink_text-secondary">{{ $selected->description }} · {{ ucfirst($selected->metal) }} · <span class="tabular">{{ $selected->customer?->phone }}</span></div>
                        @else
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-display text-[28px] leading-tight font-semibold text-ink_text-primary">Raw batch #{{ $selected->id }}</span>
                                <x-movement.due :date="$selected->expected_return" size="md" />
                            </div>
                            <div class="text-[13px] text-ink_text-secondary">{{ ucfirst($selected->metal) }} {{ $selected->purity }}{{ $selected->purpose_label ? ' · to make ' . $selected->purpose_label : '' }}</div>
                        @endif
                    </div>
                    <x-ui.button variant="ghost" size="icon-sm" icon="x" wire:click="clearSelection" aria-label="Close" />
                </div>

                <dl class="rj-dl sm:grid-cols-4 px-5 sm:px-6 py-4 bg-surface-sunken border-b border-line-light">
                    <div><dt>Karigar</dt><dd>{{ $tab === 'pieces' ? $selected->counterparty : $selected->vendor?->name }}</dd></div>
                    <div><dt>Sent</dt><dd>{{ $selected->created_at->format('j M Y') }}</dd></div>
                    <div><dt>By</dt><dd>{{ $selected->user?->name ?? '-' }}</dd></div>
                    <div><dt>{{ $tab === 'pieces' ? 'Work' : 'Weight out' }}</dt><dd class="tabular">{{ $tab === 'pieces' ? ($selected->purpose_label ?: '-') : number_format((float) $selected->weight_out, 3) . ' g' }}</dd></div>
                    @if ($selected->note)
                        <div class="col-span-2 sm:col-span-4"><dt>Note from dispatch</dt><dd>{{ $selected->note }}</dd></div>
                    @endif
                </dl>

                <form wire:submit="confirm">
                    <div class="p-5 sm:p-6 space-y-6">
                        <section>
                            <h3 class="text-[13px] font-bold text-ink_text-primary mb-3.5">{{ $tab === 'raw' ? 'The finished piece' : 'What came back' }}</h3>
                            <x-movement.weight-fields :sent="$sentWeight" :diff="$scaleDiff"
                                :weight-label="$tab === 'raw' ? 'Finished piece weighs' : 'Weight on the scale now'" />
                        </section>

                        @if ($tab === 'raw')
                            <section class="pt-6 border-t border-line-light">
                                <h3 class="text-[13px] font-bold text-ink_text-primary mb-1">Tag the new piece</h3>
                                <p class="text-[12.5px] text-ink_text-secondary mb-3.5">It becomes a real piece now. An admin sets the making charge when confirming it in Pending Review.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <x-ui.field label="Category" for="kr-cat" error="category" hint="Necklace, Ring, Bangle...">
                                        <input id="kr-cat" type="text" list="kr-categories" wire:model="category" class="rj-input @error('category') is-invalid @enderror" autocomplete="off">
                                        <datalist id="kr-categories">@foreach ($categories as $c)<option value="{{ $c }}"></option>@endforeach</datalist>
                                    </x-ui.field>
                                    <x-ui.field label="Purity" for="kr-purity" error="purity">
                                        <input id="kr-purity" type="text" wire:model="purity" maxlength="10" class="rj-input @error('purity') is-invalid @enderror">
                                        <div class="flex flex-wrap gap-1.5 mt-2">
                                            @foreach ($purities as $p)
                                                <button type="button" wire:click="$set('purity', '{{ $p }}')"
                                                    class="h-7 px-2.5 rounded-md text-[12px] font-semibold ring-1 ring-inset transition-colors
                                                    {{ $purity === $p ? 'bg-ink text-gold-light ring-ink' : 'bg-white text-ink_text-secondary ring-line hover:ring-line-strong' }}">{{ $p }}</button>
                                            @endforeach
                                        </div>
                                    </x-ui.field>
                                    <x-ui.field label="HUID" for="kr-huid" error="huid" optional hint="Leave empty and a 5-character shop code is created.">
                                        <div class="relative"><input id="kr-huid" type="text" wire:model="huid" maxlength="6" class="rj-input pr-12 rj-code uppercase @error('huid') is-invalid @enderror" autocomplete="off"><x-ui.scan-button target="#kr-huid" title="Scan the HUID" class="absolute right-1.5 top-1/2 -translate-y-1/2 !w-8 !h-8" /></div>
                                    </x-ui.field>
                                    <x-ui.field label="Description" for="kr-desc" error="description" optional>
                                        <input id="kr-desc" type="text" wire:model="description" maxlength="100" class="rj-input">
                                    </x-ui.field>
                                </div>
                            </section>
                        @endif

                        <x-movement.done-by />

                        <x-ui.field label="Note" for="kr-note" error="note" optional>
                            <input id="kr-note" type="text" wire:model="note" maxlength="255" class="rj-input" placeholder="e.g. clasp replaced, one stone reset">
                        </x-ui.field>

                        @if ($tab === 'customer')
                            <label class="flex items-start gap-3 px-4 py-3.5 rounded-xl ring-1 ring-inset ring-line-light cursor-pointer select-none">
                                <input type="checkbox" wire:model="notifyCustomer" class="rj-checkbox mt-0.5">
                                <span>
                                    <span class="block text-[13.5px] font-semibold text-ink_text-primary">Tell {{ $selected->customer?->name }} it is ready to collect</span>
                                    <span class="block text-[12.5px] text-ink_text-secondary">Adds a ready-to-copy message to Messages, to send on WhatsApp.</span>
                                </span>
                            </label>
                        @else
                            <x-movement.hallmark-chain :centres="$centres" :on="$toHallmark" />
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 bg-surface-sunken border-t border-line-light rounded-b-card">
                        <div class="flex items-center gap-2 text-[12.5px] text-ink_text-secondary">
                            Afterwards:
                            @if ($tab === 'customer')
                                <x-ui.badge tone="success" size="sm" dot>Returned, ready to collect</x-ui.badge>
                            @elseif ($toHallmark)
                                <x-ui.status status="dispatched" size="sm" /> <span>at hallmarking</span>
                            @else
                                <x-ui.status status="pending_review" size="sm" />
                            @endif
                        </div>
                        <x-ui.button type="submit" size="lg" icon="check" target="confirm">
                            {{ $tab === 'raw' ? 'Create piece and confirm return' : 'Confirm return' }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
</div>
