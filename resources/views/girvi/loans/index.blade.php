@extends('layouts.app')

@section('title', 'All Mortgage')
@section('heading', 'All Mortgage')
@php
    $heldParts = collect($totals['held'])
        ->filter(fn (float $grams) => $grams > 0)
        ->map(fn (float $grams, string $metal) => number_format($grams, 3) . ' g ' . $metal);
@endphp

@section('subheading', money($totals['money_out']) . ' out' . ($heldParts->isEmpty() ? '' : ' against ' . $heldParts->join(' and ')))

@section('actions')
    <a href="{{ route('girvi.loans.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Girvi
    </a>
@endsection

@section('content')
    <ul class="nav nav-pills gs-filter-pills mb-3">
        @foreach ([
            'unreleased' => 'UnReleased',
            'overdue' => 'Overdue',
            'released' => 'Released',
            'all' => 'All',
        ] as $value => $label)
            <li class="nav-item">
                <a class="nav-link {{ $filter === $value ? 'active' : '' }}"
                   href="{{ route('girvi.loans.index', ['filter' => $value]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card gs-stat-card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Receipt</th>
                    <th>Customer</th>
                    <th>Deposited</th>
                    <th>Due</th>
                    <th class="text-end">Fine Wt</th>
                    <th class="text-end">Loan</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td class="small font-monospace">
                            <a href="{{ route('girvi.loans.show', $loan) }}">{{ $loan->receipt_no }}</a>
                        </td>
                        <td class="fw-semibold">{{ $loan->customer->full_name }}</td>
                        <td class="small">{{ $loan->disbursed_on->format('d M y') }}</td>
                        <td class="small {{ ! $loan->isReleased() && $loan->daysOverdue() > 0 ? 'text-danger' : '' }}">
                            {{ $loan->due_on->format('d M y') }}
                        </td>
                        <td class="text-end small">{{ number_format((float) $loan->fine_weight_grams, 3) }}</td>
                        <td class="text-end">{{ money($loan->principal_amount) }}</td>
                        <td class="text-end">{{ money($loan->outstandingPrincipal()) }}</td>
                        <td>
                            @if ($loan->isReleased())
                                <span class="badge bg-success">Released</span>
                            @elseif ($loan->daysOverdue() > 0)
                                <span class="badge bg-danger">Overdue</span>
                            @else
                                <span class="badge {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('girvi.loans.receipt', $loan) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-printer me-1"></i>Receipt
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            Nothing here yet.
                            <a href="{{ route('girvi.loans.create') }}">Record a girvi</a>.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $loans->links() }}</div>
@endsection
