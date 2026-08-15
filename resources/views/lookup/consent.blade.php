@extends('layouts.app')

@section('title', 'Customer consent')
@section('heading', 'Customer consent required')
@section('subheading', 'Under the DPDP Act the customer must authorise this credit check.')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card gs-stat-card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <i class="bi bi-shield-lock fs-2 text-primary me-3"></i>
                        <div>
                            <div class="h5 mb-0">{{ $customer->full_name }}</div>
                            <div class="text-muted font-monospace">{{ $customer->maskedMobile() }}</div>
                        </div>
                    </div>

                    @if ($pending)
                        <p>
                            A one-time code was sent to the customer's registered mobile number.
                            Ask them to read it out, then enter it below.
                        </p>

                        <form method="POST" action="{{ route('lookup.consent.verify', $customer) }}" class="row g-2 align-items-start">
                            @csrf
                            <div class="col-sm-6">
                                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                                       maxlength="8" required autofocus
                                       class="form-control form-control-lg text-center font-monospace @error('code') is-invalid @enderror"
                                       placeholder="0000">
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-6 d-grid">
                                <button class="btn btn-primary btn-lg">Verify and show score</button>
                            </div>
                        </form>

                        <div class="text-muted small mt-3">
                            Code expires {{ $pending->otp_expires_at->diffForHumans() }}.
                            Attempt {{ $pending->attempts }} of {{ config('goldscore.consent.max_attempts') }}.
                        </div>

                        <hr>

                        <form method="POST" action="{{ route('lookup.consent.request', $customer) }}">
                            @csrf
                            <button class="btn btn-link p-0">Send a new code</button>
                        </form>
                    @else
                        <p class="mb-4">
                            Sending a code notifies the customer that <strong>{{ auth()->user()->store->name }}</strong>
                            wants to check their GoldScore. The report stays open for
                            {{ config('goldscore.consent.grant_ttl_minutes') }} minutes once they approve.
                        </p>

                        <form method="POST" action="{{ route('lookup.consent.request', $customer) }}"
                              class="d-flex flex-column flex-sm-row gap-2 gs-form-actions">
                            @csrf
                            <button class="btn btn-primary btn-lg">
                                <i class="bi bi-send me-1"></i>Send consent code
                            </button>
                            <a href="{{ route('lookup.index') }}" class="btn btn-outline-secondary btn-lg">Back</a>
                        </form>
                    @endif

                    <div class="alert alert-secondary mt-4 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>{{ $channelHint }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
