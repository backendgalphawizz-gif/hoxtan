<footer class="footer" id="contact">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__about">
                <a href="{{ route('home') }}" class="brand">
                    <div class="brand__icon">H</div>
                    <div>
                        <div class="brand__name">{{ $appName }}</div>
                        <div class="brand__tag">Gold · Silver · Jewellery</div>
                    </div>
                </a>
                <p>India's premium digital bullion platform. Buy, sell, invest, and shop with complete transparency and elite-grade security.</p>
                <div class="social">
                    @foreach ($socialLinks as $social)
                        <a href="{{ $social['url'] }}" class="social__link" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['name'] }}">
                            @include('landing.partials.social-icon', ['icon' => $social['icon']])
                        </a>
                    @endforeach
                </div>
            </div>
            <div>
                <h4 class="footer__heading">Platform</h4>
                <ul class="footer__links">
                    <li><a href="{{ route('home') }}#features">Digital Gold</a></li>
                    <li><a href="{{ route('home') }}#features">SIG Invest</a></li>
                    <li><a href="{{ route('home') }}#features">Jewellery</a></li>
                    <li><a href="{{ route('home') }}#rates">Live Rates</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer__heading">Company</h4>
                <ul class="footer__links">
                    @forelse ($websitePages as $footerPage)
                        <li><a href="{{ $footerPage['url'] }}">{{ $footerPage['label'] }}</a></li>
                    @empty
                        <li><span style="color:var(--text-muted); font-size:0.9rem;">Pages coming soon</span></li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h4 class="footer__heading">Support</h4>
                <ul class="footer__links">
                    @if ($supportEmail)
                        <li><a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
                    @endif
                    @if ($supportPhone)
                        <li><a href="tel:{{ $supportPhone }}">+91 {{ $supportPhone }}</a></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="footer__bottom">
            <span>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</span>
            <span>Allocated · Segregated · Insured</span>
        </div>
    </div>
</footer>
