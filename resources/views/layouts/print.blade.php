<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Receipt') &middot; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss'])
    <style>
        .gs-slip {
            max-width: 420px;
            border: 2px solid #111;
            padding: 0.85rem 0.95rem 1.1rem;
            font-size: 0.92rem;
            line-height: 1.45;
            color: #111;
        }

        .gs-slip-header {
            text-align: center;
            margin-bottom: 0.75rem;
        }

        .gs-slip-shop {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .gs-slip-meta {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.35rem;
            font-weight: 600;
        }

        .gs-slip-fields {
            padding-left: 1.25rem;
            margin: 0 0 1.5rem;
        }

        .gs-slip-fields > li {
            margin-bottom: 0.28rem;
        }

        .gs-slip-items {
            width: 100%;
            margin-top: 0.35rem;
            border-collapse: collapse;
        }

        .gs-slip-items th,
        .gs-slip-items td {
            border: 1px solid #111;
            padding: 0.2rem 0.35rem;
            font-size: 0.85rem;
        }

        .gs-slip-totals {
            margin-top: 0.45rem;
        }

        .gs-slip-signs {
            display: flex;
            justify-content: space-between;
            margin-top: 2.5rem;
            font-weight: 600;
        }

        @media print {
            .gs-no-print { display: none !important; }
            body { background: #fff; }
            .gs-slip { border-width: 1.5px; }
        }
    </style>
</head>
<body class="bg-white">
<div class="container py-4" style="max-width: 820px;">
    <div class="gs-no-print d-flex justify-content-between align-items-center mb-3">
        <a href="{{ $backUrl ?? url()->previous() }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Receipt
        </button>
    </div>

    @include('partials.alerts')

    @yield('content')
</div>
</body>
</html>
