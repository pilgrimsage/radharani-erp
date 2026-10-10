<div>
    <x-ui.page-header title="Refinery — Send" subtitle="Old gold weight and a reference photo, before it goes out to the refinery."
        :crumbs="[['label' => 'Exchange & Refinery'], ['label' => 'Refinery Send']]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="repeat" :href="route('exchange.refinery.return')">Go to Return</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <x-ui.stat-card icon="truck" label="Outstanding batches" :value="number_format($stats['outstanding'])" :hint="number_format($stats['outstandingWeight'], 3) . ' g out'" />
        <x-ui.stat-card icon="check-circle" label="Returned" :value="number_format($stats['returned'])" hint="completed round-trips" />
    </div>

    <x-ui.card title="Send a new batch" subtitle="Weigh the accumulated scrap and photograph it before it leaves the shop" icon="flame" class="mb-8 max-w-[560px]">
        <form wire:submit="submit">
            <x-ui.field label="Metal" for="rs-metal" error="metal"><select id="rs-metal" wire:model="metal" class="rj-select"><option value="gold">Gold</option><option value="silver">Silver</option><option value="platinum">Platinum</option><option value="titanium">Titanium</option></select></x-ui.field>
            <x-ui.field label="Old scrap weight" for="rs-weight" error="weight" class="mt-4">
                <div class="rj-input-icon">
                    <x-ui.icon name="scale" :size="16" />
                    <input id="rs-weight" type="number" step="0.001" min="0" wire:model="weight" class="rj-input tabular">
                </div>
            </x-ui.field>

            <div class="mt-4">
                <label class="rj-label">Reference photo</label>
                @if ($photo)
                    <div class="flex items-center gap-4 p-3 rounded-xl border border-line-light bg-surface-sunken">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-20 h-20 object-cover rounded-lg ring-1 ring-line">
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-semibold text-ink_text-primary truncate">{{ $photo->getClientOriginalName() }}</div>
                            <button type="button" wire:click="$set('photo', null)" class="text-[12.5px] font-semibold text-danger hover:underline mt-1">Remove photo</button>
                        </div>
                    </div>
                @else
                    <label class="group relative flex flex-col items-center justify-center gap-2.5 py-10 px-6 rounded-2xl border-2 border-dashed border-line hover:border-gold hover:bg-gold-tint/40 cursor-pointer transition-colors text-center"
                           x-data="{ drag: false }" x-on:dragover.prevent="drag = true" x-on:dragleave="drag = false" x-on:drop="drag = false"
                           :class="drag ? 'border-gold bg-gold-tint/60' : ''">
                        <input type="file" wire:model="photo" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                        <span class="w-12 h-12 rounded-2xl bg-white ring-1 ring-line shadow-card text-gold-dark flex items-center justify-center">
                            <x-ui.icon name="camera" :size="20" wire:loading.remove wire:target="photo" />
                            <x-ui.icon name="loader" :size="20" class="animate-spin" wire:loading wire:target="photo" />
                        </span>
                        <span class="text-[13.5px] font-semibold text-ink_text-primary" wire:loading.remove wire:target="photo">Drop a photo here, or click to choose</span>
                        <span class="text-[13.5px] font-semibold text-ink_text-primary" wire:loading wire:target="photo">Uploading...</span>
                        <span class="text-[12px] text-ink_text-secondary">JPG or PNG, compressed automatically</span>
                    </label>
                @endif
                @error('photo') <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" />{{ $message }}</p> @enderror
            </div>

            <x-ui.button type="submit" target="submit" icon="flame" class="w-full mt-5">Confirm Send</x-ui.button>
        </form>
    </x-ui.card>

    <div class="flex items-end justify-between gap-4 mb-3.5">
        <div>
            <h2 class="font-display text-[24px] font-semibold leading-tight">Batches sent</h2>
            <p class="text-[13px] text-ink_text-secondary">Every batch ever sent, outstanding or returned.</p>
        </div>
    </div>

    <x-ui.datatable :paginator="$batches">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Batch #" class="w-full sm:w-[200px]" />
        </x-slot:toolbar>

        <x-slot:head>
            <th class="w-16"><span class="sr-only">Photo</span></th>
            <x-ui.th field="id" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Batch</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight sent</x-ui.th>
            <th>Refined result</th>
            <x-ui.th field="status" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Status</x-ui.th>
            <x-ui.th field="sent" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Sent</x-ui.th>
        </x-slot:head>

        @forelse ($batches as $b)
            <tr wire:key="rb-{{ $b->id }}">
                <td>
                    @if ($b->photo_path)
                        <a href="{{ \Storage::disk('public')->url($b->photo_path) }}" target="_blank" rel="noopener">
                            <img src="{{ \Storage::disk('public')->url($b->photo_path) }}" class="w-11 h-11 object-cover rounded-lg ring-1 ring-line hover:ring-gold">
                        </a>
                    @else
                        <span class="w-11 h-11 rounded-lg bg-surface-muted flex items-center justify-center text-ink_text-muted"><x-ui.icon name="image" :size="16" /></span>
                    @endif
                </td>
                <td class="rj-code">#{{ $b->id }}</td>
                <td class="text-right tabular font-semibold">{{ number_format($b->weight, 3) }} <span class="text-ink_text-muted font-normal">g</span></td>
                <td class="tabular text-[13px]">
                    @if ($b->status === 'returned')
                        {{ number_format($b->refined_weight, 3) }} g <span class="text-ink_text-muted">· {{ number_format($b->refined_purity, 2) }}%</span>
                    @else
                        <span class="text-ink_text-muted">Not back yet</span>
                    @endif
                </td>
                <td><x-ui.badge :tone="$b->status === 'returned' ? 'success' : 'warning'" size="sm" dot>{{ $b->status === 'returned' ? 'Returned' : 'Sent' }}</x-ui.badge></td>
                <td class="text-[12.5px] text-ink_text-secondary whitespace-nowrap">{{ $b->sent_at?->format('d M Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <x-ui.empty-state icon="flame" title="No batches sent yet" message="Send the first batch using the form above." />
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>
</div>
