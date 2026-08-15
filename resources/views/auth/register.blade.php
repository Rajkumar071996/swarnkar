@extends('layouts.guest')

@section('title', 'Create your store')
@section('subtitle', 'Open a GoldScore account for your jewellery shop')

@section('content')
    <form method="POST" action="{{ route('register.store') }}">
        @csrf

        <h2 class="h6 text-uppercase text-muted mb-3">Your store</h2>

        <div class="mb-3">
            <label for="store_name" class="form-label">Shop name</label>
            <input type="text" id="store_name" name="store_name" value="{{ old('store_name') }}"
                   class="form-control @error('store_name') is-invalid @enderror"
                   placeholder="e.g. Swarnkar Jewellers" required autofocus>
            @error('store_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="address_line" class="form-label">Shop address</label>
            <textarea id="address_line" name="address_line" rows="2"
                      class="form-control @error('address_line') is-invalid @enderror"
                      placeholder="Shop no., street, market / area" required>{{ old('address_line') }}</textarea>
            @error('address_line')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-2 mb-3">
            <div class="col-12 col-sm-6">
                <label for="city" class="form-label">City</label>
                <input type="text" id="city" name="city" value="{{ old('city') }}"
                       class="form-control @error('city') is-invalid @enderror" required>
                @error('city')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-sm-6">
                <label for="state" class="form-label">State</label>
                <input type="text" id="state" name="state" value="{{ old('state') }}"
                       class="form-control @error('state') is-invalid @enderror" required>
                @error('state')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-4">
            <label for="pincode" class="form-label">PIN code</label>
            <input type="text" id="pincode" name="pincode" value="{{ old('pincode') }}"
                   class="form-control @error('pincode') is-invalid @enderror"
                   inputmode="numeric" maxlength="6" placeholder="6-digit PIN" required>
            @error('pincode')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <h2 class="h6 text-uppercase text-muted mb-3">Opening books</h2>

        <p class="text-muted small">Cash in hand and bank should add up to the capital you are starting with.</p>

        <div class="mb-3">
            <label for="opening_capital" class="form-label">Capital</label>
            <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" id="opening_capital" name="opening_capital"
                       value="{{ old('opening_capital') }}"
                       class="form-control @error('opening_capital') is-invalid @enderror" required>
                @error('opening_capital')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-2 mb-4">
            <div class="col-12 col-sm-6">
                <label for="cash_in_hand" class="form-label">Cash in hand</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" step="0.01" min="0" id="cash_in_hand" name="cash_in_hand"
                           value="{{ old('cash_in_hand') }}"
                           class="form-control @error('cash_in_hand') is-invalid @enderror" required>
                    @error('cash_in_hand')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-12 col-sm-6">
                <label for="bank_balance" class="form-label">Bank</label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" step="0.01" min="0" id="bank_balance" name="bank_balance"
                           value="{{ old('bank_balance') }}"
                           class="form-control @error('bank_balance') is-invalid @enderror" required>
                    @error('bank_balance')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <h2 class="h6 text-uppercase text-muted mb-3">Owner account</h2>

        <div class="mb-3">
            <label for="name" class="form-label">Your name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror" required>
            @error('name')
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
            <div class="form-text">This is what you will use to sign in.</div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="text-muted">(optional)</span></label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="new-password" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">Create store account</button>
    </form>

    <p class="text-center text-muted small mt-3 mb-0">
        Already have an account?
        <a href="{{ route('login') }}">Sign in</a>
    </p>

    <script>
        (function () {
            const capital = document.getElementById('opening_capital');
            const cash = document.getElementById('cash_in_hand');
            const bank = document.getElementById('bank_balance');
            if (!capital || !cash || !bank) return;

            const sum = () => {
                const total = (parseFloat(cash.value) || 0) + (parseFloat(bank.value) || 0);
                capital.value = total.toFixed(2);
            };

            cash.addEventListener('input', sum);
            bank.addEventListener('input', sum);
        })();
    </script>
@endsection
