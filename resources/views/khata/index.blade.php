@extends('layouts.app')

@section('title', 'Udhar khata')
@section('heading', 'Udhar khata')
@section('subheading', 'Every customer credit account at your shop, with what is still owed.')

@section('actions')
    <a href="{{ route('khata.receive') }}" class="btn btn-success">
        <i class="bi bi-cash-coin me-1"></i>Received entry
    </a>
    @can('create', App\Models\Udhaar::class)
        <a href="{{ route('udhaars.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Give credit
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card gs-stat-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Total outstanding</div>
                    <div class="h3 mb-0">{{ money($totals['outstanding']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card gs-stat-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Past due date</div>
                    <div class="h3 mb-0 text-danger">{{ money($totals['overdue']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card gs-stat-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Open khatas</div>
                    <div class="h3 mb-0">{{ $totals['accounts'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills gs-filter-pills mb-3">
        @foreach ([
            'outstanding' => 'With balance',
            'overdue' => 'Overdue',
            'settled' => 'Fully settled',
            'all' => 'All accounts',
        ] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $filter === $key ? 'active' : '' }}"
                   href="{{ route('khata.index', ['filter' => $key]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card gs-stat-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th class="d-none d-md-table-cell">GoldScore</th>
                    <th class="text-center d-none d-lg-table-cell">Entries</th>
                    <th class="text-end d-none d-lg-table-cell">Credit given</th>
                    <th class="text-end d-none d-xl-table-cell">Paid</th>
                    <th class="text-end">Outstanding</th>
                    <th class="d-none d-md-table-cell">Oldest overdue</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($accounts as $account)
                    @php $band = $account->latestScore?->band ?? App\Enums\RiskBand::Unscored; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $account->full_name }}</div>
                            <div class="small text-muted font-monospace">{{ $account->maskedMobile() }}</div>
                            <div class="d-md-none mt-1">
                                <span class="badge {{ $band->badgeClass() }}">
                                    {{ $account->latestScore?->score ?? 'Unscored' }}
                                </span>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <span class="badge {{ $band->badgeClass() }}">
                                {{ $account->latestScore?->score ?? 'Unscored' }}
                            </span>
                        </td>
                        <td class="text-center d-none d-lg-table-cell">{{ $account->entry_count }}</td>
                        <td class="text-end d-none d-lg-table-cell">{{ money($account->extended_total) }}</td>
                        <td class="text-end text-success d-none d-xl-table-cell">{{ money($account->paid_total) }}</td>
                        <td class="text-end fw-semibold {{ $account->outstanding_total > 0 ? '' : 'text-muted' }}">
                            {{ money($account->outstanding_total ?? 0) }}
                        </td>
                        <td class="d-none d-md-table-cell">
                            @if ($account->oldest_overdue_on)
                                <span class="text-danger">
                                    {{ (int) \Illuminate\Support\Carbon::parse($account->oldest_overdue_on)->diffInDays(now()) }} days
                                </span>
                                <div class="small text-muted">{{ $account->overdue_count }} entries</div>
                            @else
                                <span class="text-muted">--</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('khata.show', $account) }}" class="btn btn-sm btn-outline-secondary">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No credit accounts here yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $accounts->links() }}</div>
@endsection
