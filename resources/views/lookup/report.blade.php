@extends('layouts.app')

@section('title', 'GoldScore report')
@section('heading', 'GoldScore for jewellers')
@section('subheading', 'Consent-authorised credit report across the merchant network.')

@section('content')
    <div class="alert alert-success d-flex align-items-center">
        <i class="bi bi-shield-check me-2"></i>
        <div>
            Consent granted by the customer. This report closes
            {{ $grant->grant_expires_at->diffForHumans() }}.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card gs-stat-card h-100">
                <div class="card-body text-center p-4">
                    <div class="text-uppercase text-muted small mb-1">Current customer profile</div>
                    <div class="h4 mb-1">{{ $customer->full_name }}</div>
                    <div class="text-muted font-monospace">{{ $customer->maskedMobile() }}</div>
                    @if ($customer->fullAddress())
                        <div class="text-muted small mt-1 mb-4">{{ $customer->fullAddress() }}</div>
                    @else
                        <div class="mb-4"></div>
                    @endif

                    <div class="d-flex justify-content-center mb-3">
                        <div class="gs-score-dial {{ $snapshot->band->cssClass() }}"
                             style="--gs-sweep: {{ $snapshot->dialFraction() * 360 }}deg">
                            <div class="gs-score-dial-inner">
                                <div>
                                    <div class="gs-score-value">{{ $snapshot->score ?? '--' }}</div>
                                    <div class="text-muted small">of {{ config('goldscore.range.max') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <span class="badge {{ $snapshot->band->badgeClass() }} fs-6 px-3 py-2">
                        {{ strtoupper($snapshot->band->label()) }}
                    </span>

                    <p class="text-muted mt-3 mb-0">{{ $snapshot->band->recommendation() }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card gs-stat-card mb-3">
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Headroom to lend</div>
                            <div class="h5 mb-0">{{ money($snapshot->recommended_credit_limit) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Owed to you</div>
                            <div class="h5 mb-0">{{ money($exposure->ownStore) }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Owed elsewhere</div>
                            <div class="h5 mb-0 {{ $exposure->hasHiddenExposure() ? 'text-danger' : '' }}">
                                {{ money($exposure->elsewhere) }}
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-muted small text-uppercase">Verified defaults</div>
                            <div class="h5 mb-0 {{ $activity['flags']->count() ? 'text-danger' : '' }}">
                                {{ $activity['flags']->count() }}
                            </div>
                        </div>
                    </div>

                    @if ($exposure->hasHiddenExposure())
                        <div class="alert alert-danger mt-3 mb-0 d-flex align-items-start">
                            <i class="bi bi-exclamation-octagon-fill me-2 mt-1"></i>
                            <div>
                                <strong>
                                    Already owes {{ money($exposure->elsewhere) }} to
                                    {{ $exposure->elsewhereStoreCount }}
                                    other {{ Str::plural('jeweller', $exposure->elsewhereStoreCount) }}.
                                </strong>
                                <div class="small">
                                    @if ($exposure->hasOverdue())
                                        {{ money($exposure->overdue) }} of the total is already past its due
                                        date, the oldest by {{ $exposure->oldestOverdueDays }} days.
                                    @else
                                        None of it is past due yet, but it is exposure they are already carrying.
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($snapshot->band === App\Enums\RiskBand::Yellow && $snapshot->recommended_credit_limit > 0)
                        <div class="alert alert-warning mt-3 mb-0 small">
                            Cap store credit at
                            {{ (int) (config('goldscore.credit_limit.yellow_order_value_share') * 100) }}%
                            of the order value and take collateral.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">How this score was built</div>
                <div class="card-body">
                    @foreach ($snapshot->breakdown['components'] as $component)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small">
                                <span class="fw-semibold">{{ $component['label'] }}</span>
                                @if ($component['ratio'] === null)
                                    <span class="text-muted">No history &middot; weight redistributed</span>
                                @else
                                    <span>
                                        {{ round($component['ratio'] * 100) }}%
                                        <span class="text-muted">
                                            &middot; counts for {{ $component['effective_weight'] }}%
                                        </span>
                                    </span>
                                @endif
                            </div>
                            <div class="progress mt-1" style="height: 8px;">
                                <div class="progress-bar {{ $component['ratio'] === null ? 'bg-secondary opacity-25' : '' }}"
                                     style="width: {{ $component['ratio'] === null ? 100 : $component['ratio'] * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach

                    <p class="text-muted small mb-0">
                        Components with no history are excluded and the remaining weights rescaled, so a
                        customer is judged on the record they actually have.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card gs-stat-card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Where the money is owed right now</span>
                    <span class="fw-semibold">{{ money($exposure->total) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr><th>Jeweller</th><th class="text-end">Outstanding</th><th class="text-end">Of which overdue</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($exposure->stores as $row)
                            <tr class="{{ $row['own_store'] ? 'table-light' : '' }}">
                                <td>
                                    {{ $row['label'] }}
                                    @if ($row['own_store'])
                                        <span class="badge bg-secondary ms-1">You</span>
                                    @endif
                                    @if (! empty($row['address']))
                                        <div class="small text-muted mt-1">{{ $row['address'] }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ money($row['outstanding']) }}</td>
                                <td class="text-end {{ $row['overdue'] > 0 ? 'text-danger' : 'text-muted' }}">
                                    {{ $row['overdue'] > 0 ? money($row['overdue']) : '--' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    Owes nothing anywhere on the network.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card gs-stat-card h-100">
                <div class="card-header bg-white fw-semibold">Store credit across the network</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                        <tr><th>Issued</th><th>Where</th><th class="text-end">Outstanding</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($activity['udhaars'] as $row)
                            <tr>
                                <td>{{ $row['issued_on']->format('M Y') }}</td>
                                <td class="small text-muted">{{ $row['source'] }}</td>
                                <td class="text-end">{{ money($row['outstanding']) }}</td>
                                <td><span class="badge {{ $row['status']->badgeClass() }}">{{ $row['status']->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No store credit on record.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($activity['flags']->isNotEmpty())
        <div class="card gs-stat-card mt-3 border-danger">
            <div class="card-header bg-white fw-semibold text-danger">
                <i class="bi bi-exclamation-triangle me-1"></i>Verified default reports
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                    <tr><th>When</th><th>Reason</th><th>Reported by</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($activity['flags'] as $flag)
                        <tr>
                            <td>{{ $flag['occurred_on']->format('d M Y') }}</td>
                            <td>{{ $flag['reason']->label() }}</td>
                            <td class="small text-muted">{{ $flag['source'] }}</td>
                            <td class="text-end">{{ money($flag['amount']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card gs-stat-card mt-3">
        <div class="card-body">
            <div class="text-muted small mb-2">Quick actions</div>
            <div class="gs-page-actions d-flex flex-wrap gap-2">
                @can('create', App\Models\Udhaar::class)
                    <a href="{{ route('udhaars.create', ['customer' => $customer->id]) }}" class="btn btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i>Give credit
                    </a>
                @endcan
                <a href="{{ route('khata.show', $customer) }}" class="btn btn-outline-primary">
                    <i class="bi bi-journal-bookmark me-1"></i>Open khata
                </a>
                <a href="{{ route('khata.receive.customer', $customer) }}" class="btn btn-outline-success">
                    <i class="bi bi-cash-coin me-1"></i>Received entry
                </a>
                @can('create', App\Models\DefaultFlag::class)
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reportDefaultModal">
                        <i class="bi bi-flag me-1"></i>Report default
                    </button>
                @endcan
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary">Open profile</a>
            </div>
        </div>
    </div>

    @can('create', App\Models\DefaultFlag::class)
        @include('lookup._report_default_modal', ['customer' => $customer])
    @endcan

    <p class="text-muted small mt-3">
        Computed {{ $snapshot->computed_at->diffForHumans() }} from {{ $snapshot->observation_count }}
        observation(s). Merchant names outside your own store are withheld.
    </p>
@endsection
