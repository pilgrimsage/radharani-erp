@props([
    'items',
    'results',
    'label' => 'Pieces',
    'placeholder' => 'Scan a tag, packet or box, or type a code or category',
    'empty' => 'Nothing picked yet. Scan tags one after another and each one lands here.',
])
{{-- Scan-or-search basket for components using App\Livewire\Movement\Concerns\PicksItems. --}}
<div x-data="{ open: true }" x-on:picker-ready.window="window.matchMedia('(pointer: fine)').matches && $nextTick(() => $refs.pick && $refs.pick.focus())">
    <label for="pick-input" class="rj-label">{{ $label }}</label>
    <div class="relative flex gap-2" x-on:click.outside="open = false">
        <div class="rj-input-icon flex-1 min-w-0">
            <x-ui.icon name="scan" :size="16" />
            <input id="pick-input" x-ref="pick" type="text" autocomplete="off" wire:model.live.debounce.250ms="pickSearch"
                x-on:focus="open = true" x-on:input="open = true"
                x-on:keydown.enter.prevent="if ($event.target.value.trim()) $wire.addByCode($event.target.value)"
                x-on:keydown.escape="open = false"
                placeholder="{{ $placeholder }}"
                class="rj-input h-11 pr-3 sm:pr-24 @error('pickSearch') is-invalid @enderror @error('basket') is-invalid @enderror">
            <span class="hidden sm:inline absolute right-3 top-1/2 -translate-y-1/2 text-[11.5px] text-ink_text-muted pointer-events-none" wire:loading.remove wire:target="addByCode,addToBasket">Enter to add</span>
            <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gold" wire:loading wire:target="addByCode,addToBasket"><x-ui.icon name="loader" :size="15" class="animate-spin" /></span>
        </div>
        <x-ui.scan-button target="#pick-input" submit="enter" continuous :title="$label" variant="button" label="Scan" class="!h-11 !px-3.5 sm:!px-4" />

        @if ($results->isNotEmpty())
            <div x-show="open" x-cloak class="absolute left-0 right-0 top-full mt-1.5 z-dropdown bg-white rounded-control ring-1 ring-line shadow-pop overflow-hidden max-h-72 overflow-y-auto">
                @foreach ($results as $r)
                    <button type="button" wire:key="pick-{{ $r->id }}" wire:click="addToBasket({{ $r->id }})"
                        class="w-full flex items-center gap-3 px-3.5 py-2.5 text-left border-b border-line-light last:border-0 hover:bg-gold-tint/60 transition-colors">
                        <x-movement.metal-dot :metal="$r->metal" />
                        <span class="rj-code text-ink_text-primary">{{ $r->label }}</span>
                        <span class="flex-1 min-w-0 text-[12.5px] text-ink_text-secondary truncate">{{ $r->category }} · {{ $r->purity }}{{ $r->packet ? ' · ' . $r->packet->code : '' }}</span>
                        <span class="text-[12.5px] font-semibold tabular text-ink_text-primary">{{ number_format($r->weight, 3) }} g</span>
                        <x-ui.icon name="plus" :size="14" class="text-gold-dark shrink-0" />
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @error('pickSearch')
        <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" class="shrink-0" />{{ $message }}</p>
    @else
        @error('basket')
            <p class="rj-error"><x-ui.icon name="alert-triangle" :size="12" class="shrink-0" />{{ $message }}</p>
        @enderror
    @enderror

    @if ($items->isNotEmpty())
        <div class="mt-3 rounded-xl ring-1 ring-inset ring-line-light overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-3.5 h-10 bg-surface-sunken border-b border-line-light">
                <span class="text-[12.5px] text-ink_text-secondary">
                    <span class="font-bold text-ink_text-primary tabular">{{ $items->count() }}</span> {{ \Illuminate\Support\Str::plural('piece', $items->count()) }}
                    · <span class="font-semibold text-ink_text-primary tabular">{{ number_format($items->sum('weight'), 3) }} g</span>
                </span>
                <button type="button" wire:click="clearBasket" class="text-[12px] font-semibold text-ink_text-secondary hover:text-danger">Clear all</button>
            </div>
            <ul class="divide-y divide-line-light max-h-[300px] overflow-y-auto">
                @foreach ($items as $it)
                    <li wire:key="basket-{{ $it->id }}" class="flex items-center gap-3 px-3.5 py-2.5 bg-white animate-rise-in">
                        <x-movement.metal-dot :metal="$it->metal" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="rj-code text-ink_text-primary">{{ $it->label }}</span>
                                @if ($it->status !== 'in_stock')<x-ui.status :status="$it->status" size="sm" />@endif
                            </div>
                            <div class="text-[12px] text-ink_text-muted truncate">{{ $it->category }} · {{ $it->purity }}{{ $it->packet ? ' · ' . $it->packet->code : '' }}</div>
                        </div>
                        <span class="text-[13px] font-semibold tabular">{{ number_format($it->weight, 3) }} <span class="font-normal text-ink_text-muted">g</span></span>
                        <button type="button" wire:click="removeFromBasket({{ $it->id }})" aria-label="Remove {{ $it->label }}"
                            class="w-7 h-7 shrink-0 rounded-md text-ink_text-muted hover:text-danger hover:bg-danger-bg flex items-center justify-center">
                            <x-ui.icon name="x" :size="14" />
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="mt-3 flex items-center gap-3 px-4 py-3.5 rounded-xl border border-dashed border-line text-[12.5px] text-ink_text-muted">
            <x-ui.icon name="package" :size="17" class="text-gold shrink-0" />
            {{ $empty }}
        </div>
    @endif
</div>
