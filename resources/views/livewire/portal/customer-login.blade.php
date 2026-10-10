<div>
<section class="pt-auth wrap">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><i class="ph ph-caret-right"></i><span aria-current="page">Sign in</span></nav>

    <div class="pt-auth__grid">
        <div class="pt-card pt-auth__card">
            <span class="eyebrow">Your account</span>
            <h1 class="h-lg">Welcome <em>back.</em></h1>
            <p class="pt-auth__lede">Sign in with the mobile number you gave us at the showroom.</p>

            <form wire:submit="login" class="pt-form" novalidate>
                <div class="pt-field">
                    <label for="portal-phone">Mobile number</label>
                    <div class="pt-input pt-input--prefix @error('phone') is-invalid @enderror">
                        <span class="pt-input__prefix">+91</span>
                        <input id="portal-phone" type="tel" inputmode="numeric" maxlength="16" wire:model="phone" autofocus autocomplete="username" placeholder="98300 00000">
                    </div>
                    @error('phone') <p class="pt-error"><i class="ph ph-warning-circle"></i>{{ $message }}</p> @enderror
                </div>

                <div class="pt-field" x-data="{ show: false }">
                    <label for="portal-password">Password</label>
                    <div class="pt-input pt-input--suffix @error('password') is-invalid @enderror">
                        <input id="portal-password" :type="show ? 'text' : 'password'" type="password" wire:model="password" autocomplete="current-password" placeholder="Your password">
                        <button type="button" class="pt-input__eye" x-on:click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                            <i class="ph" :class="show ? 'ph-eye-slash' : 'ph-eye'"></i>
                        </button>
                    </div>
                    @error('password') <p class="pt-error"><i class="ph ph-warning-circle"></i>{{ $message }}</p> @enderror
                </div>

                <label class="pt-check">
                    <input type="checkbox" wire:model="remember">
                    <span>Keep me signed in on this device</span>
                </label>

                <button type="submit" class="btn btn--solid btn--block" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login">Sign in</span>
                    <span wire:loading wire:target="login">Signing in…</span>
                    <i class="ph ph-arrow-right" wire:loading.remove wire:target="login"></i>
                </button>
            </form>

            <p class="pt-auth__help">
                New here, or forgotten your password? We set accounts up at the counter.
                <a class="link" href="{{ \App\Models\Storefront\StorefrontSetting::whatsappUrl("Namaste. I'd like help signing in to my Radharani account.") }}" target="_blank" rel="noopener">Ask us on WhatsApp <i class="ph ph-whatsapp-logo"></i></a>
            </p>
        </div>

        <aside class="pt-auth__aside" aria-hidden="true">
            <figure class="media media--arch pt-auth__art">
                <img src="https://images.unsplash.com/photo-1774437778651-b711670f3192?auto=format&fit=crop&w=900&q=78" alt="">
            </figure>
            <ul class="pt-perks">
                <li><i class="ph ph-receipt"></i><div><b>Every purchase</b><span>What you bought, and what it weighed</span></div></li>
                <li><i class="ph ph-gift"></i><div><b>Referrals</b><span>Your code and who bought with it</span></div></li>
                <li><i class="ph ph-calendar-check"></i><div><b>Monthly scheme</b><span>Each instalment you've paid</span></div></li>
            </ul>
        </aside>
    </div>
</section>
</div>
