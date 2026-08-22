@extends('layouts.guest')

@section('title', 'Sign in')

@section('banner')
    <div class="gs-login-banner-inner">
        <div class="gs-login-kicker">
            <i class="bi bi-gem me-2"></i>GoldScore &middot; Girvi
        </div>
        <h1 class="gs-login-title">Gold Loan software for jewellers</h1>
        <p class="gs-login-lead">
            Business digital banao — girvi, udhaar khata and GoldScore in one shop counter.
        </p>

        <ul class="gs-login-points">
            <li><i class="bi bi-shield-check"></i>Secure handling of pledges</li>
            <li><i class="bi bi-lightning-charge"></i>Fast disbursement and release</li>
            <li><i class="bi bi-graph-up-arrow"></i>Real-time books and GoldScore</li>
        </ul>
    </div>
@endsection

@section('content')
    <h2 class="h4 mb-1">Sign in</h2>
    <p class="text-muted mb-4">Use the shop name and the owner's mobile number.</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="mb-3">
            <label for="company_name" class="form-label">Company name</label>
            <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}"
                   class="form-control @error('company_name') is-invalid @enderror"
                   autocomplete="organization"
                   placeholder="Mahadev Jewellers" required autofocus>
            @error('company_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Mobile number</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                   class="form-control @error('phone') is-invalid @enderror"
                   inputmode="numeric" autocomplete="username"
                   placeholder="10-digit mobile" required>
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="current-password" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-4">
            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">Sign in</button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
        New to GoldScore?
        <a href="{{ route('register') }}">Create a store account</a>
    </p>
@endsection
