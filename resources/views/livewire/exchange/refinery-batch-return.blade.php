<div>
    <x-ui.page-header title="Refinery — Return" subtitle="Pick the outstanding batch, then enter what came back."
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'Refinery Return']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="flame" :href="route('exchange.refinery.send')">Go to Send</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-6 items-start mb-8">
        <x-ui.card title="Outstanding batches" subtitle="Oldest sent is picked by default (FIFO) — choose a different one if needed" icon="truck">
            @if ($outstandingBatches->isEmpty())
                <x-ui.empty-state icon="check-circle" title="Nothing outstanding" message="Every batch that has been sent has also come back." compact />
            @else
                <div class="space-y-2">
                    @foreach ($outstandingBatches as $b)
                        <button type="button" wire:click="selectBatch({{ $b->id }})"
                            class="w-full flex items-center gap-3.5 p-3.5 rounded-xl border text-left transition-colors
                            {{ $batchId === $b->id ? 'border-gold bg-gold-tint/50 ring-1 ring-gold/20' : 'border-line-light hover:border-gold-soft bg-white' }}">
                            @if ($b->photo_path)
                                <img src="{{ \Storage::disk('public')->url($b->photo_path) }}" class="w-11 h-11 object-cover rounded-lg ring-1 ring-line shrink-0">
                            @else
                                <span class="w-11 h-11 shrink-0 rounded-lg bg-surface-muted flex items-center justify-center text-ink_text-muted"><x-ui.icon name="image" :size="16" /></span>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="text-[13.5px] font-semibold text-ink_text-primary">Batch <span class="rj-code">#{{ $b->id }}</span> · <span class="tabular">{{ number_format($b->weight, 3) }} g</span></div>
                                <div class="text-[12px] text-ink_text-muted">Sent {{ $b->sent_at?->diffForHumans() }}</div>
                            </div>
                            @if ($batchId === $b->id) <x-ui.icon name="check-circle" :size="18" class="text-gold-dark shrink-0" /> @endif
                        </button>
                    @endforeach
                </div>
            @endif
            @error('batchId') <p class="rj-error mt-3"><x-ui.icon name="alert-triangle" :size="12" />Select a batch.</p> @enderror
        </x-ui.card>

        <x-ui.card title="Refined result" icon="shield-check">
            <form wire:submit="submit" class="space-y-4">
                <x-ui.field label="Refined weight" for="rr-weight" error="refinedWeight">
                    <div class="rj-input-icon">
                        <x-ui.icon name="scale" :size="16" />
                        <input id="rr-weight" type="number" step="0.001" min="0" wire:model.live.debounce.300ms="refinedWeight" class="rj-input tabular">
                    </div>
                </x-ui.field>
                <x-ui.field label="Refined purity" for="rr-purity" error="refinedPurity">
                    <div class="rj-input-icon">
                        <x-ui.icon name="percent" :size="16" />
                        <input id="rr-purity" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.300ms="refinedPurity" class="rj-input tabular">
                    </div>
                </x-ui.field>
                @if ($calc['result'] > 0)
                    <dl class="rj-dl bg-surface-sunken ring-1 ring-inset ring-line-light rounded-xl px-4 !py-3.5">
                        <div><dt>Pure metal</dt><dd class="tabular">{{ number_format($calc['fine'], 3) }} g</dd></div>
                        <div><dt>Deduction</dt><dd class="tabular">{{ number_format($calc['percent'], 2) }}%</dd></div>
                        <div class="col-span-2 pt-2 mt-1 border-t border-line-light"><dt class="font-bold text-ink_text-primary">Resulting weight</dt><dd class="font-display text-[22px] font-semibold tabular">{{ number_format($calc['result'], 3) }} g</dd></div>
                    </dl>
                @endif
                <x-ui.button type="submit" target="submit" icon="check" class="w-full" :disabled="! $batchId">Confirm Return</x-ui.button>
            </form>
        </x-ui.card>
    </div>

    <div class="flex items-end justify-between gap-4 mb-3.5">
        <div>
            <h2 class="font-display text-[24px] font-semibold leading-tight">Returns recorded</h2>
            <p class="text-[13px] text-ink_text-secondary">Completed round-trips, most recent first.</p>
        </div>
    </div>

    <x-ui.datatable :paginator="$returnedBatches">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Batch #" class="w-full sm:w-[200px]" />
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="id" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Batch</x-ui.th>
            <th class="text-right">Sent</th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Refined weight</x-ui.th>
            <x-ui.th field="purity" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Refined purity</x-ui.th>
            <th>Turnaround</th>
            <x-ui.th field="returned" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Returned</x-ui.th>
            <th>By</th>
        </x-slot:head>

        @forelse ($returnedBatches as $b)
            <tr wire:key="ret-{{ $b->id }}">
                <td class="rj-code">#{{ $b->id }}</td>
                <td class="text-right tabular">{{ number_format($b->weight, 3) }} g</td>
                <td class="text-right tabular font-semibold">{{ number_format($b->refined_weight, 3) }} g</td>
                <td class="text-right tabular">{{ number_format($b->refined_purity, 2) }}%</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $b->sent_at?->diffInDays($b->returned_at) }} days</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $b->returned_at?->format('d M Y') }}</td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $b->returner->name ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty-state icon="repeat" title="No returns recorded yet" message="Completed batches will appear here once they come back from the refinery." />
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
