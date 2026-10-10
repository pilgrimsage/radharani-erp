@extends('storefront.layout')
@php
    use App\Support\StorefrontImage as Img;
    $shop = fn (array $q = []) => route('storefront.catalog', $q);
    $numberWords = [1 => 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve'];
    $inr = fn ($n) => '₹'.\App\Support\Money::inr($n);
@endphp

@section('before')
<!-- first-visit loader (home only; other pages use the curtain) -->
<div class="loader" aria-hidden="true">
  <div class="loader__inner">
    <img class="loader__mark" src="{{ \App\Support\StorefrontAsset::url('img/mark.png') }}" alt="">
    <div class="loader__bar"><i></i></div>
  </div>
</div>
@endsection

@section('content')
<main id="top">

  <!-- ================= HERO CAROUSEL ================= -->
  <section class="hero" aria-label="Featured">
    <div class="wrap">
      <div class="stage" data-carousel>
        <article class="slide is-active">
          <figure class="media slide__media"><img data-parallax src="https://images.unsplash.com/photo-1781077126479-437220427c93?auto=format&fit=crop&w=1800&q=80" alt="Bride in traditional jewellery with mehndi on her hands" fetchpriority="high"></figure>
          <div class="slide__copy">
            <span class="eyebrow" data-anim>The bridal edit</span>
            <h1 class="h-xl" data-anim>Made for the <em>mandap.</em></h1>
            <p class="lede" data-anim>Heirloom sets in 22K gold, set aside for your family over as many visits as it takes.</p>
            <div data-anim><a class="btn btn--solid" href="{{ $shop(['occasion' => 'bridal']) }}" data-magnetic>See bridal sets</a></div>
          </div>
        </article>
        <article class="slide">
          <figure class="media slide__media"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1774437778651-b711670f3192?auto=format&fit=crop&w=1800&q=80" alt="Woman in traditional attire wearing ornate temple jewellery"></figure>
          <div class="slide__copy">
            <span class="eyebrow" data-anim>Temple gold</span>
            <h2 class="h-xl" data-anim>Shaped by <em>hand.</em></h2>
            <p class="lede" data-anim>Lakshmi haars, kasu malas and jhumkas, made by the same karigar families for three generations.</p>
            <div data-anim><a class="btn btn--solid" href="{{ $shop(['collection' => 'temple']) }}" data-magnetic>See temple gold</a></div>
          </div>
        </article>
        <article class="slide">
          <figure class="media slide__media"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1723879580148-517048db5bd9?auto=format&fit=crop&w=1800&q=80" alt="Woman wearing a light gold necklace and earrings"></figure>
          <div class="slide__copy">
            <span class="eyebrow" data-anim>Everyday gold</span>
            <h2 class="h-xl" data-anim>Light enough <em>for Monday.</em></h2>
            <p class="lede" data-anim>Chains, studs and slim bangles you will forget you are wearing. Hallmarked, like everything we sell.</p>
            <div data-anim><a class="btn btn--solid" href="{{ $shop(['collection' => 'daily-gold']) }}" data-magnetic>See everyday pieces</a></div>
          </div>
        </article>

        <div class="stage__ui">
          <div class="dots" role="tablist" aria-label="Choose slide">
            <button class="dot" aria-label="Slide 1"><i></i></button>
            <button class="dot" aria-label="Slide 2"><i></i></button>
            <button class="dot" aria-label="Slide 3"><i></i></button>
          </div>
          <div class="arrows">
            <button class="arrow" data-prev aria-label="Previous slide"><i class="ph ph-caret-left"></i></button>
            <button class="arrow" data-next aria-label="Next slide"><i class="ph ph-caret-right"></i></button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= TRUST ================= -->
  <section class="trust" aria-label="Our promise">
    <div class="wrap">
      <ul data-rise>
        <li><i class="ph ph-seal-check"></i><div><b>BIS hallmarked</b><span>Purity certified on every piece</span></div></li>
        <li><i class="ph ph-fingerprint"></i><div><b>HUID on every piece</b><span>A unique ID you can check</span></div></li>
        <li><i class="ph ph-scales"></i><div><b>Weighed in front of you</b><span>The scale faces your side</span></div></li>
        <li><i class="ph ph-arrows-clockwise"></i><div><b>Old gold exchange</b><span>Melted and tested with you</span></div></li>
      </ul>
    </div>
  </section>

  <!-- ================= CATEGORIES ================= -->
  @if (count($categories))
  <section class="section" id="categories">
    <div class="wrap">
      <div class="head-row">
        <h2 class="h-lg" data-split>Shop by <em>category</em></h2>
        <a class="link" href="{{ $shop() }}">View all jewellery <i class="ph ph-arrow-right"></i></a>
      </div>
      <div class="cat-grid">
        @foreach ($categories as $c)
        <a class="cat zoom" href="{{ $shop(['category' => $c['slug']]) }}"><figure class="media"><img loading="lazy" src="{{ Img::sized($c['img'], 400, 400, 75) }}" alt="{{ $c['name'] }}"></figure><span>{{ $c['name'] }}</span></a>
        @endforeach
        @if (collect($rj['metals'])->contains('silver'))
        <a class="cat zoom" href="{{ $shop(['metal' => 'silver']) }}"><figure class="media"><img loading="lazy" src="https://images.unsplash.com/photo-1573408301185-9146fe634ad0?auto=format&fit=crop&w=400&h=400&q=75" alt="Silver piece with clear stones"></figure><span>Silver</span></a>
        @endif
      </div>
    </div>
  </section>
  @endif

  <!-- ================= SHOP BY BUDGET ================= -->
  <section class="section budget" id="budget">
    <div class="wrap">
      <div class="head-row">
        <h2 class="h-lg" data-split>Shop by <em>budget</em></h2>
        <p class="muted">Tell us your number. We will show you what it buys today.</p>
      </div>
      <div class="budget-grid">
        @foreach ($budgets as $b)
        <a class="band zoom" data-rise href="{{ $shop(['budget' => $b['slug']]) }}">
          <figure class="media"><img data-parallax loading="lazy" src="{{ Img::sized($b['image'], 700, null, 75) }}" alt="{{ $b['alt'] }}"></figure>
          <span class="band__go"><i class="ph ph-arrow-right"></i></span>
          <div class="band__txt"><small>{{ $b['small'] }}</small><strong>{{ $b['strong'] }}</strong></div>
        </a>
        @endforeach
      </div>
    </div>
  </section>

  <!-- ================= COLLECTIONS ================= -->
  @if (count($collections))
  <section class="collections section" id="collections">
    <div class="wrap head-row">
      <div>
        <span class="eyebrow">Collections</span>
        <h2 class="h-lg" data-split>{{ ($numberWords[count($collections)] ?? 'Many').' '.(count($collections) === 1 ? 'story' : 'stories') }}, <em>one bench.</em></h2>
      </div>
      <a class="link" href="{{ $shop() }}">Browse every collection <i class="ph ph-arrow-right"></i></a>
    </div>
    <div class="col-view">
      <div class="col-track">
        @foreach ($collections as $c)
        <a class="col-card zoom" href="{{ $shop(['collection' => $c['slug']]) }}">
          <figure class="media"><img loading="lazy" src="{{ Img::sized($c['img'], 800) }}" alt="{{ $c['name'] }}"></figure>
          <h3>{{ $c['name'] }}</h3><p>{{ $c['blurb'] }}</p>
        </a>
        @endforeach
      </div>
    </div>
  </section>
  @endif

  <!-- ================= SHOP FOR ================= -->
  <section class="section" id="shop-for">
    <div class="wrap">
      <div class="head-row"><h2 class="h-lg" data-split>Who is it <em>for?</em></h2></div>
      <div class="for-grid">
        <a class="for for--tall zoom" data-rise href="{{ $shop(['for' => 'women']) }}">
          <figure class="media"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1582797536372-862bb193b69a?auto=format&fit=crop&w=1000&q=78" alt="Woman in a yellow dress wearing a gold necklace"></figure>
          <div class="for__txt"><div><h3>For her</h3><p>Necklaces, jhumkas, bangles and more</p></div><i class="ph ph-arrow-right"></i></div>
        </a>
        <a class="for zoom" data-rise href="{{ $shop(['for' => 'men']) }}">
          <figure class="media"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1780565336226-b53294a9592d?auto=format&fit=crop&w=1000&q=78" alt="Layered gold chains worn over a black t-shirt"></figure>
          <div class="for__txt"><div><h3>For him</h3><p>Chains, kadas and signet rings</p></div><i class="ph ph-arrow-right"></i></div>
        </a>
        <a class="for zoom" data-rise href="{{ $shop(['for' => 'kids']) }}">
          <figure class="media"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1780329917066-273e537df7bc?auto=format&fit=crop&w=1000&q=78" alt="Baby wearing small bracelets"></figure>
          <div class="for__txt"><div><h3>For little ones</h3><p>Nazariya, first bracelets, tiny studs</p></div><i class="ph ph-arrow-right"></i></div>
        </a>
      </div>
    </div>
  </section>

  <!-- ================= NEW ARRIVALS ================= -->
  <!-- filled by home.js from RJ_DATA (pieces listed in the last few weeks) -->
  <section class="section arrivals" id="arrivals">
    <div class="wrap">
      <div class="head-row">
        <h2 class="h-lg" data-split>New this <em>week</em></h2>
        <div class="row-ctrl"><a class="link" href="{{ $shop(['sort' => 'new']) }}">View all new arrivals <i class="ph ph-arrow-right"></i></a>
          <button class="arrow" data-row-prev aria-label="Previous products"><i class="ph ph-caret-left"></i></button>
          <button class="arrow" data-row-next aria-label="Next products"><i class="ph ph-caret-right"></i></button>
        </div>
      </div>
      <div class="prod-row" data-row></div>
    </div>
  </section>

  <!-- ================= BRIDAL ================= -->
  <section class="bridal" id="bridal">
    <div class="bridal__intro">
      <span class="eyebrow">Bridal</span>
      <h2 class="h-lg">For the day she has <em>pictured for years</em></h2>
    </div>
    <figure class="bridal__frame media">
      <img loading="lazy" src="https://images.unsplash.com/photo-1733937108021-d5db469f2216?auto=format&fit=crop&w=2000&q=80" alt="Bride in a yellow and red outfit wearing heavy gold jewellery">
    </figure>
    <div class="bridal__copy">
      <h2 class="h-lg">The trousseau, <em>built piece by piece.</em></h2>
      <ul class="bridal__points">
        <li><i class="ph ph-check-circle"></i>Pieces held for your family between visits</li>
        <li><i class="ph ph-check-circle"></i>Your old gold exchanged against the new set</li>
        <li><i class="ph ph-check-circle"></i>Private viewing for the whole family</li>
      </ul>
      <div><a class="btn btn--solid" href="{{ $shop(['occasion' => 'bridal']) }}" data-magnetic>See bridal sets</a></div>
    </div>
  </section>

  <!-- ================= HOW WE PRICE ================= -->
  <section class="section" id="price">
    <div class="wrap price-grid">
      <div class="price-head">
        <h2 class="h-lg" data-split>No hidden <em>numbers.</em></h2>
        <p class="lede">Every bill shows the same four lines. Here is how a 10 gram chain adds up at this morning's rate.</p>
        <ul>
          <li><i class="ph ph-seal-check"></i><span>Purity is stamped, and the HUID can be checked on the BIS Care app before you pay.</span></li>
          <li><i class="ph ph-scales"></i><span>Net weight is taken on a scale that faces you.</span></li>
          <li><i class="ph ph-hand-coins"></i><span>Making charges are shown on their own line, never folded into the rate.</span></li>
        </ul>
      </div>
      <div class="bill" data-bill>
        <div class="bill__top"><h3>Example bill</h3><span>10 g gold chain</span></div>
        <div class="bill__row" data-bill-row><span>Gold rate today<small>per gram</small></span><b data-count="{{ round($bill['rate']) }}" data-prefix="₹">{{ $inr($bill['rate']) }}</b></div>
        <div class="bill__row" data-bill-row><span>Net weight</span><b data-count="{{ $bill['weight'] }}" data-suffix=".000 g">{{ $bill['weight'] }}.000 g</b></div>
        <div class="bill__row bill__row--sub" data-bill-row><span>Gold value</span><b data-count="{{ $bill['value'] }}" data-prefix="₹">{{ $inr($bill['value']) }}</b></div>
        <div class="bill__row" data-bill-row><span>Making charges<small>12% of gold value</small></span><b data-count="{{ $bill['making'] }}" data-prefix="₹">{{ $inr($bill['making']) }}</b></div>
        <div class="bill__total" data-bill-row><span>You pay</span><b data-count="{{ $bill['total'] }}" data-prefix="₹">{{ $inr($bill['total']) }}</b></div>
        <p class="bill__note">Example only. Making charges vary by design and are quoted before you decide.</p>
      </div>
    </div>
  </section>

  <!-- ================= OLD GOLD EXCHANGE ================= -->
  <section class="section exchange" id="exchange">
    <div class="wrap">
      <h2 class="h-md">Bring your old gold. Watch every step.</h2>
      <div class="verbs" aria-label="Weigh, melt, test twice"><span class="verb">Weigh.</span><span class="verb">Melt.</span><span class="verb">Test twice.</span></div>
      <div class="ex-foot">
        <div class="ex-steps">
          <p><b>Weigh</b>Gross weight is taken on the counter scale, facing you.</p>
          <p><b>Melt</b>The piece is melted in the showroom, so it never leaves your sight.</p>
          <p><b>Test twice</b>Purity is tested twice and averaged. You see the deduction before anything is adjusted.</p>
        </div>
        <div><a class="btn btn--ghost" data-wa data-msg="I'd like to exchange some old gold. What should I bring?" href="https://wa.me/{{ $config['whatsapp'] }}" data-magnetic>Ask about exchange</a></div>
      </div>
    </div>
  </section>

  <!-- ================= GIFTING ================= -->
  <section class="section" id="gifting">
    <div class="wrap gift-grid">
      <div class="gift-art">
        <figure class="media gift-a"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1680200256120-8ac04eb6f01d?auto=format&fit=crop&w=900&q=78" alt="An open jewellery box with a necklace inside"></figure>
        <figure class="media gift-b" data-speed="1.08"><img loading="lazy" src="https://images.unsplash.com/photo-1654700194977-a1f7cc4d7884?auto=format&fit=crop&w=500&h=500&q=78" alt="A pair of rings in a ring box"></figure>
      </div>
      <div class="gift-copy">
        <span class="eyebrow">Gifting</span>
        <h2 class="h-lg" data-split>Gifts that stay <em>in the family.</em></h2>
        <p class="lede">Pick the occasion and we will show you what families usually choose. Every piece is gift-wrapped when you collect it.</p>
        <div class="chips">
          <a class="chip" href="{{ $shop(['occasion' => 'festive']) }}"><i class="ph ph-sparkle"></i>Akshaya Tritiya</a>
          <a class="chip" href="{{ $shop(['occasion' => 'festive', 'purity' => '22K']) }}"><i class="ph ph-sparkle"></i>Dhanteras</a>
          <a class="chip" href="{{ $shop(['occasion' => 'anniversary']) }}"><i class="ph ph-heart"></i>Anniversary</a>
          <a class="chip" href="{{ $shop(['occasion' => 'birthday']) }}"><i class="ph ph-gift"></i>Birthday</a>
          <a class="chip" href="{{ $shop(['for' => 'kids']) }}"><i class="ph ph-baby"></i>Annaprashan</a>
          <a class="chip" href="{{ $shop(['occasion' => 'rakhi']) }}"><i class="ph ph-gift"></i>Raksha Bandhan</a>
          <a class="chip" href="{{ $shop(['occasion' => 'bridal', 'budget' => '0-25000']) }}"><i class="ph ph-hand-coins"></i>Wedding shagun</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= HERITAGE ================= -->
  <section class="section" id="heritage">
    <div class="wrap">
      <p class="heritage__text" data-words>Our grandfather set the first stone at this bench. His karigars trained ours. We still weigh every piece <em>in front of you,</em> the way he did.</p>
      <div class="collage">
        <figure class="media collage__a"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1598724168411-9ba1e003a7fe?auto=format&fit=crop&w=1400&q=78" alt="A craftsman's hands holding a fine metal tool"></figure>
        <figure class="media media--arch collage__b" data-speed="1.08"><img loading="lazy" src="https://images.unsplash.com/photo-1774437891372-fccf47c0425d?auto=format&fit=crop&w=700&q=78" alt="An elderly woman in a pink sari wearing gold jewellery"></figure>
        <p class="collage__note">Three generations at one address. Many families here bought their mothers' wedding sets from us too.</p>
      </div>
    </div>
  </section>

  <!-- ================= REVIEWS ================= -->
  <!-- PLACEHOLDER REVIEWS: replace with real customer reviews (e.g. from Google) before launch -->
  <section class="section reviews" aria-label="Customer reviews">
    <div class="wrap head-row">
      <h2 class="h-lg" data-split>Families who <em>come back</em></h2>
      <p class="rv-note">Placeholder reviews. Replace with real Google reviews before launch.</p>
    </div>
    <div class="rv-track">
      @php
        $reviews = [
          ["They weighed my mother's old bangles in front of all of us and explained every deduction.", 'old gold exchange'],
          ['Four visits for the wedding set and nobody rushed us once. The haar was held for us each time.', 'bridal'],
          ['Sent photos and weights on WhatsApp within minutes. Picked up the jhumkas the same evening.', 'earrings'],
          ['The bill had every line written out. I checked the HUID on my phone before leaving.', 'chain'],
        ];
      @endphp
      @foreach ([false, true] as $copy)
        @foreach ($reviews as [$quote, $what])
      <article class="rv"@if ($copy) aria-hidden="true"@endif><div class="rv__stars"><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i><i class="ph-fill ph-star"></i></div><p>{{ $quote }}</p><footer><b>Customer name</b>, {{ $what }}</footer></article>
        @endforeach
      @endforeach
    </div>
  </section>

  <!-- ================= VISIT ================= -->
  <section class="section" id="visit">
    <div class="wrap visit-grid">
      <figure class="media media--arch visit-photo"><img data-parallax loading="lazy" src="https://images.unsplash.com/photo-1768359666502-306694fa6fcf?auto=format&fit=crop&w=1000&q=78" alt="Gold bracelets on display in a jewellery showroom"></figure>
      <div class="visit-copy">
        <h2 class="h-lg" data-split>Come and hold it <em>in your hand.</em></h2>
        <p class="lede">Photos help you choose. The weight in your palm tells you it is right. The chai is on us.</p>
        <div class="facts">
          <div class="fact"><i class="ph ph-map-pin"></i><div><small>Address</small><p>{{ $config['address'] }}</p></div></div>
          <div class="fact"><i class="ph ph-clock"></i><div><small>Hours</small><p>{{ $config['hours'] }}</p></div></div>
          <div class="fact"><i class="ph ph-phone"></i><div><small>Phone</small><p><a href="tel:{{ $config['tel'] }}">{{ $config['phone'] }}</a></p></div></div>
          @if ($config['parking'])
          <div class="fact"><i class="ph ph-car"></i><div><small>Parking</small><p>{{ $config['parking'] }}</p></div></div>
          @endif
        </div>
        <div class="map"><iframe title="Showroom location" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="{{ $config['mapsEmbed'] }}"></iframe></div>
        <div class="visit-actions">
          <a class="btn btn--solid" href="{{ $config['maps'] }}" target="_blank" rel="noopener" data-magnetic><i class="ph ph-navigation-arrow"></i>Get directions</a>
          <a class="btn btn--ghost" data-wa data-msg="I'd like to book a visit to the showroom." href="https://wa.me/{{ $config['whatsapp'] }}" data-magnetic>Book a visit</a>
        </div>
      </div>
    </div>
  </section>

</main>
@endsection
