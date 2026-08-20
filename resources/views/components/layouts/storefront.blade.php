@props(['title' => null, 'liveCart' => true, 'overlayHeader' => false])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Marks that scripts run, so the reveal's hidden state never applies
         without one. Inline and in the head to avoid a flash of content that
         then hides itself. --}}
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $title ? $title.' — Pulora' : 'Pulora' }}</title>
    {{-- The mark sits on the shop's own cream rather than on transparency:
         it is dark brown, and a browser is given one icon for both a light
         and a dark tab strip. ?v= because public/favicon.ico shipped empty
         for months, and an empty icon is exactly what a browser caches as
         "this site has none" — bump it if the mark ever changes. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#f0e9dd">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ground font-sans text-ink antialiased">
    <x-site-header :live-cart="$liveCart" :overlay="$overlayHeader" />

    {{-- Sibling of <header>, not a child of it — see the component's own
         comment for why. --}}
    <x-nav-drawer />

    <main>
        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>
