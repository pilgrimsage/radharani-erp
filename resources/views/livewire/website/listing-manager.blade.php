<div>
    <x-ui.page-header title="Website listings" subtitle="Which pieces the public website shows. Reserved, dispatched and sold pieces come off the site by themselves."
        :crumbs="[['label' => 'Website'], ['label' => 'Listings']]">
        <x-slot:meta>
            <x-ui.badge tone="success" dot>{{ $counts['live'] }} live</x-ui.badge>
            @if ($counts['photos'])
                <x-ui.badge tone="warning">{{ $counts['photos'] }} without photos</x-ui.badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="external-link" :href="route('home')" target="_blank">Open website</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable :paginator="$items">
        <x-slot:toolbar>
            <x-ui.search-input wire:model.live.debounce.300ms="search" placeholder="Name, HUID, code, category, packet or box" class="w-full sm:w-[300px]" scan />
            <div class="rj-segment">
                @foreach (['' => 'All', 'live' => 'Live', 'waiting' => 'Ticked, not showing', 'off' => 'Off the site'] as $k => $v)
                    <button type="button" wire:click="$set('state', '{{ $k }}')" @class(['is-active' => $state === $k])>{{ $v }}</button>
                @endforeach
            </div>
            <select wire:model.live="stockCategory" class="rj-select w-full sm:w-[180px]" aria-label="Stock category">
                <option value="">All categories</option>
                @foreach ($stockCategories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
            @if ($this->hasActiveFilters())
                <x-ui.button variant="ghost" size="sm" icon="x" wire:click="resetFilters">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-ui.th field="name" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Piece</x-ui.th>
            <x-ui.th field="category" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Category</x-ui.th>
            <x-ui.th field="weight" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()" align="right">Weight</x-ui.th>
            <x-ui.th align="right">Price today</x-ui.th>
            <x-ui.th>Stock</x-ui.th>
            <x-ui.th field="listed" :sort-field="$this->currentSortField()" :sort-direction="$this->currentSortDirection()">Website</x-ui.th>
            <x-ui.th align="right"><span class="sr-only">Actions</span></x-ui.th>
        </x-slot:head>

        @forelse ($rows as $row)
            @php($item = $row['item'])
            <tr wire:key="listing-{{ $item->id }}">
                <td>
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($item->images->first())
                            <img src="{{ \App\Support\StorefrontImage::sized($item->images->first()->url, 96, 96) }}" alt="" class="w-11 h-11 rounded-control object-cover ring-1 ring-line shrink-0">
                        @else
                            <span class="w-11 h-11 rounded-control bg-surface-muted ring-1 ring-line flex items-center justify-center text-ink_text-muted shrink-0" title="No photos yet"><x-ui.icon name="camera" :size="16" /></span>
                        @endif
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="font-semibold text-ink_text-primary truncate max-w-[240px]">{{ $item->web_name ?: ($item->description ?: 'Untitled piece') }}</span>
                                @if (! $item->web_name)<span class="text-ink_text-muted text-[12px] shrink-0">(no website name)</span>@endif
                                @if ($item->is_bestseller)<x-ui.badge tone="gold" size="sm" class="shrink-0">Bestseller</x-ui.badge>@endif
                            </div>
                            <div class="flex items-center gap-2 text-[12px] text-ink_text-muted whitespace-nowrap">
                                <a href="{{ route('stock.items.show', $item) }}" class="rj-code hover:text-gold-dark">{{ $item->label }}</a>
                                <span>&middot; {{ $item->images_count }} {{ Str::plural('photo', $item->images_count) }}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="text-ink_text-primary">{{ $item->category }}</div>
                    <div class="text-[12px] {{ $row['webCategory'] ? 'text-ink_text-muted' : 'text-warning' }}">
                        {{ $row['webCategory'] ? 'Website: '.$row['webCategory']->name : 'Not a website category' }}
                        @if ($item->storefrontCollection) &middot; {{ $item->storefrontCollection->name }} @endif
                    </div>
                </td>
                <td class="text-right tabular">{{ number_format((float) ($item->net_weight ?: $item->weight), 3) }} g</td>
                <td class="text-right tabular font-semibold text-ink_text-primary">₹{{ \App\Support\Money::inr($row['price']) }}</td>
                <td><x-ui.status :status="$item->status" /></td>
                <td>
                    @if ($row['live'])
                        <x-ui.badge tone="success" size="sm" dot>Live</x-ui.badge>
                        <div class="text-[11.5px] text-ink_text-muted mt-1">since {{ $item->listed_at?->format('j M Y') }}</div>
                    @elseif ($item->show_on_website)
                        <x-ui.badge tone="warning" size="sm" dot>Not showing</x-ui.badge>
                        <div class="text-[11.5px] text-ink_text-muted mt-1 max-w-[200px]">{{ $row['reason'] }}</div>
                    @else
                        <x-ui.badge size="sm">Off</x-ui.badge>
                    @endif
                </td>
                <td>
                    <div class="flex items-center justify-end gap-1">
                        @if ($row['live'])
                            <x-ui.button variant="ghost" size="icon-sm" icon="external-link" :href="route('storefront.product', $item->slug)" target="_blank" title="Open on the website" aria-label="Open {{ $item->web_name }} on the website" />
                        @endif
                        <x-ui.button variant="ghost" size="icon-sm" icon="edit" title="Edit website details" aria-label="Edit website details"
                            x-on:click="Livewire.dispatch('open-item-form', { id: {{ $item->id }}, tab: 'website' })" />
                        <x-ui.button variant="{{ $item->show_on_website ? 'ghost' : 'soft' }}" size="sm" :icon="$item->show_on_website ? 'eye-off' : 'globe'"
                            wire:click="toggle({{ $item->id }})" target="toggle({{ $item->id }})">
                            {{ $item->show_on_website ? 'Take off' : 'Put on site' }}
                        </x-ui.button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">
                    <x-ui.empty-state icon="globe" title="No pieces match" message="Try another filter, or add pieces in Stock first." compact />
                </td>
            </tr>
        @endforelse
    </x-ui.datatable>

    <livewire:stock.item-form />
</div>
