@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Good ' . (now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')) . ', ' . Str::before(auth()->user()->name, ' '))
@section('subheading', auth()->user()->store->name . ' · ' . now()->format('l, d F Y'))

@section('actions')
    <a href="{{ route('lookup.index') }}" class="btn btn-primary">
        <i class="bi bi-shield-check me-1"></i>Check a GoldScore
    </a>
@endsection

@section('content')
    @include('partials.books-cards')

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Khata outstanding', 'value' => money($stats['outstanding']), 'icon' => 'journal-bookmark', 'tone' => '', 'hint' => null],
            ['label' => 'Past due date', 'value' => money($stats['overdue']), 'icon' => 'exclamation-triangle', 'tone' => 'text-danger', 'hint' => null],
            ['label' => 'Due this week', 'value' => money($stats['due_this_week']), 'icon' => 'calendar-check', 'tone' => '', 'hint' => null],
            ['label' => 'Advances held', 'value' => money($stats['advance_held']), 'icon' => 'wallet2', 'tone' => $stats['advance_held'] > 0 ? 'text-success' : '', 'hint' => $stats['advance_customers'].' '.Str::plural('customer', $stats['advance_customers'])],
            ['label' => 'Open khatas', 'value' => $stats['open_khatas'], 'icon' => 'person-lines-fill', 'tone' => '', 'hint' => null],
            ['label' => 'Customers', 'value' => $stats['customers'], 'icon' => 'people', 'tone' => '', 'hint' => null],
        ] as $card)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card gs-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-1">
                            <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                            <i class="bi bi-{{ $card['icon'] }} text-muted flex-shrink-0"></i>
                        </div>
                        <div class="h4 gs-stat-value mb-0 mt-2 {{ $card['tone'] }}">{{ $card['value'] }}</div>
                        @if ($card['hint'])
                            <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card gs-stat-card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Falling due this week</span>
                    <a href="{{ route('khata.index') }}" class="small">Open khata</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Customer</th><th class="d-none d-md-table-cell">Item</th><th>Due</th><th class="text-end">Outstanding</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse ($dueSoon as $udhaar)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('khata.show', $udhaar->customer) }}">
                                        {{ $udhaar->customer->full_name }}
                                    </a>
                                </td>
                                <td class="small d-none d-md-table-cell">{{ Str::limit($udhaar->item_description, 24) }}</td>
                                <td>{{ $udhaar->due_on->format('d M') }}</td>
                                <td class="text-end">{{ money($udhaar->outstandingAmount()) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('udhaars.show', $udhaar) }}"
                                       class="btn btn-sm btn-outline-primary">Collect</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nothing due in the next seven days.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card gs-stat-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Overdue store credit</span>
                    <a href="{{ route('udhaars.index', ['filter' => 'overdue']) }}" class="small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Customer</th><th class="d-none d-md-table-cell">Item</th><th>Late by</th><th class="text-end">Outstanding</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($overdueUdhaars as $udhaar)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('khata.show', $udhaar->customer) }}">{{ $udhaar->customer->full_name }}</a>
                                </td>
                                <td class="small d-none d-md-table-cell">{{ Str::limit($udhaar->item_description, 28) }}</td>
                                <td class="text-danger">{{ $udhaar->daysOverdue() }} days</td>
                                <td class="text-end">{{ money($udhaar->outstandingAmount()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Nothing overdue. </td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card gs-stat-card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Advances held</span>
                    <a href="{{ route('khata.receive') }}" class="small">Received entry</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Customer</th><th class="text-end">Advance</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse ($advanceHeld as $row)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('khata.show', $row->customer) }}">
                                        {{ $row->customer->full_name }}
                                    </a>
                                </td>
                                <td class="text-end text-success">{{ money($row->balance) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('udhaars.create', ['customer' => $row->customer_id]) }}"
                                       class="btn btn-sm btn-outline-primary">Give credit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    No advances held. Money received with nothing outstanding appears here.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Risk mix of your customer book</div>
                <div class="card-body">
                    @php $totalCustomers = max(1, array_sum($riskMix)); @endphp

                    @foreach (App\Enums\RiskBand::cases() as $band)
                        @php $count = $riskMix[$band->value]; @endphp
                        <div class="d-flex justify-content-between small mb-1">
                            <span>
                                <span class="badge {{ $band->badgeClass() }} me-1">&nbsp;</span>
                                {{ $band->label() }}
                            </span>
                            <span class="text-muted">{{ $count }}</span>
                        </div>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar {{ str_replace('bg-', 'bg-', $band->badgeClass()) }}"
                                 style="width: {{ ($count / $totalCustomers) * 100 }}%"></div>
                        </div>
                    @endforeach

                    <p class="text-muted small mb-0">
                        Scores refresh automatically whenever you extend credit, record a payment
                        or write off an account.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
