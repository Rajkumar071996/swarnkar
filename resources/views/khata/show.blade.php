@extends('layouts.app')

@section('title', $customer->full_name . ' · khata')
@section('heading', $customer->full_name)
@section('subheading', 'Udhar khata at ' . auth()->user()->store->name)

@section('actions')
    <a href="{{ route('lookup.report', $customer) }}" class="btn btn-outline-primary">
        <i class="bi bi-shield-check me-1"></i>Check GoldScore
    </a>
    <a href="{{ route('khata.receive.customer', $customer) }}" class="btn btn-success">
        ₹ You Got
    </a>
    @can('create', App\Models\Udhaar::class)
        <a href="{{ route('udhaars.create', ['customer' => $customer->id]) }}" class="btn btn-danger">
            ₹ You Gave
        </a>
    @endcan
@endsection

@section('content')
    @php $band = $customer->latestScore?->band ?? App\Enums\RiskBand::Unscored; @endphp

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Outstanding</div>
                    <div class="h3 mb-0 {{ $summary['outstanding'] > 0 ? 'text-danger' : 'text-success' }}">
                        {{ money($summary['outstanding']) }}
                    </div>
                    <div class="small text-muted">{{ $summary['open_entries'] }} open of {{ $summary['entries'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Advance with you</div>
                    <div class="h3 mb-0 {{ $advance > 0 ? 'text-success' : '' }}">{{ money($advance) }}</div>
                    <div class="small text-muted">applied when you give credit</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Paid back</div>
                    <div class="h3 mb-0 text-success">{{ money($summary['paid']) }}</div>
                    @if ($summary['written_off'] > 0)
                        <div class="small text-danger">{{ money($summary['written_off']) }} written off</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">GoldScore</div>
                    <div class="h3 mb-0">
                        <span class="badge {{ $band->badgeClass() }}">
                            {{ $customer->latestScore?->score ?? 'Unscored' }}
                        </span>
                    </div>
                    @if ($summary['oldest_overdue_days'] > 0)
                        <div class="small text-danger">{{ $summary['oldest_overdue_days'] }} days past due</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($exposure)
        <div class="card gs-stat-card mb-3 {{ $exposure->hasHiddenExposure() ? 'border-warning' : '' }}">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Position across the network</span>
                <span class="small text-muted">Shown because consent is currently active</span>
            </div>
            <div class="card-body">
                @if ($exposure->hasHiddenExposure())
                    <div class="alert alert-warning d-flex align-items-start mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                        <div>
                            <strong>{{ money($exposure->elsewhere) }} is owed to
                                {{ $exposure->elsewhereStoreCount }}
                                other {{ Str::plural('jeweller', $exposure->elsewhereStoreCount) }}.</strong>
                            <div class="small">
                                Take this into account before adding to what they already carry here.
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="text-muted text-uppercase small">At your store</div>
                        <div class="h5 mb-0">{{ money($exposure->ownStore) }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted text-uppercase small">Elsewhere</div>
                        <div class="h5 mb-0 {{ $exposure->hasHiddenExposure() ? 'text-warning' : '' }}">
                            {{ money($exposure->elsewhere) }}
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted text-uppercase small">Total owed</div>
                        <div class="h5 mb-0">{{ money($exposure->total) }}</div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-light border d-flex justify-content-between align-items-center">
            <div class="small text-muted mb-0">
                This page shows your own book only. Run a consented credit check to see whether this
                customer owes anything at other jewellers.
            </div>
            <a href="{{ route('lookup.report', $customer) }}" class="btn btn-sm btn-outline-primary ms-3 text-nowrap">
                Check network
            </a>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Credit entries</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th>Issued</th>
                            <th>Due</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Outstanding</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ Str::limit($entry->item_description, 26) }}</td>
                                <td class="small">{{ $entry->issued_on->format('d M y') }}</td>
                                <td class="small">
                                    {{ $entry->due_on->format('d M y') }}
                                    @if ($entry->status->isOutstanding() && $entry->daysOverdue() > 0)
                                        <div class="text-danger">{{ $entry->daysOverdue() }}d late</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ money($entry->principal_amount) }}</td>
                                <td class="text-end fw-semibold">{{ money($entry->outstandingAmount()) }}</td>
                                <td>
                                    <span class="badge {{ $entry->status->badgeClass() }}">
                                        {{ $entry->status->label() }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('udhaars.show', $entry) }}"
                                       class="btn btn-sm btn-outline-secondary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No credit has been given to this customer yet.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Payment history</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Date</th><th>Against</th><th>Method</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($history as $row)
                            <tr>
                                <td class="small">{{ $row['paid_on']->format('d M y') }}</td>
                                <td class="small text-muted">{{ $row['against'] }}</td>
                                <td class="small">{{ Str::headline($row['method']) }}</td>
                                <td class="text-end text-success">{{ money($row['amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Nothing paid back yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
