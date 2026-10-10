@php
    use App\Support\Money;
    use App\Support\StorefrontImage;

    $inr = fn ($n) => '₹'.Money::inr($n);
    $sales = $customer->sales;
    $confirmedTotal = $sales->where('confirmed_by_accountant', true)->sum('total');
    $activeSchemes = $customer->installmentSchemes->where('status', 'active');
    $paidIntoSchemes = $customer->installmentSchemes->flatMap->payments->sum('amount');
    $phone = preg_replace('/^(\d{5})(\d{5})$/', '$1 $2', (string) $customer->phone);
    $shareText = "Shop at Radharani Jewellery Works with my referral code {$customer->referral_code}.";
    $tabs = [
        'purchases' => ['Purchases', $sales->count()],
        'installments' => ['Monthly scheme', $customer->installmentSchemes->count() ?: null],
        'referrals' => ['Referrals', $referredBuyers->count() ?: null],
    ];
@endphp
<div>
{{-- ============ Greeting + summary ============ --}}
<section class="pt-head">
    <div class="wrap">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><i class="ph ph-caret-right"></i><span aria-current="page">My account</span></nav>

        <div class="pt-head__row">
            <div class="pt-head__hello">
                <span class="eyebrow">My account</span>
                <h1 class="h-lg">Namaste, <em>{{ $firstName }}.</em></h1>
                <p class="pt-head__meta">
                    <span><i class="ph ph-phone"></i>+91 {{ $phone }}</span>
                    @if ($customer->email)<span><i class="ph ph-envelope-simple"></i>{{ $customer->email }}</span>@endif
                    <span><i class="ph ph-calendar-blank"></i>With us since {{ $customer->created_at?->format('F Y') }}</span>
                </p>
            </div>
            <div class="pt-head__actions">
                <a href="{{ route('portal.change-password') }}" class="btn btn--ghost btn--small"><i class="ph ph-lock-key"></i>Change password</a>
                <button type="button" class="btn btn--ghost btn--small" wire:click="logout" wire:loading.attr="disabled" wire:target="logout"><i class="ph ph-sign-out"></i>Sign out</button>
            </div>
        </div>

        <div class="pt-stats">
            <button type="button" class="pt-stat" wire:click="setTab('purchases')">
                <span class="pt-stat__icon"><i class="ph ph-receipt"></i></span>
                <small>Purchases</small>
                <b>{{ $sales->count() }}</b>
                <span>{{ $confirmedTotal > 0 ? $inr($confirmedTotal).' in total' : 'None yet' }}</span>
            </button>
            <button type="button" class="pt-stat" wire:click="setTab('installments')">
                <span class="pt-stat__icon"><i class="ph ph-calendar-check"></i></span>
                <small>Monthly scheme</small>
                <b>{{ $activeSchemes->count() ? $activeSchemes->count().' active' : 'None' }}</b>
                <span>{{ $paidIntoSchemes > 0 ? $inr($paidIntoSchemes).' paid so far' : 'Ask us about joining' }}</span>
            </button>
            <div class="pt-stat pt-stat--code" x-data="{ copied: false }">
                <span class="pt-stat__icon"><i class="ph ph-gift"></i></span>
                <small>Your referral code</small>
                @if ($customer->referral_code)
                    <b class="pt-code">{{ $customer->referral_code }}</b>
                    <button type="button" class="pt-copy" x-on:click="navigator.clipboard && navigator.clipboard.writeText('{{ $customer->referral_code }}').then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                        <i class="ph" :class="copied ? 'ph-check' : 'ph-copy'"></i><span x-text="copied ? 'Copied' : 'Copy code'">Copy code</span>
                    </button>
                @else
                    <b>&ndash;</b>
                    <span>Ask at the counter for one</span>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ============ Tabs ============ --}}
<div class="pt-tabs-bar">
    <div class="wrap">
        <nav class="pt-tabs" role="tablist" aria-label="My account">
            @foreach ($tabs as $key => [$label, $count])
                <button type="button" role="tab" data-tab="{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['pt-tab', 'is-on' => $tab === $key]) wire:click="setTab('{{ $key }}')">
                    {{ $label }}@if ($count)<span class="pt-tab__count">{{ $count }}</span>@endif
                </button>
            @endforeach
        </nav>
    </div>
</div>

<section class="pt-body wrap" wire:loading.class="is-loading" wire:target="setTab">

    {{-- ============ Purchases ============ --}}
    @if ($tab === 'purchases')
        @forelse ($sales as $sale)
            @php
                $pending = ! $sale->confirmed_by_accountant;
                $gst = (float) $sale->cgst + (float) $sale->sgst + (float) $sale->igst;
            @endphp
            <article class="pt-card pt-invoice" wire:key="sale-{{ $sale->id }}">
                <header class="pt-invoice__head">
                    <div>
                        <small>{{ $pending ? 'Being confirmed' : 'Invoice' }}</small>
                        <h3>{{ $pending ? 'Purchase on '.$sale->created_at?->format('j M Y') : $sale->invoice_number }}</h3>
                        <span class="pt-invoice__date">{{ $sale->created_at?->format('l, j F Y') }} &middot; {{ $sale->items->count() }} {{ Str::plural('piece', $sale->items->count()) }}</span>
                    </div>
                    <div class="pt-invoice__total">
                        <span @class(['pt-pill', 'pt-pill--ok' => ! $pending, 'pt-pill--wait' => $pending])>
                            <i class="ph {{ $pending ? 'ph-hourglass-medium' : 'ph-seal-check' }}"></i>{{ $pending ? 'Awaiting confirmation' : 'Confirmed' }}
                        </span>
                        <b>{{ $inr($sale->total) }}</b>
                    </div>
                </header>

                <ul class="pt-lines">
                    @foreach ($sale->items as $piece)
                        @php
                            $photo = $piece->images->first()?->url;
                            $name = $piece->web_name ?: trim(($piece->purity ? $piece->purity.' ' : '').$piece->category);
                        @endphp
                        <li class="pt-line">
                            <figure class="media pt-line__img">
                                @if ($photo)<img src="{{ StorefrontImage::sized($photo, 160, 160) }}" alt="" loading="lazy">@else<i class="ph ph-diamond"></i>@endif
                            </figure>
                            <div class="pt-line__txt">
                                <b>{{ $name }}</b>
                                <span>{{ ucfirst((string) $piece->metal) }}{{ $piece->purity ? ' '.$piece->purity : '' }} &middot; {{ number_format((float) ($piece->net_weight ?: $piece->weight), 3) }} g &middot; {{ $piece->huid_code ? 'HUID '.$piece->huid_code : 'Code '.$piece->internal_code }}</span>
                            </div>
                            <span class="pt-line__price">{{ $inr($piece->pivot->price_at_sale) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="pt-sum">
                    @if ($gst > 0)<div><dt>GST</dt><dd>{{ $inr($gst) }}</dd></div>@endif
                    @if ((float) $sale->discount > 0)<div><dt>Discount</dt><dd>&minus;{{ $inr($sale->discount) }}</dd></div>@endif
                    <div class="pt-sum__total"><dt>Total</dt><dd>{{ $inr($sale->total) }}</dd></div>
                </dl>
                @if ($pending)
                    <p class="pt-note"><i class="ph ph-info"></i>The shop confirms every sale before issuing the final invoice number. This usually happens the same day.</p>
                @endif
            </article>
        @empty
            <div class="pt-empty">
                <i class="ph ph-receipt"></i>
                <h3>No purchases yet</h3>
                <p>When you buy from the showroom, your invoices appear here with every piece and its weight.</p>
                <a href="{{ route('storefront.catalog') }}" class="btn btn--solid">Browse jewellery</a>
            </div>
        @endforelse
    @endif

    {{-- ============ Monthly scheme ============ --}}
    @if ($tab === 'installments')
        @forelse ($customer->installmentSchemes as $scheme)
            @php $paid = $scheme->payments->sum('amount'); @endphp
            <article class="pt-card pt-scheme" wire:key="scheme-{{ $scheme->id }}">
                <header class="pt-scheme__head">
                    <div>
                        <small>Monthly scheme</small>
                        <h3>{{ $inr($scheme->monthly_amount) }} <span>a month</span></h3>
                        <span class="pt-invoice__date">Started {{ $scheme->start_date?->format('j F Y') }}</span>
                    </div>
                    <span @class([
                        'pt-pill',
                        'pt-pill--ok' => $scheme->status === 'active',
                        'pt-pill--done' => $scheme->status === 'completed',
                        'pt-pill--wait' => $scheme->status === 'defaulted',
                    ])>{{ ['active' => 'Active', 'completed' => 'Completed', 'defaulted' => 'Paused'][$scheme->status] ?? ucfirst($scheme->status) }}</span>
                </header>

                <div class="pt-scheme__figures">
                    <div><small>Months paid</small><b>{{ $scheme->months_paid }}</b></div>
                    <div><small>Paid so far</small><b>{{ $inr($paid) }}</b></div>
                    <div><small>Last payment</small><b>{{ $scheme->payments->last()?->paid_on?->format('j M Y') ?? '–' }}</b></div>
                </div>

                @if ($scheme->payments->isNotEmpty())
                    <ol class="pt-payments">
                        @foreach ($scheme->payments as $i => $payment)
                            <li wire:key="pay-{{ $payment->id }}">
                                <span class="pt-payments__n">{{ $i + 1 }}</span>
                                <span class="pt-payments__date">{{ $payment->paid_on?->format('j M Y') }}</span>
                                <span class="pt-payments__amt">{{ $inr($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </article>
        @empty
            <div class="pt-empty">
                <i class="ph ph-calendar-check"></i>
                <h3>You're not in a monthly scheme</h3>
                <p>Put aside a fixed amount each month towards jewellery. Ask us how it works.</p>
                <a class="btn btn--solid" href="{{ \App\Models\Storefront\StorefrontSetting::whatsappUrl("Namaste. I'd like to know about your monthly jewellery scheme.") }}" target="_blank" rel="noopener"><i class="ph ph-whatsapp-logo"></i>Ask on WhatsApp</a>
            </div>
        @endforelse
    @endif

    {{-- ============ Referrals ============ --}}
    @if ($tab === 'referrals')
        <div class="pt-split">
            <div class="pt-card pt-refer" x-data="{ copied: false }">
                <span class="eyebrow">Refer a friend</span>
                @if ($customer->referral_code)
                    <b class="pt-refer__code">{{ $customer->referral_code }}</b>
                    <p>Share your code. When a friend buys from us with it, you earn referral points.@if ($referralPoints > 0) You have <strong>{{ number_format($referralPoints) }} points</strong> so far.@endif</p>
                    <div class="pt-refer__actions">
                        <a class="btn btn--solid" href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener"><i class="ph ph-whatsapp-logo"></i>Share on WhatsApp</a>
                        <button type="button" class="btn btn--ghost" x-on:click="navigator.clipboard && navigator.clipboard.writeText('{{ $customer->referral_code }}').then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
                            <i class="ph" :class="copied ? 'ph-check' : 'ph-copy'"></i><span x-text="copied ? 'Copied' : 'Copy code'">Copy code</span>
                        </button>
                    </div>
                @else
                    <p>Referral codes are for customers who ask for one. Ask at the counter and we will add one to your account.</p>
                @endif
            </div>

            <div class="pt-card pt-ledger">
                <h3 class="pt-card__title">People you've referred</h3>
                @forelse ($referredBuyers as $buys)
                    @php $friend = $buys->first()->customer; @endphp
                    <div class="pt-ledger__row" wire:key="ref-{{ $friend?->id }}">
                        <span class="pt-ledger__icon"><i class="ph ph-user"></i></span>
                        <div class="pt-ledger__txt">
                            <b>{{ $friend?->name }}</b>
                            <span>{{ $buys->count() }} {{ \Illuminate\Support\Str::plural('purchase', $buys->count()) }} with your code</span>
                        </div>
                        <span class="pt-pill pt-pill--ok">Thank you</span>
                    </div>
                @empty
                    <p class="pt-muted">No one yet. Friends who buy with your code show up here.</p>
                @endforelse
            </div>
        </div>
    @endif
</section>
</div>
