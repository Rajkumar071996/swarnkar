<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Receipt') &middot; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss'])
    <style>
        .gs-print-sheet {
            display: flex;
            width: 210mm;
            min-height: 148mm;
            margin: 0 auto;
            background: #fff;
        }

        .gs-slip {
            flex: 1 1 50%;
            width: 50%;
            border: 1.5px solid #111;
            padding: 0.45rem 0.55rem 0.6rem;
            font-size: 0.72rem;
            line-height: 1.28;
            color: #111;
            display: flex;
            flex-direction: column;
        }

        .gs-slip + .gs-slip {
            border-left-style: dashed;
            margin-left: -1.5px;
        }

        .gs-slip-copy {
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.68rem;
            margin-bottom: 0.2rem;
        }

        .gs-slip-header {
            text-align: center;
            margin-bottom: 0.4rem;
        }

        .gs-slip-shop {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .gs-slip-meta {
            display: flex;
            justify-content: space-between;
            gap: 0.35rem;
            margin-top: 0.2rem;
            font-weight: 600;
        }

        .gs-slip-fields {
            padding-left: 1.05rem;
            margin: 0 0 0.4rem;
        }

        .gs-slip-fields > li {
            margin-bottom: 0.12rem;
        }

        .gs-slip-items {
            width: 100%;
            margin-top: 0.2rem;
            border-collapse: collapse;
        }

        .gs-slip-items th,
        .gs-slip-items td {
            border: 1px solid #111;
            padding: 0.12rem 0.22rem;
            font-size: 0.68rem;
        }

        .gs-slip-totals {
            margin-top: 0.25rem;
        }

        .gs-slip-signs {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 1.1rem;
            font-weight: 600;
        }

        @page {
            size: A5 landscape;
            margin: 6mm;
        }

        @media print {
            .gs-no-print { display: none !important; }
            html, body {
                background: #fff;
                margin: 0;
                padding: 0;
            }
            .gs-print-wrap {
                max-width: none !important;
                padding: 0 !important;
            }
            .gs-print-sheet {
                width: 100%;
                min-height: auto;
                height: 136mm;
            }
        }
    </style>
</head>
<body class="bg-white">
<div class="container py-4 gs-print-wrap" style="max-width: 230mm;">
    <div class="gs-no-print d-flex justify-content-between align-items-center mb-3">
        <a href="{{ $backUrl ?? url()->previous() }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <div class="small text-muted">A5 landscape · two copies</div>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Receipt
        </button>
    </div>

    @include('partials.alerts')

    @yield('content')
</div>
</body>
</html>
