@props([
    'audience' => 'staff', // staff | customer | welcome
    'heading' => null,
    'lede' => null,
])
{{--
    Shared frame for every sign-in / password page (staff and customer portal).
    lg and up: jewellery carousel on the left, form on the right.
    Below lg: brand mark on top, then the form only.
    Photos: public/images/auth (public-domain / CC0 museum photography, see CREDITS.md there).
--}}
@php
$slides = [
    ['img' => 'kundan-choker.webp', 'name' => 'Kundan choker', 'detail' => 'Gold, uncut diamonds, pearls and an emerald drop', 'pos' => '50% 40%'],
    ['img' => 'temple-necklace.webp', 'name' => 'Temple necklace', 'detail' => 'Gold set with rubies, emeralds and seed pearls', 'pos' => '50% 55%'],
    ['img' => 'gold-arm-bracelet.webp', 'name' => 'Gold arm bracelet', 'detail' => 'Chased gold with a ruby and pearls', 'pos' => '50% 45%'],
    ['img' => 'diamond-pearl-necklace.webp', 'name' => 'Diamond and pearl necklace', 'detail' => 'Old-cut diamonds with natural pearls', 'pos' => '50% 60%'],
];
$isStaff = $audience === 'staff';
$heading ??= $isStaff ? 'Every piece, accounted for.' : 'Your jewellery, always in view.';
$lede ??= $isStaff
    ? 'Stock, movements, karigar work and sales for Radharani Jewellery Works, in one place.'
    : 'Orders, purchases, referrals and instalments from Radharani Jewellery Works.';
@endphp

<div class="min-h-[100dvh] flex bg-surface-bg">
    {{-- ============================================================ Carousel (lg+) --}}
    <aside class="hidden lg:flex relative w-[52%] xl:w-[56%] shrink-0 overflow-hidden bg-ink text-white select-none"
        x-data="{
            i: 0, n: {{ count($slides) }}, paused: false, timer: null, dur: 6500,
            start() { clearInterval(this.timer); this.timer = setInterval(() => { if (!this.paused) this.next() }, this.dur) },
            next() { this.i = (this.i + 1) % this.n },
            prev() { this.i = (this.i - 1 + this.n) % this.n },
            go(k) { this.i = k; this.start() },
        }"
        x-init="start()"
        x-on:mouseenter="paused = true" x-on:mouseleave="paused = false"
        x-on:keydown.left.window="if (!['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) { prev(); start() }"
        x-on:keydown.right.window="if (!['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) { next(); start() }"
        aria-roledescription="carousel" aria-label="Jewellery from the collection">

        {{-- Slides --}}
        @foreach ($slides as $k => $s)
            <div class="absolute inset-0 transition-opacity duration-[1400ms] ease-in-out"
                 style="opacity: {{ $k === 0 ? 1 : 0 }}" :style="{ opacity: i === {{ $k }} ? 1 : 0 }"
                 role="group" aria-roledescription="slide" aria-label="{{ $k + 1 }} of {{ count($slides) }}: {{ $s['name'] }}">
                <img src="{{ asset('images/auth/' . $s['img']) }}" alt="{{ $s['name'] }}. {{ $s['detail'] }}."
                     @if($k > 0) loading="lazy" @endif decoding="async"
                     style="object-position: {{ $s['pos'] }}"
                     class="absolute inset-0 w-full h-full object-cover transition-transform duration-[8000ms] ease-out will-change-transform"
                     :style="{ transform: i === {{ $k }} ? 'scale(1)' : 'scale(1.08)' }">
            </div>
        @endforeach

        {{-- Legibility: dark falloff top and bottom, warm gold glow in the corner --}}
        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/5 to-black/85 pointer-events-none"></div>
        <div class="absolute inset-x-0 bottom-0 h-[62%] bg-gradient-to-t from-black/90 via-black/60 to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 bg-[radial-gradient(90%_60%_at_0%_100%,rgba(184,134,45,.28),transparent_60%)] pointer-events-none"></div>
        <div class="absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-gold-light/40 to-transparent"></div>

        <div class="relative z-[1] flex flex-col w-full p-10 xl:p-14">
            {{-- Brand --}}
            <div class="flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-3 text-white hover:text-white">
                    <x-ui.logo :size="52" class="w-[52px] h-[52px] drop-shadow-[0_4px_14px_rgba(0,0,0,.45)]" />
                    <span class="leading-none">
                        <span class="block font-display text-[26px] font-semibold tracking-[0.01em]">Radharani</span>
                        <span class="block text-[10.5px] font-semibold tracking-[0.26em] text-gold-light/90 mt-1">JEWELLERY WORKS</span>
                    </span>
                </a>
                <span class="h-8 px-3.5 inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur-md text-[12px] font-semibold text-white/90">
                    <x-ui.icon :name="['staff' => 'shield-check', 'customer' => 'user'][$audience] ?? 'gem'" :size="13" class="text-gold-light" />
                    {{ ['staff' => 'Staff access', 'customer' => 'Customer portal'][$audience] ?? 'Radharani ERP' }}
                </span>
            </div>

            {{-- Headline + caption --}}
            <div class="mt-auto">
                <h2 class="font-display text-[46px] xl:text-[54px] leading-[1.04] font-semibold max-w-[14ch] text-balance">{{ $heading }}</h2>
                <p class="text-[14.5px] text-white/75 mt-4 max-w-[46ch] leading-relaxed">{{ $lede }}</p>

                <div class="mt-10 flex items-end justify-between gap-6">
                    <div class="relative min-h-[46px] flex-1">
                        @foreach ($slides as $k => $s)
                            <div class="absolute inset-x-0 bottom-0 transition-[opacity,transform] duration-700"
                                 style="opacity: {{ $k === 0 ? 1 : 0 }}"
                                 :style="{ opacity: i === {{ $k }} ? 1 : 0, transform: i === {{ $k }} ? 'none' : 'translateY(8px)' }">
                                <div class="text-[13.5px] font-semibold text-gold-light">{{ $s['name'] }}</div>
                                <div class="text-[12.5px] text-white/60 mt-0.5">{{ $s['detail'] }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" x-on:click="prev(); start()" aria-label="Previous piece"
                            class="press w-10 h-10 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur-md hover:bg-white/20 flex items-center justify-center">
                            <x-ui.icon name="chevron-left" :size="17" />
                        </button>
                        <button type="button" x-on:click="next(); start()" aria-label="Next piece"
                            class="press w-10 h-10 rounded-full bg-white/10 ring-1 ring-white/15 backdrop-blur-md hover:bg-white/20 flex items-center justify-center">
                            <x-ui.icon name="chevron-right" :size="17" />
                        </button>
                    </div>
                </div>

                {{-- Progress indicators --}}
                <div class="mt-6 grid gap-2" style="grid-template-columns: repeat({{ count($slides) }}, minmax(0, 1fr))">
                    @foreach ($slides as $k => $s)
                        <button type="button" x-on:click="go({{ $k }})" class="group py-2" aria-label="Show {{ $s['name'] }}">
                            <span class="block h-[2px] rounded-full bg-white/20 overflow-hidden">
                                <span class="block h-full bg-gold-light origin-left" style="transform: scaleX(0)"
                                      :style="i === {{ $k }} ? `animation: rj-auth-bar ${dur}ms linear forwards; animation-play-state: ${paused ? 'paused' : 'running'}` : (i > {{ $k }} ? 'transform: scaleX(1)' : 'transform: scaleX(0)')"
></span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </aside>

    {{-- ============================================================ Form side --}}
    <main class="flex-1 min-w-0 flex flex-col">
        <div class="flex items-center justify-end gap-3 px-5 sm:px-8 pt-5 sm:pt-7 min-h-[52px]">
            @isset($aside)
                {{ $aside }}
            @endisset
        </div>

        <div class="flex-1 flex items-center justify-center px-5 sm:px-8 py-8">
            <div class="w-full max-w-[420px] animate-rise-in">
                {{-- Brand on small screens (the carousel is hidden there) --}}
                <div class="lg:hidden flex flex-col items-center text-center mb-9">
                    <x-ui.logo :size="76" class="w-[76px] h-[76px] drop-shadow-[0_6px_16px_rgba(184,134,45,.25)]" />
                    <span class="font-display text-[32px] font-semibold leading-none mt-4">Radharani</span>
                    <span class="text-[10.5px] font-semibold tracking-[0.28em] text-gold-dark mt-1.5">JEWELLERY WORKS</span>
                </div>

                {{ $slot }}
            </div>
        </div>

        <footer class="px-5 sm:px-8 pb-6 pt-2 flex flex-wrap items-center justify-center lg:justify-between gap-x-4 gap-y-1 text-[12px] text-ink_text-muted">
            <span>© {{ date('Y') }} Radharani Jewellery Works</span>
            <span>{{ ['staff' => 'Every sign-in is recorded against your account', 'customer' => 'Your details stay with the shop'][$audience] ?? 'Stock, sales and customer records in one place' }}</span>
        </footer>
    </main>
</div>

<style>
    @keyframes rj-auth-bar { from { transform: scaleX(0) } to { transform: scaleX(1) } }
</style>
