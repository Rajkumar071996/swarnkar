@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="mb-3">
            <label for="phone" class="form-label">Mobile number</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                   class="form-control @error('phone') is-invalid @enderror"
                   inputmode="numeric" autocomplete="username"
                   placeholder="10-digit mobile" required autofocus>
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

        <div class="form-check mb-3">
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
