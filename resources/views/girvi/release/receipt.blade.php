@extends('layouts.print', ['backUrl' => route('girvi.loans.show', $loan)])

@section('title', 'Release receipt ' . ($settlement?->receipt_no ?? $loan->receipt_no))

@section('content')
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <div class="h4 mb-0">{{ auth()->user()->store->name }}</div>
            <div class="small text-muted">{{ auth()->user()->store->fullAddress() }}</div>
        </div>
        <div class="text-end">
            <div class="h5 mb-0">Release Receipt</div>
            <div class="font-monospace">{{ $settlement?->receipt_no ?? '--' }}</div>
            <div class="small text-muted">
                {{ $loan->released_on?->format('d M Y') ?? now()->format('d M Y') }}
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-7">
            <div class="text-uppercase small text-muted">Returned to</div>
            <div class="fw-semibold">{{ $loan->customer->full_name }}</div>
            <div class="small">{{ $loan->customer->fullAddress() }}</div>
            <div class="small font-monospace">{{ $loan->customer->maskedMobile() }}</div>
        </div>
        <div class="col-5">
            <dl class="row mb-0 small">
                <dt class="col-6">Girvi receipt</dt>
                <dd class="col-6 font-monospace">{{ $loan->receipt_no }}</dd>

                <dt class="col-6">Mortgage No</dt>
                <dd class="col-6 font-monospace">{{ $loan->loan_no }}</dd>

                <dt class="col-6">Deposited</dt>
                <dd class="col-6">{{ $loan->disbursed_on->format('d M Y') }}</dd>

                <dt class="col-6">Released</dt>
                <dd class="col-6">{{ $loan->released_on?->format('d M Y') ?? '--' }}</dd>
            </dl>
        </div>
    </div>

    <div class="border rounded mb-3">
        @include('girvi._items-table', ['loan' => $loan])
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <dl class="row mb-0 small">
                <dt class="col-6">Loan amount</dt>
                <dd class="col-6">{{ money($loan->principal_amount) }}</dd>

                <dt class="col-6">Interest collected</dt>
                <dd class="col-6">{{ money($loan->interest_collected) }}</dd>

                <dt class="col-6">Extra amount</dt>
                <dd class="col-6">{{ money($loan->extra_amount) }}</dd>

                <dt class="col-6">Notice charge</dt>
                <dd class="col-6">{{ money($loan->notice_charge) }}</dd>

                <dt class="col-6">Discount</dt>
                <dd class="col-6">{{ money($loan->discount) }}</dd>
            </dl>
        </div>
        <div class="col-6 text-end">
            <div class="text-uppercase small text-muted">Total collected</div>
            <div class="h3 mb-0">{{ money($settlement?->amount ?? 0) }}</div>
        </div>
    </div>

    <p class="small text-muted border-top pt-3">
        The jewellery listed above has been returned in full and the account is closed. Nothing further
        is owed on this pledge.
    </p>

    <div class="row mt-5 pt-4">
        <div class="col-6">
            <div class="border-top pt-2 small">Received the jewellery — customer signature</div>
        </div>
        <div class="col-6 text-end">
            <div class="border-top pt-2 small">For {{ auth()->user()->store->name }}</div>
        </div>
    </div>
@endsection
