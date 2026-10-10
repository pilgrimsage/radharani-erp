<x-layouts.guest title="Radharani Jewellery Works">
<x-auth.shell audience="welcome" heading="Every piece, accounted for."
    lede="The stock room, the counter and our customers, kept in step. Choose how you'd like to sign in.">

    <p class="text-[12px] font-bold uppercase tracking-[0.18em] text-gold-dark">Radharani Jewellery Works</p>
    <h1 class="font-display text-[40px] leading-[1.08] font-semibold mt-2">How are you signing in?</h1>
    <p class="text-[14px] text-ink_text-secondary mt-2">Staff and customers have separate, secure sign-ins.</p>

    <div class="mt-8 space-y-3">
        @foreach ([
            [route('login'), 'shield-check', 'I work at the shop', 'Stock, movements, billing and reports', true],
            [route('portal.login'), 'user', "I'm a customer", 'Your purchases, referrals and instalments', false],
        ] as [$href, $icon, $title, $sub, $primary])
            <a href="{{ $href }}"
               class="group press flex items-center gap-4 p-5 rounded-2xl border transition-[border-color,box-shadow,background-color]
                      {{ $primary ? 'bg-ink border-ink text-white hover:shadow-modal' : 'bg-white border-line-light hover:border-gold-soft hover:shadow-raised' }}">
                <span class="w-12 h-12 shrink-0 rounded-xl flex items-center justify-center {{ $primary ? 'gold-sheen text-ink shadow-gold' : 'bg-gold-tint text-gold-dark ring-1 ring-inset ring-gold-soft' }}">
                    <x-ui.icon :name="$icon" :size="20" />
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block text-[15.5px] font-bold {{ $primary ? 'text-white' : 'text-ink_text-primary' }}">{{ $title }}</span>
                    <span class="block text-[13px] mt-0.5 {{ $primary ? 'text-ink-fg' : 'text-ink_text-secondary' }}">{{ $sub }}</span>
                </span>
                <x-ui.icon name="arrow-right" :size="18" class="shrink-0 transition-transform group-hover:translate-x-1 {{ $primary ? 'text-gold-light' : 'text-ink_text-muted' }}" />
            </a>
        @endforeach
    </div>
</x-auth.shell>
</x-layouts.guest>
