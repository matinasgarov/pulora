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
