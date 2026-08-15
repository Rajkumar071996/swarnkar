@extends('layouts.app')

@section('title', $customer->full_name)
@section('heading', $customer->full_name)
@section('subheading', 'Customer profile and ledger history at your store.')

@section('actions')
    <a href="{{ route('lookup.index', ['q' => $customer->maskedMobile()]) }}" class="btn btn-primary">
        <i class="bi bi-shield-check me-1"></i>Check GoldScore
    </a>
    @can('update', $customer)
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary">Edit</a>
    @endcan
@endsection

@section('content')
    @unless ($isLinked)
        <div class="alert alert-info">
            This profile exists on the network but has no transaction history at your store yet.
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Profile</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Mobile</dt>
                        <dd class="col-7 font-monospace">{{ $customer->maskedMobile() }}</dd>

                        <dt class="col-5">PAN</dt>
                        <dd class="col-7 font-monospace">{{ $customer->maskedPan() ?: '--' }}</dd>

                        <dt class="col-5">Aadhaar</dt>
                        <dd class="col-7 font-monospace">
                            {{ $customer->aadhaar_last4 ? 'XXXXXXXX'.$customer->aadhaar_last4 : '--' }}
                        </dd>

                        <dt class="col-5">Date of birth</dt>
                        <dd class="col-7">{{ $customer->date_of_birth?->format('d M Y') ?: '--' }}</dd>

                        <dt class="col-5">City</dt>
                        <dd class="col-7">{{ $customer->city ?: '--' }}</dd>

                        <dt class="col-5">State</dt>
                        <dd class="col-7">{{ $customer->state ?: '--' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card gs-stat-card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Udhar khata at your store</span>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('khata.show', $customer) }}"
                           class="btn btn-sm btn-outline-secondary">Open khata</a>
                        @can('create', App\Models\Udhaar::class)
                            <a href="{{ route('udhaars.create', ['customer' => $customer->id]) }}"
                               class="btn btn-sm btn-outline-primary">Give credit</a>
                        @endcan
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Issued</th>
                            <th>Item</th>
                            <th class="text-end d-none d-md-table-cell">Principal</th>
                            <th class="text-end">Outstanding</th>
                            <th class="d-none d-sm-table-cell">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($udhaars as $udhaar)
                            <tr>
                                <td>{{ $udhaar->issued_on->format('d M Y') }}</td>
                                <td>
                                    <a href="{{ route('udhaars.show', $udhaar) }}">{{ $udhaar->item_description }}</a>
                                </td>
                                <td class="text-end d-none d-md-table-cell">{{ money($udhaar->principal_amount) }}</td>
                                <td class="text-end">{{ money($udhaar->outstandingAmount()) }}</td>
                                <td class="d-none d-sm-table-cell"><span class="badge {{ $udhaar->status->badgeClass() }}">{{ $udhaar->status->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No store credit recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
@endsection
