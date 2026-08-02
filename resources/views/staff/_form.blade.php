@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Full name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $staff->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $staff->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $staff->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="role" class="form-label">Role</label>
        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}"
                    @selected(old('role', isset($staff) ? $staff->role->value : 'staff') === $role->value)>
                    {{ $role->label() }}
                </option>
            @endforeach
        </select>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">
            Owners may extend udhaar, report defaults and manage staff. Other roles record payments and read scores.
        </div>
    </div>

    <div class="col-md-6">
        <label for="password" class="form-label">
            Password @isset($staff) <span class="text-muted">(leave blank to keep current)</span> @endisset
        </label>
        <input type="password" id="password" name="password"
               class="form-control @error('password') is-invalid @enderror" @empty($staff) required @endempty>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="form-control" @empty($staff) required @endempty>
    </div>

    @isset($staff)
        <div class="col-12">
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       @checked(old('is_active', $staff->is_active))>
                <label class="form-check-label" for="is_active">Account is active</label>
            </div>
        </div>
    @endisset
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
