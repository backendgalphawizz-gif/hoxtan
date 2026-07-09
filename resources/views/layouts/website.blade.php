<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? ($appName.' — Gold, Silver & Jewellery') }}">
    <title>{{ isset($pageTitle) ? $pageTitle.' — '.$appName : $appName.' — Gold · Silver · Jewellery' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    @stack('head')
</head>
<body>

    <div class="ambient" aria-hidden="true">
        <div class="orb orb--gold"></div>
        <div class="orb orb--silver"></div>
        <div class="orb orb--accent"></div>
    </div>
    <div class="particles" aria-hidden="true"></div>

    @include('landing.partials.nav', ['isHome' => $isHome ?? false])

    @yield('content')

    @include('landing.partials.footer')

    <script src="{{ asset('js/landing.js') }}"></script>
    @stack('scripts')
</body>
</html>
