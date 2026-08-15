@extends('layouts.app')

@section('title', 'Record udhaar')
@section('heading', 'Record udhaar')
@section('subheading', 'Store credit extended against an order.')

@section('content')
    <div class="card gs-stat-card">
        <div class="card-body">
            <form method="POST" action="{{ route('udhaars.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select id="customer_id" name="customer_id"
                                class="form-select @error('customer_id') is-invalid @enderror" required>
                            <option value="">Select a customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    @selected((int) old('customer_id', $selectedCustomer) === $customer->id)>
                                    {{ $customer->full_name }} ({{ $customer->maskedMobile() }})
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">
                            Only customers linked to your store appear here.
                            <a href="{{ route('customers.create') }}">Add a new customer</a>.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="invoice_no" class="form-label">Invoice number</label>
                        <input type="text" id="invoice_no" name="invoice_no" value="{{ old('invoice_no') }}"
                               class="form-control @error('invoice_no') is-invalid @enderror">
                        @error('invoice_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-8">
                        <label for="item_description" class="form-label">Item</label>
                        <input type="text" id="item_description" name="item_description"
                               value="{{ old('item_description') }}"
                               class="form-control @error('item_description') is-invalid @enderror"
                               placeholder="e.g. 22K gold chain, 18.4 g" required>
                        @error('item_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="principal_amount" class="form-label">Credit amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="1" id="principal_amount" name="principal_amount"
                                   value="{{ old('principal_amount') }}"
                                   class="form-control @error('principal_amount') is-invalid @enderror" required>
                            @error('principal_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="issued_on" class="form-label">Issued on</label>
                        <input type="date" id="issued_on" name="issued_on"
                               value="{{ old('issued_on', now()->toDateString()) }}"
                               max="{{ now()->toDateString() }}"
                               class="form-control @error('issued_on') is-invalid @enderror" required>
                        @error('issued_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="due_on" class="form-label">Due on</label>
                        <input type="date" id="due_on" name="due_on"
                               value="{{ old('due_on', now()->addDays(30)->toDateString()) }}"
                               class="form-control @error('due_on') is-invalid @enderror" required>
                        @error('due_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="collateral_weight_grams" class="form-label">Collateral weight</label>
                        <div class="input-group">
                            <input type="number" step="0.001" min="0" id="collateral_weight_grams"
                                   name="collateral_weight_grams" value="{{ old('collateral_weight_grams') }}"
                                   class="form-control @error('collateral_weight_grams') is-invalid @enderror">
                            <span class="input-group-text">g</span>
                            @error('collateral_weight_grams') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label for="collateral_description" class="form-label">Collateral taken</label>
                        <input type="text" id="collateral_description" name="collateral_description"
                               value="{{ old('collateral_description') }}"
                               class="form-control @error('collateral_description') is-invalid @enderror"
                               placeholder="Optional">
                        @error('collateral_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mt-4 d-flex flex-column flex-sm-row gap-2 gs-form-actions">
                    <button type="submit" class="btn btn-primary">Record udhaar</button>
                    <a href="{{ route('udhaars.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
