@extends('layouts.app')

@section('title', 'Release')
@section('heading', 'Release')
@section('subheading', 'Settle a pledge and hand the jewellery back.')

@section('content')
    <div class="card gs-stat-card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('girvi.release.create') }}" class="row g-2 align-items-end mb-0">
                <div class="col-md-8">
                    <label for="q" class="form-label">Search by receipt, invoice, barcode, mortgage no or name</label>
                    <input type="text" id="q" name="q" value="{{ $term }}" class="form-control"
                           placeholder="GRT-19/27-4, barcode, or customer name">
                </div>
                <div class="col-md-4 d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Find pledge
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if (! $loan)
        <div class="card gs-stat-card">
            <div class="card-header bg-white fw-semibold">Unreleased pledges</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Receipt</th><th>Customer</th>
                        <th class="d-none d-md-table-cell">Deposited</th>
                        <th>Due</th>
                        <th class="text-end">Loan</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($matches as $row)
                        <tr>
                            <td class="font-monospace small">{{ $row->receipt_no }}</td>
                            <td class="fw-semibold">{{ $row->customer->full_name }}</td>
                            <td class="small d-none d-md-table-cell">{{ $row->disbursed_on->format('d M y') }}</td>
                            <td class="small {{ $row->daysOverdue() > 0 ? 'text-danger' : '' }}">
                                {{ $row->due_on->format('d M y') }}
                            </td>
                            <td class="text-end">{{ money($row->principal_amount) }}</td>
                            <td class="text-end">
                                <a href="{{ route('girvi.release.create', ['loan' => $row->id]) }}"
                                   class="btn btn-sm btn-outline-primary">Select</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ $term !== '' ? 'No unreleased pledge matches that search.' : 'Nothing is pledged right now.' }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card gs-stat-card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">{{ $loan->customer->full_name }}</span>
                        <span class="font-monospace small">{{ $loan->receipt_no }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-4">Deposited</dt>
                            <dd class="col-8">{{ $loan->disbursed_on->format('d M Y') }}</dd>

                            <dt class="col-4">Due on</dt>
                            <dd class="col-8">
                                {{ $loan->due_on->format('d M Y') }}
                                @if ($loan->daysOverdue() > 0)
                                    <span class="text-danger">({{ $loan->daysOverdue() }} days overdue)</span>
                                @endif
                            </dd>

                            <dt class="col-4">Loan amount</dt>
                            <dd class="col-8">{{ money($loan->principal_amount) }}</dd>

                            <dt class="col-4">Interest rate</dt>
                            <dd class="col-8">{{ number_format($loan->monthlyInterestRate(), 2) }}% per month</dd>

                            <dt class="col-4">Packet No</dt>
                            <dd class="col-8">{{ $loan->packet_no ?: '--' }}</dd>
                        </dl>
                    </div>
                    @include('girvi._items-table', ['loan' => $loan])
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card gs-stat-card">
                    <div class="card-header bg-white fw-semibold">Settlement</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('girvi.release.store', $loan) }}" id="releaseForm">
                            @csrf

                            <div class="mb-3">
                                <label for="released_on" class="form-label">Date of release</label>
                                <input type="date" id="released_on" name="released_on"
                                       value="{{ old('released_on', now()->toDateString()) }}"
                                       max="{{ now()->toDateString() }}"
                                       class="form-control @error('released_on') is-invalid @enderror" required>
                                @error('released_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Interest is charged for {{ $summary['months'] }}
                                    {{ Str::plural('month', $summary['months']) }} to today. Change the date and save
                                    to recalculate.
                                </div>
                            </div>

                            <dl class="row small mb-3">
                                <dt class="col-7">Loan amount</dt>
                                <dd class="col-5 text-end" data-base>{{ money($summary['principal']) }}</dd>

                                <dt class="col-7">Interest to date</dt>
                                <dd class="col-5 text-end">{{ money($summary['interest_due']) }}</dd>

                                <dt class="col-7">Interest already paid</dt>
                                <dd class="col-5 text-end text-success">{{ money($summary['interest_paid']) }}</dd>

                                <dt class="col-7">Interest payable</dt>
                                <dd class="col-5 text-end">{{ money($summary['interest_payable']) }}</dd>
                            </dl>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label for="extra_amount" class="form-label small">Extra amount</label>
                                    <input type="number" step="0.01" min="0" id="extra_amount" name="extra_amount"
                                           value="{{ old('extra_amount', 0) }}"
                                           class="form-control form-control-sm js-charge">
                                </div>
                                <div class="col-6">
                                    <label for="extra_interest" class="form-label small">Extra interest</label>
                                    <input type="number" step="0.01" min="0" id="extra_interest" name="extra_interest"
                                           value="{{ old('extra_interest', 0) }}"
                                           class="form-control form-control-sm js-charge">
                                </div>
                                <div class="col-6">
                                    <label for="notice_charge" class="form-label small">Notice charge</label>
                                    <input type="number" step="0.01" min="0" id="notice_charge" name="notice_charge"
                                           value="{{ old('notice_charge', 0) }}"
                                           class="form-control form-control-sm js-charge">
                                </div>
                                <div class="col-6">
                                    <label for="discount" class="form-label small">Discount</label>
                                    <input type="number" step="0.01" min="0" id="discount" name="discount"
                                           value="{{ old('discount', 0) }}"
                                           class="form-control form-control-sm js-discount">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center border-top pt-3 mb-3">
                                <span class="text-uppercase small text-muted">Total to collect</span>
                                <span class="h4 mb-0" id="releaseTotal">{{ money($summary['total']) }}</span>
                            </div>

                            <div class="mb-3">
                                <label for="narration" class="form-label">Narration</label>
                                <input type="text" id="narration" name="narration" value="{{ old('narration') }}"
                                       class="form-control">
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-box-arrow-up me-1"></i>Release jewellery
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const base = @json($summary['principal'] + $summary['interest_payable']);
                const total = document.getElementById('releaseTotal');
                const form = document.getElementById('releaseForm');

                const formatter = new Intl.NumberFormat('en-IN', {
                    style: 'currency', currency: 'INR', minimumFractionDigits: 2,
                });

                function recalc() {
                    let amount = base;

                    form.querySelectorAll('.js-charge').forEach((input) => {
                        amount += parseFloat(input.value) || 0;
                    });

                    form.querySelectorAll('.js-discount').forEach((input) => {
                        amount -= parseFloat(input.value) || 0;
                    });

                    total.textContent = formatter.format(Math.max(0, amount));
                }

                form.addEventListener('input', recalc);
            })();
        </script>
    @endif
@endsection
