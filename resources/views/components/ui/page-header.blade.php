@props(['title', 'subtitle' => null, 'crumbs' => []])
{{-- crumbs: [['label' => 'Stock', 'href' => route(...)], ['label' => 'Boxes']] --}}
<div class="relative z-20 mb-7 animate-rise-in">
    @if (count($crumbs))
        <nav aria-label="Breadcrumb" class="flex items-center flex-wrap gap-1.5 text-[12.5px] font-medium text-ink_text-muted mb-2.5">
            @foreach ($crumbs as $crumb)
                @if (! empty($crumb['href']))
                    <a href="{{ $crumb['href'] }}" class="text-ink_text-secondary hover:text-gold-dark">{{ $crumb['label'] }}</a>
                @else
                    <span class="text-ink_text-primary">{{ $crumb['label'] }}</span>
                @endif
                @unless ($loop->last)
                    <x-ui.icon name="chevron-right" :size="12" class="text-ink_text-muted/70" />
                @endunless
            @endforeach
        </nav>
    @endif
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="font-display text-[34px] leading-[1.1] font-semibold text-ink_text-primary tracking-[-0.01em]">{{ $title }}</h1>
                @isset($meta)
                    <div class="flex items-center gap-2 pt-1">{{ $meta }}</div>
                @endisset
            </div>
            @if ($subtitle)
                <p class="text-[13.5px] text-ink_text-secondary mt-1.5 max-w-[72ch]">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2.5 max-w-full sm:shrink-0">{{ $actions }}</div>
        @endisset
    </div>
</div>
