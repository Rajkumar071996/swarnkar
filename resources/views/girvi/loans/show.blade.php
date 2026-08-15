@extends('layouts.app')

@section('title', 'Girvi ' . $loan->receipt_no)
@section('heading', $loan->customer->full_name)
@section('subheading', 'Girvi ' . $loan->receipt_no . ' · ' . $loan->status->label())

@section('actions')
    <a href="{{ route('girvi.loans.receipt', $loan) }}" class="btn btn-primary">
        <i class="bi bi-printer me-1"></i>Print Receipt
    </a>
    @if (! $loan->isReleased())
        @can('release', $loan)
            <a href="{{ route('girvi.release.create', ['loan' => $loan->id]) }}" class="btn btn-success">
                <i class="bi bi-box-arrow-up me-1"></i>Release
            </a>
        @endcan
    @else
        <a href="{{ route('girvi.release.receipt', $loan) }}" class="btn btn-outline-success">
            <i class="bi bi-printer me-1"></i>Release receipt
        </a>
    @endif
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Loan amount</div>
                    <div class="h4 mb-0">{{ money($loan->principal_amount) }}</div>
                    <div class="small text-muted">{{ number_format($loan->monthlyInterestRate(), 2) }}% per month</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Outstanding</div>
                    <div class="h4 mb-0 {{ $loan->outstandingPrincipal() > 0 ? 'text-danger' : 'text-success' }}">
                        {{ money($loan->outstandingPrincipal()) }}
                    </div>
                    <div class="small text-muted">{{ money($loan->interest_collected) }} interest collected</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Fine weight</div>
                    <div class="h4 mb-0">{{ number_format((float) $loan->fine_weight_grams, 3) }} g</div>
                    <div class="small text-muted">
                        of {{ number_format((float) $loan->net_weight_grams, 3) }} g net
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Due on</div>
                    <div class="h4 mb-0">{{ $loan->due_on->format('d M Y') }}</div>
                    @if (! $loan->isReleased() && $loan->daysOverdue() > 0)
                        <div class="small text-danger">{{ $loan->daysOverdue() }} days overdue</div>
                    @elseif ($loan->isReleased())
                        <div class="small text-success">Released {{ $loan->released_on->format('d M Y') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card gs-stat-card mb-3">
                <div class="card-header bg-white fw-semibold">Pledged items</div>
                @include('girvi._items-table', ['loan' => $loan])
            </div>

            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Payment history</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Date</th><th>Type</th><th class="d-none d-md-table-cell">Method</th><th class="d-none d-lg-table-cell">Receipt</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($loan->payments->sortByDesc('paid_on') as $payment)
                            <tr>
                                <td class="small">{{ $payment->paid_on->format('d M y') }}</td>
                                <td><span class="badge bg-secondary">{{ Str::headline($payment->type) }}</span></td>
                                <td class="small d-none d-md-table-cell">{{ Str::headline($payment->method) }}</td>
                                <td class="small font-monospace d-none d-lg-table-cell">{{ $payment->receipt_no ?: '--' }}</td>
                                <td class="text-end text-success">{{ money($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nothing collected yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card gs-stat-card mb-3">
                <div class="card-header bg-white fw-semibold">Girvi details</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5">Mortgage No</dt>
                        <dd class="col-7 font-monospace">{{ $loan->loan_no }}</dd>

                        <dt class="col-5">Invoice No</dt>
                        <dd class="col-7">{{ $loan->invoice_no ?: '--' }}</dd>

                        <dt class="col-5">Packet No</dt>
                        <dd class="col-7">{{ $loan->packet_no ?: '--' }}</dd>

                        <dt class="col-5">Barcode</dt>
                        <dd class="col-7 font-monospace">{{ $loan->barcode ?: '--' }}</dd>

                        <dt class="col-5">Deposited</dt>
                        <dd class="col-7">{{ $loan->disbursed_on->format('d M Y') }}</dd>

                        <dt class="col-5">Duration</dt>
                        <dd class="col-7">{{ $loan->duration_months }} months</dd>

                        <dt class="col-5">Estimate</dt>
                        <dd class="col-7">
                            {{ money($loan->estimate_amount) }}
                            <span class="text-muted">at {{ number_format((float) $loan->estimate_percent, 0) }}%</span>
                        </dd>

                        <dt class="col-5">Loan type</dt>
                        <dd class="col-7">{{ $loan->loan_type ?: '--' }}</dd>

                        <dt class="col-5">Refer by</dt>
                        <dd class="col-7">{{ $loan->refer_by ?: '--' }}</dd>
                    </dl>
                </div>
            </div>

            @if (! $loan->isReleased())
                @can('collect', $loan)
                    <div class="card gs-stat-card">
                        <div class="card-header bg-white fw-semibold">Collect interest</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('girvi.loans.interest', $loan) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                                               class="form-control @error('amount') is-invalid @enderror" required>
                                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="paid_on" class="form-label">Received on</label>
                                    <input type="date" id="paid_on" name="paid_on"
                                           value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                                           class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="method" class="form-label">Method</label>
                                    <select id="method" name="method" class="form-select" required>
                                        @foreach ([
                                            'cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card',
                                            'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque',
                                        ] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-cash-coin me-1"></i>Collect interest
                                </button>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endsection
