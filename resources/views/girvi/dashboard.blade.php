@extends('layouts.app')

@section('title', 'Girvi dashboard')
@section('heading', 'Girvi')
@section('subheading', auth()->user()->store->name . ' · ' . now()->format('l, d F Y'))

@section('actions')
    <a href="{{ route('girvi.loans.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Girvi
    </a>
    <a href="{{ route('girvi.release.create') }}" class="btn btn-outline-success">
        <i class="bi bi-box-arrow-up me-1"></i>Release
    </a>
@endsection

@section('content')
    @include('partials.books-cards')

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Money out', 'value' => money($stats['money_out']), 'icon' => 'cash-stack', 'tone' => '', 'hint' => 'On unreleased pledges'],
            ['label' => 'Pledges held', 'value' => $stats['pledges'], 'icon' => 'safe', 'tone' => '', 'hint' => null],
            ['label' => 'Overdue', 'value' => $stats['overdue'], 'icon' => 'exclamation-triangle', 'tone' => $stats['overdue'] > 0 ? 'text-danger' : '', 'hint' => 'Past maturity'],
            ['label' => 'Gold held', 'value' => number_format($stats['held']['gold'], 3) . ' g', 'icon' => 'gem', 'tone' => 'text-warning', 'hint' => 'Fine weight'],
            ['label' => 'Silver held', 'value' => number_format($stats['held']['silver'], 3) . ' g', 'icon' => 'circle', 'tone' => 'text-secondary', 'hint' => 'Fine weight'],
            ['label' => 'Interest this month', 'value' => money($stats['interest_this_month']), 'icon' => 'graph-up', 'tone' => 'text-success', 'hint' => null],
            ['label' => 'Released', 'value' => $stats['released'], 'icon' => 'unlock', 'tone' => '', 'hint' => 'All time'],
        ] as $card)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card gs-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                            <i class="bi bi-{{ $card['icon'] }} text-muted"></i>
                        </div>
                        <div class="h4 mb-0 mt-2 {{ $card['tone'] }}">{{ $card['value'] }}</div>
                        @if ($card['hint'])
                            <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card gs-stat-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Maturing in the next 30 days</span>
                    <a href="{{ route('girvi.loans.index') }}" class="small">All mortgage</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Customer</th><th>Receipt</th><th>Due</th><th class="text-end">Loan</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($dueSoon as $loan)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('girvi.loans.show', $loan) }}">{{ $loan->customer->full_name }}</a>
                                </td>
                                <td class="small font-monospace">{{ $loan->receipt_no }}</td>
                                <td class="small">{{ $loan->due_on->format('d M y') }}</td>
                                <td class="text-end">{{ money($loan->outstandingPrincipal()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Nothing matures this month.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card gs-stat-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Past maturity</span>
                    <a href="{{ route('girvi.loans.index', ['filter' => 'overdue']) }}" class="small">See all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Customer</th><th>Receipt</th><th>Overdue</th><th class="text-end">Loan</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($overdue as $loan)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('girvi.loans.show', $loan) }}">{{ $loan->customer->full_name }}</a>
                                </td>
                                <td class="small font-monospace">{{ $loan->receipt_no }}</td>
                                <td class="small text-danger">{{ $loan->daysOverdue() }} days</td>
                                <td class="text-end">{{ money($loan->outstandingPrincipal()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Nothing is overdue.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
