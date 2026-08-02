@extends('layouts.app')

@section('title', 'Udhaar ledger')
@section('heading', 'Udhaar ledger')
@section('subheading', 'Store credit issued by your shop.')

@section('actions')
    @can('create', App\Models\Udhaar::class)
        <a href="{{ route('udhaars.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Record udhaar
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card gs-stat-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Total outstanding</div>
                    <div class="h3 mb-0">{{ money($totals['outstanding']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card gs-stat-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Past due date</div>
                    <div class="h3 mb-0 text-danger">{{ money($totals['overdue']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach (['outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'all' => 'All'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $filter === $key ? 'active' : '' }}"
                   href="{{ route('udhaars.index', ['filter' => $key]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card gs-stat-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Due</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($udhaars as $udhaar)
                    <tr>
                        <td class="fw-semibold">{{ $udhaar->customer->full_name }}</td>
                        <td>{{ $udhaar->item_description }}</td>
                        <td>
                            {{ $udhaar->due_on->format('d M Y') }}
                            @if ($udhaar->status->isOutstanding() && $udhaar->due_on->isPast())
                                <div class="small text-danger">{{ $udhaar->daysOverdue() }} days late</div>
                            @endif
                        </td>
                        <td class="text-end">{{ money($udhaar->principal_amount) }}</td>
                        <td class="text-end fw-semibold">{{ money($udhaar->outstandingAmount()) }}</td>
                        <td><span class="badge {{ $udhaar->status->badgeClass() }}">{{ $udhaar->status->label() }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('udhaars.show', $udhaar) }}" class="btn btn-sm btn-outline-secondary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Nothing here.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $udhaars->links() }}</div>
@endsection
