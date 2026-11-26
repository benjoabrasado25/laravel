<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Properties') · Stays</title>
    {{-- Fonts from the design guide. If offline, the CSS falls back to system fonts. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="{{ route('properties.index') }}" class="brand">
                <span class="brand-mark">@include('partials.icon-house', ['size' => 18])</span>
                <span class="brand-name">Stays</span>
            </a>
            <nav aria-label="Main" class="main-nav">
                {{-- Highlighted across the whole booking flow, but only the list itself is the "current page". --}}
                <a href="{{ route('properties.index') }}"
                   @class(['is-active' => request()->routeIs('properties.*', 'bookings.*')])
                   @if (request()->routeIs('properties.index')) aria-current="page" @endif>Properties</a>
            </nav>
        </div>
    </header>

    <main>
        @if (session('success'))
            <div class="container">
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <span><strong>Stays</strong> · Property booking demo</span>
            <span>Prices per night in EUR</span>
        </div>
    </footer>
</body>
</html>
