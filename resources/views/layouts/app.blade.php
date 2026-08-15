<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07153f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
@php
    $isGirvi = ($activeModule ?? 'goldscore') === 'girvi';
    $moduleName = $isGirvi ? 'Girvi' : 'GoldScore';
    $moduleIcon = $isGirvi ? 'safe' : 'gem';
    $moduleHome = $isGirvi ? route('girvi.dashboard') : route('dashboard');
@endphp

{{-- Mobile top bar --}}
<header class="gs-topbar d-lg-none">
    <button class="btn btn-link text-white p-0 border-0"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#gsMobileNav"
            aria-controls="gsMobileNav"
            aria-label="Open menu">
        <i class="bi bi-list fs-3"></i>
    </button>

    <a href="{{ $moduleHome }}" class="text-white text-decoration-none fw-semibold">
        <i class="bi bi-{{ $moduleIcon }} gs-brand-mark me-1"></i>{{ $moduleName }}
    </a>

    <span class="small text-white-50 text-truncate gs-topbar-store">
        {{ auth()->user()->store->name }}
    </span>
</header>

{{-- Mobile offcanvas menu --}}
<div class="offcanvas offcanvas-start gs-offcanvas d-lg-none" tabindex="-1" id="gsMobileNav"
     aria-labelledby="gsMobileNavLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h2 class="offcanvas-title h5 text-white mb-0" id="gsMobileNavLabel">
            <i class="bi bi-{{ $moduleIcon }} gs-brand-mark me-2"></i>{{ $moduleName }}
        </h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3">
        @include('partials.nav-links', ['dismissOffcanvas' => true])

        <hr class="text-secondary">

        <div class="text-white-50 small px-2 mt-auto">
            <div class="fw-semibold text-white">{{ auth()->user()->name }}</div>
            <div>{{ auth()->user()->role->label() }}</div>
            <div class="mt-1">{{ auth()->user()->store->name }}</div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="px-2 mt-3">
            @csrf
            <button class="btn btn-sm btn-outline-light w-100" type="submit">
                <i class="bi bi-box-arrow-right me-1"></i>Sign out
            </button>
        </form>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        {{-- Desktop sidebar --}}
        <nav class="col-lg-2 gs-sidebar d-none d-lg-flex flex-column p-3">
            <a href="{{ $moduleHome }}" class="d-flex align-items-center text-white text-decoration-none mb-3">
                <i class="bi bi-{{ $moduleIcon }} fs-4 me-2 gs-brand-mark"></i>
                <span class="fs-5 fw-semibold">{{ $moduleName }}</span>
            </a>

            @include('partials.nav-links')

            <hr class="text-secondary">

            <div class="text-white-50 small px-2 mt-auto">
                <div class="fw-semibold text-white">{{ auth()->user()->name }}</div>
                <div>{{ auth()->user()->role->label() }}</div>
                <div class="mt-1">{{ auth()->user()->store->name }}</div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="px-2 mt-3">
                @csrf
                <button class="btn btn-sm btn-outline-light w-100" type="submit">
                    <i class="bi bi-box-arrow-right me-1"></i>Sign out
                </button>
            </form>
        </nav>

        <main class="col-12 col-lg-10 gs-main">
            <div class="gs-page-header d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-3 mb-md-4">
                <div class="min-w-0">
                    <h1 class="h4 h3-md mb-0 text-break">@yield('heading', 'Dashboard')</h1>
                    @hasSection('subheading')
                        <p class="text-muted mb-0 small">@yield('subheading')</p>
                    @endif
                </div>
                <div class="gs-page-actions d-flex flex-wrap gap-2">@yield('actions')</div>
            </div>

            @include('partials.alerts')

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
