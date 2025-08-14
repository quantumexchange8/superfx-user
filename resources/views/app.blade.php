<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        @if (App::environment('production') || App::environment('staging'))
            <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        @endif

        <meta name="description" content="SuperFin is a global ECN broker that offers online currency trading, CFD, stocks, commodities, futures and precious metals via trading platform" />

        <!-- Google / Search Engine Tags -->
        <meta itemprop="name" content="Forex Broker | Online Currency Trading with SuperFin (SF)" />
        <meta itemprop="description" content="SuperFin is a global ECN broker that offers online currency trading, CFD, stocks, commodities, futures and precious metals via trading platform" />
        <meta itemprop="image" content="{{ asset('img/social-card.webp') }}" />

        <!-- Facebook Meta Tags -->
        <meta property="og:url" content="https://my.superfin.global/login" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Forex Broker | Online Currency Trading with SuperFin (SF)" />
        <meta property="og:description" content="SuperFin is a global ECN broker that offers online currency trading, CFD, stocks, commodities, futures and precious metals via trading platform" />
        <meta property="og:image" content="{{ asset('img/social-card.webp') }}" />

        <!-- Twitter Meta Tags -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="Forex Broker | Online Currency Trading with SuperFin (SF)" />
        <meta name="twitter:description" content="SuperFin is a global ECN broker that offers online currency trading, CFD, stocks, commodities, futures and precious metals via trading platform" />
        <meta name="twitter:image" content="{{ asset('img/social-card.webp') }}" />

        <title inertia>{{ config('app.name', 'SuperFin User') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

        <!-- Icon -->
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('img/superfx-logo.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/superfx-logo.png') }}" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
