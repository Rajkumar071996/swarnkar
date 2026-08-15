@extends('layouts.app')

@section('title', 'Check GoldScore')
@section('heading', 'Check GoldScore')
@section('subheading', 'Search the network by phone, PAN or Aadhaar before extending credit.')

@section('content')
    <div class="card gs-stat-card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('lookup.search') }}" class="row g-2">
                @csrf
                <div class="col-md-9">
                    <input type="text" name="q" value="{{ $term }}" required minlength="3"
                           class="form-control form-control-lg @error('q') is-invalid @enderror"
                           placeholder="Phone number, PAN or Aadhaar" autofocus>
                    @error('q') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary btn-lg"><i class="bi bi-search me-1"></i>Search</button>
                </div>
            </form>

            <p class="text-muted small mb-0 mt-3">
                <i class="bi bi-shield-lock me-1"></i>
                Scores are only revealed after the customer approves the check with a one-time code.
                {{ $channelHint }}
            </p>
        </div>
    </div>

    @if ($results->isNotEmpty())
        <div class="card gs-stat-card">
            <div class="card-header bg-white fw-semibold">
                {{ $results->count() }} {{ Str::plural('match', $results->count()) }}
            </div>
            <div class="list-group list-group-flush">
                @foreach ($results as $customer)
                    <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                        <div>
                            <div class="fw-semibold">{{ $customer->full_name }}</div>
                            <div class="text-muted small font-monospace">
                                {{ $customer->maskedMobile() }}
                                @if ($customer->city) &middot; {{ $customer->city }} @endif
                            </div>
                        </div>
                        <a href="{{ route('lookup.report', $customer) }}" class="btn btn-outline-primary">
                            Request consent <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($term !== '')
        <div class="card gs-stat-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-person-x fs-1 text-muted"></i>
                <p class="mt-3 mb-1 fw-semibold">No profile found for "{{ $term }}"</p>
                <p class="text-muted">This person has no history anywhere on the GoldScore network.</p>
                <a href="{{ route('customers.create') }}" class="btn btn-outline-primary">Add them as a customer</a>
            </div>
        </div>
    @endif
@endsection
