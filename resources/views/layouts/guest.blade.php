<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') &middot; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex align-items-center py-5">
<main class="container" style="max-width: 30rem;">
    <div class="text-center mb-4">
        <i class="bi bi-gem fs-1 text-primary"></i>
        <h1 class="h4 mt-2 mb-0">GoldScore</h1>
        <p class="text-muted mb-0">@yield('subtitle', 'Credit intelligence for retail jewellers')</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            @include('partials.alerts')
            @yield('content')
        </div>
    </div>
</main>
</body>
</html>
