@extends('layouts.app')

@section('title', 'Shop books')
@section('heading', 'Shop books')
@section('subheading', 'Capital you started with, cash and bank you have today, and expenses paid out.')

@section('content')
    @include('partials.books-cards')

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card gs-stat-card mb-3">
                <div class="card-header bg-white fw-semibold">Record an expense</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('books.expenses.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                                       value="{{ old('amount') }}"
                                       class="form-control @error('amount') is-invalid @enderror" required>
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="paid_from" class="form-label">Paid from</label>
                            <select id="paid_from" name="paid_from"
                                    class="form-select @error('paid_from') is-invalid @enderror" required>
                                <option value="cash" @selected(old('paid_from', 'cash') === 'cash')>Cash in hand</option>
                                <option value="bank" @selected(old('paid_from') === 'bank')>Bank</option>
                            </select>
                            @error('paid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="paid_on" class="form-label">Date</label>
                            <input type="date" id="paid_on" name="paid_on"
                                   value="{{ old('paid_on', now()->toDateString()) }}"
                                   max="{{ now()->toDateString() }}"
                                   class="form-control @error('paid_on') is-invalid @enderror" required>
                            @error('paid_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="narration" class="form-label">What was it for</label>
                            <input type="text" id="narration" name="narration" value="{{ old('narration') }}"
                                   class="form-control @error('narration') is-invalid @enderror"
                                   placeholder="e.g. Shop rent, electricity" required>
                            @error('narration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save expense
                        </button>
                    </form>
                </div>
            </div>

            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Correct the books</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('books.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="opening_capital" class="form-label">Capital</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" id="opening_capital" name="opening_capital"
                                       value="{{ old('opening_capital', $store->opening_capital) }}"
                                       class="form-control @error('opening_capital') is-invalid @enderror" required>
                                @error('opening_capital') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">What you started the shop with. This does not change when you pay an expense.</div>
                        </div>

                        <div class="mb-3">
                            <label for="cash_in_hand" class="form-label">Cash in hand</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" id="cash_in_hand" name="cash_in_hand"
                                       value="{{ old('cash_in_hand', $store->cash_in_hand) }}"
                                       class="form-control @error('cash_in_hand') is-invalid @enderror" required>
                                @error('cash_in_hand') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="bank_balance" class="form-label">Bank</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0" id="bank_balance" name="bank_balance"
                                       value="{{ old('bank_balance', $store->bank_balance) }}"
                                       class="form-control @error('bank_balance') is-invalid @enderror" required>
                                @error('bank_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary">Save books</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card gs-stat-card">
                <div class="card-header bg-white fw-semibold">Recent expenses</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>For</th>
                            <th>From</th>
                            <th class="text-end">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="small">{{ $expense->paid_on->format('d M Y') }}</td>
                                <td>{{ $expense->narration }}</td>
                                <td class="small text-muted">{{ $expense->paid_from === 'bank' ? 'Bank' : 'Cash' }}</td>
                                <td class="text-end">{{ money($expense->amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No expenses recorded yet.
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
