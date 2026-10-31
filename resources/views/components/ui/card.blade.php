@props(['title' => null, 'subtitle' => null, 'icon' => null, 'padding' => true])
@php $hasHeader = $title || isset($actions); @endphp
<section {{ $attributes->merge(['class' => 'bg-white border border-line-light rounded-card shadow-card' . (! $hasHeader && $padding ? ' p-5' : '')]) }}>
    @if ($hasHeader)
        <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 min-h-[60px] py-3 border-b border-line-light">
            <div class="flex items-center gap-3 min-w-0">
                @if ($icon)
                    <div class="w-8 h-8 shrink-0 rounded-lg bg-gold-tint text-gold-dark flex items-center justify-center">
                        <x-ui.icon :name="$icon" :size="15" />
                    </div>
                @endif
                <div class="min-w-0">
                    <h3 class="text-[14.5px] font-bold text-ink_text-primary truncate">{{ $title }}</h3>
                    @if ($subtitle)
                        <p class="text-[12.5px] text-ink_text-secondary mt-0.5 truncate">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2 max-w-full sm:shrink-0">{{ $actions }}</div>
            @endisset
        </header>
        <div @class(['p-5' => $padding])>{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</section>
