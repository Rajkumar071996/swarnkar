@extends('layouts.app')

@section('title', 'Udhaar detail')
@section('heading', $udhaar->item_description)
@section('subheading', $udhaar->customer->full_name . ' · issued ' . $udhaar->issued_on->format('d M Y'))

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card gs-stat-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted text-uppercase small">Outstanding</div>
                            <div class="h2 mb-0">{{ money($udhaar->outstandingAmount()) }}</div>
                        </div>
                        <span class="badge {{ $udhaar->status->badgeClass() }} fs-6">
                            {{ $udhaar->status->label() }}
                        </span>
                    </div>

                    <dl class="row small mb-0">
                        <dt class="col-6">Principal</dt>
                        <dd class="col-6 text-end">{{ money($udhaar->principal_amount) }}</dd>

                        <dt class="col-6">Paid so far</dt>
                        <dd class="col-6 text-end">{{ money($udhaar->amount_paid) }}</dd>

                        <dt class="col-6">Due date</dt>
                        <dd class="col-6 text-end">{{ $udhaar->due_on->format('d M Y') }}</dd>

                        @if ($udhaar->status->isOutstanding() && $udhaar->due_on->isPast())
                            <dt class="col-6 text-danger">Days overdue</dt>
                            <dd class="col-6 text-end text-danger">{{ $udhaar->daysOverdue() }}</dd>
                        @endif

                        @if ($udhaar->settled_on)
                            <dt class="col-6">Settled on</dt>
                            <dd class="col-6 text-end">{{ $udhaar->settled_on->format('d M Y') }}</dd>
                        @endif

                        @if ($udhaar->invoice_no)
                            <dt class="col-6">Invoice</dt>
                            <dd class="col-6 text-end font-monospace">{{ $udhaar->invoice_no }}</dd>
                        @endif

                        @if ($udhaar->collateral_description)
                            <dt class="col-6">Collateral</dt>
                            <dd class="col-6 text-end">
                                {{ $udhaar->collateral_description }}
                                @if ($udhaar->collateral_weight_grams)
                                    <div class="text-muted">{{ grams($udhaar->collateral_weight_grams) }}</div>
                                @endif
                            </dd>
                        @endif
                    </dl>

                    @if ($udhaar->notes)
                        <hr>
                        <p class="small text-muted mb-0">{{ $udhaar->notes }}</p>
                    @endif
                </div>
            </div>

            @can('writeOff', $udhaar)
                @if ($udhaar->status->isOutstanding())
                    <div class="card gs-stat-card border-danger">
                        <div class="card-body">
                            <h3 class="h6">Write off</h3>
                            <p class="small text-muted">
                                Closes the account as an unrecoverable loss and lowers the customer's GoldScore
                                across the network.
                            </p>
                            <form method="POST" action="{{ route('udhaars.write-off', $udhaar) }}">
                                @csrf
                                <input type="text" name="notes" class="form-control form-control-sm mb-2"
                                       placeholder="Reason (optional)">
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Write off {{ money($udhaar->outstandingAmount()) }}?')">
                                    Write off account
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan
        </div>

        <div class="col-lg-7">
            @can('recordPayment', $udhaar)
                @if ($udhaar->status->isOutstanding())
                    <div class="card gs-stat-card mb-3">
                        <div class="card-header bg-white fw-semibold">Record a payment</div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('udhaars.payments.store', $udhaar) }}" class="row g-2">
                                @csrf
                                <div class="col-md-3">
                                    <label for="amount" class="form-label small">Amount</label>
                                    <input type="number" step="0.01" min="0.01"
                                           max="{{ $udhaar->outstandingAmount() }}"
                                           id="amount" name="amount"
                                           value="{{ old('amount', $udhaar->outstandingAmount()) }}"
                                           class="form-control @error('amount') is-invalid @enderror" required>
                                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label for="paid_on" class="form-label small">Paid on</label>
                                    <input type="date" id="paid_on" name="paid_on"
                                           value="{{ old('paid_on', now()->toDateString()) }}"
                                           max="{{ now()->toDateString() }}"
                                           class="form-control @error('paid_on') is-invalid @enderror" required>
                                    @error('paid_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label for="method" class="form-label small">Method</label>
                                    <select id="method" name="method" class="form-select" required>
                                        @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-primary w-100">Record</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Payment history</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Date</th><th>Method</th><th class="d-none d-md-table-cell">Reference</th><th class="d-none d-lg-table-cell">Recorded by</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($udhaar->payments->sortByDesc('paid_on') as $payment)
                            <tr>
                                <td>{{ $payment->paid_on->format('d M Y') }}</td>
                                <td>{{ Str::headline($payment->method) }}</td>
                                <td class="font-monospace small d-none d-md-table-cell">{{ $payment->reference ?: '--' }}</td>
                                <td class="small text-muted d-none d-lg-table-cell">{{ $payment->recordedBy?->name ?: '--' }}</td>
                                <td class="text-end">{{ money($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No payments yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
