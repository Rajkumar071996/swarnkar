@extends('layouts.print', ['backUrl' => route('girvi.loans.show', $loan)])

@section('title', 'Girvi receipt ' . $loan->receipt_no)

@section('content')
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <div class="h4 mb-0">{{ auth()->user()->store->name }}</div>
            <div class="small text-muted">{{ auth()->user()->store->fullAddress() }}</div>
        </div>
        <div class="text-end">
            <div class="h5 mb-0">Girvi Receipt</div>
            <div class="font-monospace">{{ $loan->receipt_no }}</div>
            <div class="small text-muted">{{ $loan->disbursed_on->format('d M Y') }}</div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-7">
            <div class="text-uppercase small text-muted">Customer</div>
            <div class="fw-semibold">{{ $loan->customer->full_name }}</div>
            <div class="small">{{ $loan->customer->fullAddress() }}</div>
            <div class="small font-monospace">{{ $loan->customer->maskedMobile() }}</div>
        </div>
        <div class="col-5">
            <dl class="row mb-0 small">
                <dt class="col-6">Mortgage No</dt>
                <dd class="col-6 font-monospace">{{ $loan->loan_no }}</dd>

                <dt class="col-6">Invoice No</dt>
                <dd class="col-6">{{ $loan->invoice_no ?: '--' }}</dd>

                <dt class="col-6">Packet No</dt>
                <dd class="col-6">{{ $loan->packet_no ?: '--' }}</dd>

                <dt class="col-6">Due on</dt>
                <dd class="col-6">{{ $loan->due_on->format('d M Y') }}</dd>
            </dl>
        </div>
    </div>

    <div class="border rounded mb-3">
        @include('girvi._items-table', ['loan' => $loan])
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <dl class="row mb-0 small">
                <dt class="col-6">Estimate ({{ number_format((float) $loan->estimate_percent, 0) }}%)</dt>
                <dd class="col-6">{{ money($loan->estimate_amount) }}</dd>

                <dt class="col-6">Interest per year</dt>
                <dd class="col-6">{{ number_format((float) $loan->interest_rate, 2) }}%</dd>

                <dt class="col-6">Duration</dt>
                <dd class="col-6">{{ $loan->duration_months }} months</dd>
            </dl>
        </div>
        <div class="col-6 text-end">
            <div class="text-uppercase small text-muted">Loan amount</div>
            <div class="h3 mb-0">{{ money($loan->principal_amount) }}</div>
        </div>
    </div>

    <p class="small text-muted border-top pt-3">
        The jewellery listed above is held against this loan and is returned once the loan and the
        interest due to the date of release are paid in full. Interest is charged for a full month
        even where only part of a month has run. This receipt must be produced at the time of release.
    </p>

    <div class="row mt-5 pt-4">
        <div class="col-6">
            <div class="border-top pt-2 small">Customer signature</div>
        </div>
        <div class="col-6 text-end">
            <div class="border-top pt-2 small">For {{ auth()->user()->store->name }}</div>
        </div>
    </div>
@endsection
