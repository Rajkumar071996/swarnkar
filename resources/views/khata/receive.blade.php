@extends('layouts.app')

@section('title', 'Received entry')
@section('heading', 'Received entry')
@section('subheading', $customer
    ? 'Record money received from '.$customer->full_name
    : 'Record money received against a customer khata')

@section('content')
    <div class="card gs-stat-card">
        <div class="card-body">
            @if (! $customer)
                <form method="GET" action="{{ route('khata.receive') }}" class="row g-3 mb-0">
                    <div class="col-md-8">
                        <label for="customer" class="form-label">Customer</label>
                        <select id="customer" name="customer" class="form-select" required
                                onchange="this.form.submit()">
                            <option value="">Select a customer with outstanding balance</option>
                            @foreach ($customers as $row)
                                <option value="{{ $row->id }}">
                                    {{ $row->full_name }} ({{ $row->maskedMobile() }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Only customers who still owe your store appear here.
                        </div>
                    </div>
                </form>
            @else
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
                    <div>
                        <div class="fw-semibold">{{ $customer->full_name }}</div>
                        <div class="small text-muted font-monospace">{{ $customer->maskedMobile() }}</div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted text-uppercase small">Outstanding at your store</div>
                        <div class="h4 mb-0 {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">
                            {{ money($outstanding) }}
                        </div>
                    </div>
                </div>

                @if ($outstanding <= 0)
                    <div class="alert alert-light border mb-0">
                        Nothing is outstanding on this khata.
                        <a href="{{ route('khata.show', $customer) }}">Open khata</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('khata.receive.store', $customer) }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="amount" class="form-label">Amount received</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" min="0.01" max="{{ $outstanding }}"
                                           id="amount" name="amount"
                                           value="{{ old('amount', $outstanding) }}"
                                           class="form-control @error('amount') is-invalid @enderror" required>
                                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="paid_on" class="form-label">Received on</label>
                                <input type="date" id="paid_on" name="paid_on"
                                       value="{{ old('paid_on', now()->toDateString()) }}"
                                       max="{{ now()->toDateString() }}"
                                       class="form-control @error('paid_on') is-invalid @enderror" required>
                                @error('paid_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="method" class="form-label">Method</label>
                                <select id="method" name="method"
                                        class="form-select @error('method') is-invalid @enderror" required>
                                    @foreach ([
                                        'cash' => 'Cash',
                                        'upi' => 'UPI',
                                        'card' => 'Card',
                                        'bank_transfer' => 'Bank transfer',
                                        'cheque' => 'Cheque',
                                    ] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-8">
                                <label for="udhaar_id" class="form-label">Apply against</label>
                                <select id="udhaar_id" name="udhaar_id"
                                        class="form-select @error('udhaar_id') is-invalid @enderror">
                                    <option value="">Oldest outstanding first (recommended)</option>
                                    @foreach ($openEntries as $entry)
                                        <option value="{{ $entry->id }}" @selected((string) old('udhaar_id') === (string) $entry->id)>
                                            {{ $entry->due_on->format('d M Y') }}
                                            · {{ Str::limit($entry->item_description, 32) }}
                                            · {{ money($entry->outstandingAmount()) }} due
                                        </option>
                                    @endforeach
                                </select>
                                @error('udhaar_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Leave as recommended to clear older bills first when the amount covers more than one entry.
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="reference" class="form-label">Reference <span class="text-muted">(optional)</span></label>
                                <input type="text" id="reference" name="reference" value="{{ old('reference') }}"
                                       class="form-control @error('reference') is-invalid @enderror"
                                       placeholder="UPI ref / cheque no.">
                                @error('reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cash-coin me-1"></i>Save received entry
                            </button>
                            <a href="{{ route('khata.show', $customer) }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endsection
