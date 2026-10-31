@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'maxWidth' => 'lg',
    'submit' => null, // Livewire method: wraps body + footer in <form wire:submit="...">
])
{{--
    Livewire-bound dialog. Open/close is a boolean property on the component:
        <x-ui.modal wire:model="showForm" title="New box" submit="save"> ... <x-slot:footer>...</x-slot:footer> </x-ui.modal>
    Escape, the backdrop, and the close button all set it back to false.
--}}
@php
$wireModel = $attributes->wire('model')->value();
$widths = ['sm' => 'sm:max-w-[420px]', 'md' => 'sm:max-w-[520px]', 'lg' => 'sm:max-w-[640px]', 'xl' => 'sm:max-w-[820px]', '2xl' => 'sm:max-w-[1040px]'];
$width = $widths[$maxWidth] ?? $widths['lg'];
$formTag = $submit ? 'form' : 'div';
@endphp
<div x-data="{ show: $wire.$entangle('{{ $wireModel }}') }"
     x-show="show" x-cloak
     x-on:keydown.escape.window="show = false"
     class="fixed inset-0 z-modal" role="dialog" aria-modal="true">
    <div x-show="show" x-transition.opacity.duration.200ms class="fixed inset-0 bg-[#1A150C]/45 backdrop-blur-[3px]"></div>

    <div class="fixed inset-0 overflow-y-auto">
        <div class="min-h-full flex items-end sm:items-center justify-center p-3 sm:p-6" x-on:click.self="show = false">
            <div x-show="show" x-trap.noscroll="show"
                 x-transition:enter="transition ease-silk duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-2 sm:scale-[.97]"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 sm:scale-100"
                 x-transition:leave-end="opacity-0 sm:scale-[.97]"
                 {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => "relative w-full $width bg-white rounded-[18px] shadow-modal ring-1 ring-black/[.04] overflow-clip"]) }}>

                <div class="h-[3px] gold-sheen"></div>

                @if ($title)
                    <div class="flex items-start gap-3 sm:gap-3.5 px-4 sm:px-6 pt-4 sm:pt-5 pb-3 sm:pb-4">
                        @if ($icon)
                            <div class="w-10 h-10 shrink-0 rounded-xl bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft/70 flex items-center justify-center">
                                <x-ui.icon :name="$icon" :size="18" />
                            </div>
                        @endif
                        <div class="min-w-0 flex-1 pt-0.5">
                            <h2 class="font-display text-[24px] leading-tight font-semibold text-ink_text-primary">{{ $title }}</h2>
                            @if ($subtitle)
                                <p class="text-[13px] text-ink_text-secondary mt-1">{{ $subtitle }}</p>
                            @endif
                        </div>
                        <button type="button" x-on:click="show = false" aria-label="Close"
                            class="press -mr-2 -mt-1 w-9 h-9 shrink-0 rounded-lg text-ink_text-muted hover:text-ink_text-primary hover:bg-surface-muted flex items-center justify-center">
                            <x-ui.icon name="x" :size="17" />
                        </button>
                    </div>
                @endif

                <{{ $formTag }} @if($submit) wire:submit="{{ $submit }}" @endif>
                    <div class="px-4 sm:px-6 {{ $title ? 'pb-5 sm:pb-6' : 'py-5 sm:py-6' }} max-h-[calc(100dvh-170px)] sm:max-h-[calc(100dvh-220px)] overflow-y-auto overscroll-contain">
                        {{ $slot }}
                    </div>
                    @isset($footer)
                        <div class="flex flex-wrap items-center justify-end gap-2.5 px-4 sm:px-6 py-3 sm:py-4 bg-surface-sunken border-t border-line-light">
                            {{ $footer }}
                        </div>
                    @endisset
                </{{ $formTag }}>
            </div>
        </div>
    </div>
</div>
