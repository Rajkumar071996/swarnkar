<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-lg-2 gs-sidebar p-3">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-white text-decoration-none mb-4">
                <i class="bi bi-gem fs-4 me-2 text-warning"></i>
                <span class="fs-5 fw-semibold">GoldScore</span>
            </a>

            <ul class="nav nav-pills flex-column">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link @active('dashboard')">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('lookup.index') }}" class="nav-link @active('lookup.*')">
                        <i class="bi bi-search me-2"></i>Check GoldScore
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('customers.index') }}" class="nav-link @active('customers.*')">
                        <i class="bi bi-people me-2"></i>Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('khata.index') }}" class="nav-link @active('khata.*')">
                        <i class="bi bi-journal-bookmark me-2"></i>Udhar Khata
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('udhaars.index') }}" class="nav-link @active('udhaars.*')">
                        <i class="bi bi-receipt me-2"></i>Credit Entries
                    </a>
                </li>
                @can('manageStaff', App\Models\User::class)
                    <li class="nav-item">
                        <a href="{{ route('staff.index') }}" class="nav-link @active('staff.*')">
                            <i class="bi bi-person-badge me-2"></i>Staff
                        </a>
                    </li>
                @endcan
            </ul>

            <hr class="text-secondary">

            <div class="text-white-50 small px-2">
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

        <main class="col-12 col-lg-10 py-4 px-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h1 class="h3 mb-0">@yield('heading', 'Dashboard')</h1>
                    @hasSection('subheading')
                        <p class="text-muted mb-0">@yield('subheading')</p>
                    @endif
                </div>
                <div>@yield('actions')</div>
            </div>

            @include('partials.alerts')

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
