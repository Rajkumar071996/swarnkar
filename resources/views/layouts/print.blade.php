<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07153f">
    <meta name="format-detection" content="telephone=no">
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
            padding: 0.55rem 0.65rem 0.55rem;
            font-size: 0.78rem;
            line-height: 1.4;
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
            font-size: 0.72rem;
            margin-bottom: 0.45rem;
        }

        .gs-slip-header {
            text-align: center;
            margin-bottom: 0.7rem;
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
            margin-top: 0.35rem;
            font-weight: 600;
        }

        .gs-slip-fields {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-left: 1.15rem;
            padding-top: 0.45rem;
            margin: 0;
            border-top: 1.5px solid #111;
        }

        .gs-slip-fields > li {
            margin: 0;
            padding: 0.18rem 0;
        }

        .gs-slip-items {
            width: 100%;
            margin-top: 0.35rem;
            border-collapse: collapse;
        }

        .gs-slip-items th,
        .gs-slip-items td {
            border: 1px solid #111;
            padding: 0.18rem 0.28rem;
            font-size: 0.72rem;
        }

        .gs-slip-totals {
            margin-top: 0.4rem;
        }

        .gs-slip-signs {
            display: flex;
            justify-content: space-between;
            margin-top: 0.85rem;
            padding-top: 0.35rem;
            font-weight: 600;
        }

        .gs-slip-sign-img {
            display: block;
            height: 28px;
            width: auto;
            max-width: 7.5rem;
            object-fit: contain;
            margin-bottom: 0.15rem;
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
                flex-direction: row;
            }
            .gs-slip {
                width: 50%;
                flex: 1 1 50%;
            }
        }

        @media screen and (max-width: 767.98px) {
            .gs-print-wrap {
                max-width: 100% !important;
                padding: 1rem !important;
                padding-bottom: max(1rem, env(safe-area-inset-bottom)) !important;
            }
            .gs-print-sheet {
                flex-direction: column;
                width: 100%;
                min-height: 0;
            }
            .gs-slip {
                width: 100%;
                flex: none;
            }
            .gs-slip + .gs-slip {
                border-left-style: solid;
                border-top: 1.5px dashed #111;
                margin-left: 0;
            }
            .gs-no-print {
                flex-direction: column;
                align-items: stretch !important;
            }
            .gs-no-print .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-white">
<div class="container py-4 gs-print-wrap" style="max-width: 230mm;">
    <div class="gs-no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a href="{{ $backUrl ?? url()->previous() }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <div class="small text-muted d-none d-sm-block">A5 landscape · two copies</div>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Receipt
        </button>
    </div>

    @include('partials.alerts')

    @yield('content')
</div>
</body>
</html>
