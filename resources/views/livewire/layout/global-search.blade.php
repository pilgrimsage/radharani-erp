<div class="relative flex-1 max-w-[440px]" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false; $refs.input.blur()">
    <div class="rj-input-icon">
        <x-ui.icon name="search" :size="16" />
        <input id="global-search" x-ref="input" type="text" wire:model.live.debounce.300ms="query" x-on:focus="open = true" x-on:input="open = true"
            placeholder="Search boxes, packets, pieces, customers, orders" autocomplete="off"
            class="rj-input h-10 bg-surface-sunken border-line-light pr-14 focus:bg-white">
        <kbd class="hidden md:flex absolute right-2.5 top-1/2 -translate-y-1/2 h-6 min-w-[24px] px-1.5 items-center justify-center rounded-md border border-line bg-white text-[11px] font-semibold text-ink_text-muted font-sans pointer-events-none"
             wire:loading.remove wire:target="query">/</kbd>
        <span wire:loading wire:target="query" class="absolute right-3 top-1/2 -translate-y-1/2 text-gold">
            <x-ui.icon name="loader" :size="15" class="animate-spin" />
        </span>
    </div>

    @if ($query && strlen($query) >= 2)
        @php $results = $this->results; @endphp
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-silk duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="absolute left-0 right-0 sm:right-auto sm:w-[520px] mt-2 bg-white border border-line-light rounded-xl shadow-pop max-h-[460px] overflow-y-auto z-dropdown p-1.5">
            @if (($results['boxes'] ?? collect())->isEmpty() && ($results['packets'] ?? collect())->isEmpty() && ($results['items'] ?? collect())->isEmpty() && ($results['customers'] ?? collect())->isEmpty() && ($results['orders'] ?? collect())->isEmpty())
                <div class="px-3 py-6 text-center">
                    <div class="text-[13px] font-semibold text-ink_text-primary">No matches for "{{ $query }}"</div>
                    <div class="text-[12px] text-ink_text-muted mt-0.5">Try a box or packet code, a HUID, an internal code, a customer phone or an order description.</div>
                </div>
            @else
                @foreach ([
                    'boxes' => ['Boxes', 'archive'],
                    'packets' => ['Packets', 'package'],
                    'items' => ['Inventory', 'gem'],
                    'customers' => ['Customers', 'user'],
                    'orders' => ['Orders', 'file-text'],
                ] as $key => [$heading, $icon])
                    @if (($results[$key] ?? collect())->isNotEmpty())
                        <div class="px-2.5 pt-2.5 pb-1.5 text-[11px] font-bold text-ink_text-muted uppercase tracking-[0.1em]">{{ $heading }}</div>
                        @foreach ($results[$key] as $row)
                            @php
                                [$href, $primary, $secondary] = match ($key) {
                                    'boxes' => [route('stock.boxes.show', $row), $row->code, $row->label],
                                    'packets' => [route('stock.packets.show', $row), $row->code, trim(($row->label ?? '') . ($row->box ? ' · in ' . $row->box->code : ''), ' ·')],
                                    'items' => [route('stock.items.show', $row), $row->huid_code ?: $row->internal_code, trim($row->category . ' ' . ($row->weight ? number_format($row->weight, 3) . ' g' : ''))],
                                    'customers' => [route('admin.customers.detail', $row), $row->name, $row->phone],
                                    'orders' => [route('orders.show', $row), $row->product_description, 'Order #' . $row->id],
                                };
                            @endphp
                            <a href="{{ $href }}" class="group flex items-center gap-3 px-2.5 py-2 rounded-lg hover:bg-surface-muted">
                                <span class="w-8 h-8 shrink-0 rounded-lg bg-gold-tint text-gold-dark flex items-center justify-center">
                                    <x-ui.icon :name="$icon" :size="15" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-semibold text-ink_text-primary truncate {{ in_array($key, ['boxes', 'packets', 'items']) ? 'rj-code' : '' }}">{{ $primary }}</span>
                                    <span class="block text-[12px] text-ink_text-muted truncate">{{ $secondary }}</span>
                                </span>
                                <x-ui.icon name="arrow-right" :size="14" class="text-ink_text-muted opacity-0 group-hover:opacity-100 transition-opacity" />
                            </a>
                        @endforeach
                    @endif
                @endforeach
            @endif
        </div>
    @endif
</div>
