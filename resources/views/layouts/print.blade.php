<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Receipt') &middot; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss'])
    <style>
        @media print {
            .gs-no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body class="bg-white">
<div class="container py-4" style="max-width: 820px;">
    <div class="gs-no-print d-flex justify-content-between align-items-center mb-3">
        <a href="{{ $backUrl ?? url()->previous() }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>

    @yield('content')
</div>
</body>
</html>
