@extends('layouts.app')

@section('title', 'Customers')
@section('heading', 'Customers')
@section('subheading', 'People who have transacted with your store.')

@section('actions')
    <a href="{{ route('customers.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i>Add customer
    </a>
@endsection

@section('content')
    <div class="card gs-stat-card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('customers.index') }}" class="row g-2">
                <div class="col-md-9">
                    <input type="text" name="q" value="{{ $term }}" class="form-control"
                           placeholder="Search by name, mobile, PAN or Aadhaar">
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card gs-stat-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th class="d-none d-md-table-cell">City</th>
                    <th>Score</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="fw-semibold">{{ $customer->full_name }}</td>
                        <td class="font-monospace">{{ $customer->maskedMobile() }}</td>
                        <td class="d-none d-md-table-cell">{{ $customer->city ?: '--' }}</td>
                        <td>
                            @if ($customer->latestScore)
                                <span class="badge {{ $customer->latestScore->band->badgeClass() }}">
                                    {{ $customer->latestScore->score ?? 'NR' }}
                                    <span class="d-none d-sm-inline">{{ $customer->latestScore->band->label() }}</span>
                                </span>
                            @else
                                <span class="text-muted">Not computed</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary">
                                Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No customers found{{ $term !== '' ? ' for "'.$term.'"' : '' }}.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $customers->links() }}</div>
@endsection
