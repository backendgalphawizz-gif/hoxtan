<nav class="nav{{ ($isHome ?? false) ? '' : ' scrolled' }}">
    <div class="container nav__inner">
        <a href="{{ route('home') }}" class="brand">
            <div class="brand__icon">H</div>
            <div>
                <div class="brand__name">{{ $appName }}</div>
                <div class="brand__tag">Gold · Silver · Jewellery</div>
            </div>
        </a>
        <ul class="nav__links">
            @if ($isHome ?? false)
                <li><a href="#features">Features</a></li>
                <li><a href="#rates">Live Rates</a></li>
                <li><a href="#how">How It Works</a></li>
                <li><a href="#contact">Contact</a></li>
            @else
                <li><a href="{{ route('home') }}">Home</a></li>
                @foreach ($websitePages as $navPage)
                    <li><a href="{{ $navPage['url'] }}">{{ $navPage['label'] }}</a></li>
                @endforeach
            @endif
        </ul>
        <div class="nav__actions">
            <a href="{{ ($isHome ?? false) ? '#download' : route('home').'#download' }}" class="btn btn--gold" style="padding: 0.6rem 1.4rem; font-size: 0.85rem;">Get the App</a>
            <img src="{{ asset('images/bis-logo.png') }}" alt="BIS Hallmark" class="nav__bis" width="48" height="48">
        </div>
        <button class="nav__toggle" aria-label="Menu">☰</button>
    </div>
</nav>
