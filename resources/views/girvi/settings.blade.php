@extends('layouts.app')

@section('title', 'Girvi settings')
@section('heading', 'Girvi settings')
@section('subheading', 'The rates every new pledge is valued at.')

@section('content')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Rate per gram</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('girvi.settings.rates') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="gold" class="form-label">Gold</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" id="gold" name="gold"
                                       value="{{ old('gold', $rates['gold']) }}"
                                       class="form-control @error('gold') is-invalid @enderror" required>
                                <span class="input-group-text">per gram</span>
                                @error('gold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="silver" class="form-label">Silver</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" id="silver" name="silver"
                                       value="{{ old('silver', $rates['silver']) }}"
                                       class="form-control @error('silver') is-invalid @enderror" required>
                                <span class="input-group-text">per gram</span>
                                @error('silver') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save today's rates
                        </button>
                    </form>

                    <p class="text-muted small mb-0 mt-3">
                        Rates are saved against today. A pledge keeps the rate it was priced at, so
                        changing these does not touch anything already in the book.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Recent changes</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Date</th><th>Metal</th><th class="text-end">Rate</th><th>Set by</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($history as $rate)
                            <tr>
                                <td class="small">{{ $rate->effective_on->format('d M Y') }}</td>
                                <td>{{ $rate->metalLabel() }}</td>
                                <td class="text-end">{{ money($rate->rate_per_gram) }}</td>
                                <td class="small text-muted">{{ $rate->updatedBy?->name ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No rates saved yet, so the defaults are in use.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
