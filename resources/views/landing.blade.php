@extends('layouts.website', ['isHome' => true])

@section('content')
    {{-- Hero --}}
    <section class="hero">
        <div class="container hero__grid">
            <div class="hero__content">
                <div class="hero__badge">
                    <span class="hero__badge-dot"></span>
                    Live market rates · 24/7 trading
                </div>
                <h1 class="hero__title">
                    Own <span>Pure Gold</span><br>&amp; Silver Digitally
                </h1>
                <p class="hero__subtitle">
                    {{ $appName }} is your premium bullion platform — buy, sell, and grow wealth with live gold &amp; silver rates, SIG auto-invest, and handcrafted jewellery.
                </p>
                <div class="hero__actions">
                    <a href="#download" class="btn btn--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                        Download App
                    </a>
                    <a href="#features" class="btn btn--outline">Explore Features</a>
                </div>
                <div class="hero__stats">
                    <div>
                        <div class="stat__value">99.9%</div>
                        <div class="stat__label">Purity</div>
                    </div>
                    <div>
                        <div class="stat__value">256-bit</div>
                        <div class="stat__label">Encryption</div>
                    </div>
                    <div>
                        <div class="stat__value">24/7</div>
                        <div class="stat__label">Support</div>
                    </div>
                </div>
            </div>

            <div class="hero__visual" id="rates">
                <div class="hero__ring hero__ring--2" aria-hidden="true"></div>
                <div class="hero__ring" aria-hidden="true"></div>
                <div class="hero__card">
                    <div class="hero__card-shine" aria-hidden="true"></div>
                    <p style="font-size:0.75rem; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold); margin-bottom:1.25rem;">Today's Live Rates</p>

                    <div class="rate-card">
                        <div class="rate-card__metal">
                            <div class="rate-card__icon rate-card__icon--gold">🥇</div>
                            <div>
                                <div class="rate-card__name">Gold 24K</div>
                                <div class="rate-card__purity">99.9% Pure · per gram</div>
                            </div>
                        </div>
                        <div class="rate-card__price" data-count="{{ $goldRate }}" data-prefix="₹">₹0.00</div>
                    </div>

                    <div class="rate-card rate-card--silver">
                        <div class="rate-card__metal">
                            <div class="rate-card__icon rate-card__icon--silver">🥈</div>
                            <div>
                                <div class="rate-card__name">Silver</div>
                                <div class="rate-card__purity">999 Fine · per gram</div>
                            </div>
                        </div>
                        <div class="rate-card__price" data-count="{{ $silverRate }}" data-prefix="₹">₹0.00</div>
                    </div>

                    <p style="font-size:0.75rem; color:var(--text-muted); text-align:center; margin-top:0.5rem;">
                        Rates update in real-time from live market feed
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Ticker --}}
    <div class="ticker" aria-hidden="true">
        <div class="ticker__track">
            @for ($i = 0; $i < 2; $i++)
                <span class="ticker__item">🥇 Gold <strong>₹{{ number_format($goldRate, 2) }}/g</strong></span>
                <span class="ticker__item">🥈 Silver <strong>₹{{ number_format($silverRate, 2) }}/g</strong></span>
                <span class="ticker__item">✦ Hallmarked Jewellery</span>
                <span class="ticker__item">✦ SIG Auto-Invest</span>
                <span class="ticker__item">✦ Secure Vault Storage</span>
                <span class="ticker__item">✦ Instant Buy &amp; Sell</span>
                <span class="ticker__item">✦ KYC Verified Platform</span>
            @endfor
        </div>
    </div>

    {{-- Features --}}
    <section class="section" id="features">
        <div class="container">
            <div class="section__header reveal">
                <p class="section__eyebrow">Why {{ $appName }}</p>
                <h2 class="section__title">Everything You Need to Build Wealth in Precious Metals</h2>
                <p class="section__desc">From digital bullion to premium jewellery — one secure platform for India's smartest investors.</p>
            </div>
            <div class="features">
                @foreach (config('app_content.landing_features', []) as $index => $feature)
                    <button type="button" class="feature reveal" data-feature-index="{{ $index }}" aria-haspopup="dialog">
                        <div class="feature__icon">{{ $feature['icon'] }}</div>
                        <h3 class="feature__title">{{ $feature['title'] }}</h3>
                        <p class="feature__text">{{ $feature['text'] }}</p>
                        <span class="feature__more">Learn more →</span>
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    <script type="application/json" id="landing-features-data">@json(config('app_content.landing_features', []))</script>

    <div class="feature-modal" id="featureModal" hidden aria-hidden="true">
        <div class="feature-modal__backdrop" data-feature-modal-close></div>
        <div class="feature-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="featureModalTitle">
            <button type="button" class="feature-modal__close" data-feature-modal-close aria-label="Close">&times;</button>
            <div class="feature-modal__icon" id="featureModalIcon"></div>
            <h3 class="feature-modal__title" id="featureModalTitle"></h3>
            <p class="feature-modal__summary" id="featureModalSummary"></p>
            <ul class="feature-modal__list" id="featureModalList"></ul>
        </div>
    </div>

    {{-- How it works --}}
    <section class="section" id="how" style="background: rgba(16,19,28,0.5);">
        <div class="container">
            <div class="section__header reveal">
                <p class="section__eyebrow">Simple Process</p>
                <h2 class="section__title">Start Investing in 4 Easy Steps</h2>
            </div>
            <div class="steps">
                <div class="step reveal">
                    <h3 class="step__title">Download App</h3>
                    <p class="step__text">Get {{ $appName }} from Play Store or App Store</p>
                </div>
                <div class="step reveal">
                    <h3 class="step__title">Complete KYC</h3>
                    <p class="step__text">Verify identity with Aadhaar, PAN &amp; face match</p>
                </div>
                <div class="step reveal">
                    <h3 class="step__title">Buy or Invest</h3>
                    <p class="step__text">Purchase gold/silver or activate SIG plan</p>
                </div>
                <div class="step reveal">
                    <h3 class="step__title">Grow Wealth</h3>
                    <p class="step__text">Track holdings, sell, or shop jewellery anytime</p>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="cta" id="download">
        <div class="container">
            <div class="cta__box reveal">
                <h2 class="cta__title">Ready to Start Your Gold Journey?</h2>
                <p class="cta__text">Join thousands of investors who trust {{ $appName }} for secure, transparent precious metal trading.</p>
                <div class="cta__actions">
                    <a href="#" class="btn btn--gold">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M3 20.5V3.5C3 2.91 3.34 2.39 3.84 2.15L13.69 12L3.84 21.85C3.34 21.6 3 21.09 3 20.5ZM16.81 15.12L6.05 21.34L14.54 12.85L16.81 15.12ZM20.16 10.81C20.5 11.08 20.75 11.5 20.75 12C20.75 12.5 20.53 12.9 20.18 13.18L17.89 14.5L15.39 12L17.89 9.5L20.16 10.81ZM6.05 2.66L16.81 8.88L14.54 11.15L6.05 2.66Z"/></svg>
                        Google Play
                    </a>
                    <a href="#" class="btn btn--outline">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                        </svg>
                        App Store
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
