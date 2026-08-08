@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label for="full_name" class="form-label">Full name</label>
        <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $customer->full_name ?? '') }}"
               class="form-control @error('full_name') is-invalid @enderror" required autofocus>
        @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="mobile" class="form-label">Mobile number</label>
        <input type="text" id="mobile" name="mobile" value="{{ old('mobile', $customer->mobile ?? '') }}"
               class="form-control @error('mobile') is-invalid @enderror" inputmode="numeric" required>
        @error('mobile') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Stored encrypted. This is the primary identity across the network.</div>
    </div>

    <div class="col-md-6">
        <label for="pan" class="form-label">PAN <span class="text-muted">(optional)</span></label>
        <input type="text" id="pan" name="pan" value="{{ old('pan', $customer->pan ?? '') }}"
               class="form-control text-uppercase @error('pan') is-invalid @enderror" maxlength="10">
        @error('pan') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="aadhaar" class="form-label">Aadhaar <span class="text-muted">(optional)</span></label>
        <input type="text" id="aadhaar" name="aadhaar" value="{{ old('aadhaar') }}"
               class="form-control @error('aadhaar') is-invalid @enderror" inputmode="numeric" maxlength="12"
               placeholder="{{ isset($customer) && $customer->aadhaar_last4 ? 'XXXXXXXX'.$customer->aadhaar_last4 : '' }}">
        @error('aadhaar') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Only the last 4 digits are retained. The full number is never stored.</div>
    </div>

    <div class="col-md-4">
        <label for="date_of_birth" class="form-label">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth"
               value="{{ old('date_of_birth', isset($customer) ? $customer->date_of_birth?->format('Y-m-d') : '') }}"
               class="form-control @error('date_of_birth') is-invalid @enderror">
        @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label for="address_line" class="form-label">Address</label>
        <input type="text" id="address_line" name="address_line"
               value="{{ old('address_line', $customer->address_line ?? '') }}"
               class="form-control @error('address_line') is-invalid @enderror">
        @error('address_line') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="city" class="form-label">City</label>
        <input type="text" id="city" name="city" value="{{ old('city', $customer->city ?? '') }}"
               class="form-control @error('city') is-invalid @enderror">
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="state" class="form-label">State</label>
        <input type="text" id="state" name="state" value="{{ old('state', $customer->state ?? '') }}"
               class="form-control @error('state') is-invalid @enderror">
        @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="pincode" class="form-label">PIN code</label>
        <input type="text" id="pincode" name="pincode" value="{{ old('pincode', $customer->pincode ?? '') }}"
               class="form-control @error('pincode') is-invalid @enderror" inputmode="numeric" maxlength="6">
        @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @empty($customer)
        <div class="col-md-6">
            <label for="local_reference" class="form-label">Your khata reference <span class="text-muted">(optional)</span></label>
            <input type="text" id="local_reference" name="local_reference" value="{{ old('local_reference') }}"
                   class="form-control @error('local_reference') is-invalid @enderror">
            @error('local_reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endempty

    <div class="col-12">
        <hr class="mt-2 mb-1">
        <div class="text-muted small">For girvi — optional unless you pledge jewellery for this customer.</div>
    </div>

    <div class="col-md-3">
        <label for="ledger_no" class="form-label">Ledger No</label>
        <input type="text" id="ledger_no" name="ledger_no" value="{{ old('ledger_no', $ledgerNo ?? '') }}"
               class="form-control @error('ledger_no') is-invalid @enderror">
        @error('ledger_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="post" class="form-label">Post</label>
        <input type="text" id="post" name="post" value="{{ old('post', $customer->post ?? '') }}"
               class="form-control @error('post') is-invalid @enderror">
        @error('post') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="caste" class="form-label">Caste</label>
        <input type="text" id="caste" name="caste" value="{{ old('caste', $customer->caste ?? '') }}"
               class="form-control @error('caste') is-invalid @enderror">
        @error('caste') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="business_type" class="form-label">Business type</label>
        <select id="business_type" name="business_type"
                class="form-select @error('business_type') is-invalid @enderror">
            <option value="">Not set</option>
            @foreach (['agriculture' => 'Agriculture', 'non_agriculture' => 'Non-Agriculture'] as $value => $label)
                <option value="{{ $value }}" @selected(old('business_type', $customer->business_type ?? '') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('business_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
