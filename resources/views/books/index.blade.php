@extends('layouts.app')

@section('title', 'Shop books')
@section('heading', 'Shop books')
@section('subheading', 'Capital you started with, cash and bank you have today, and money that later came in or went out.')

@section('content')
    @include('partials.books-cards')

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card gs-stat-card h-100">
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
        </div>

        <div class="col-lg-6">
            <div class="card gs-stat-card h-100">
                <div class="card-header bg-white fw-semibold">Record income</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('books.incomes.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="income_amount" class="form-label">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" id="income_amount" name="income_amount"
                                       value="{{ old('income_amount') }}"
                                       class="form-control @error('income_amount') is-invalid @enderror" required>
                                @error('income_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="kind" class="form-label">What is it</label>
                            <select id="kind" name="kind"
                                    class="form-select @error('kind') is-invalid @enderror" required>
                                <option value="income" @selected(old('kind', 'income') === 'income')>Income — someone paid / other receipt</option>
                                <option value="investment" @selected(old('kind') === 'investment')>Investment — adds to capital</option>
                            </select>
                            @error('kind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="received_in" class="form-label">Received in</label>
                            <select id="received_in" name="received_in"
                                    class="form-select @error('received_in') is-invalid @enderror" required>
                                <option value="cash" @selected(old('received_in', 'cash') === 'cash')>Cash in hand</option>
                                <option value="bank" @selected(old('received_in') === 'bank')>Bank</option>
                            </select>
                            @error('received_in') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="received_on" class="form-label">Date</label>
                            <input type="date" id="received_on" name="received_on"
                                   value="{{ old('received_on', now()->toDateString()) }}"
                                   max="{{ now()->toDateString() }}"
                                   class="form-control @error('received_on') is-invalid @enderror" required>
                            @error('received_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="income_narration" class="form-label">Remark</label>
                            <input type="text" id="income_narration" name="income_narration"
                                   value="{{ old('income_narration') }}"
                                   class="form-control @error('income_narration') is-invalid @enderror"
                                   placeholder="e.g. Partner investment, amount from Ramesh" required>
                            @error('income_narration') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2 me-1"></i>Save income
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @unless ($store->openingBooksAreSet())
        <div class="card gs-stat-card mb-3">
            <div class="card-header bg-white fw-semibold">Correct the books</div>
            <div class="card-body">
                <form method="POST" action="{{ route('books.update') }}" class="row g-3 align-items-end">
                    @csrf
                    @method('PUT')

                    <div class="col-md-3">
                        <label for="opening_capital" class="form-label">Capital</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0" id="opening_capital" name="opening_capital"
                                   value="{{ old('opening_capital', $store->opening_capital) }}"
                                   class="form-control @error('opening_capital') is-invalid @enderror" required>
                            @error('opening_capital') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="cash_in_hand" class="form-label">Cash in hand</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0" id="cash_in_hand" name="cash_in_hand"
                                   value="{{ old('cash_in_hand', $store->cash_in_hand) }}"
                                   class="form-control @error('cash_in_hand') is-invalid @enderror" required>
                            @error('cash_in_hand') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="bank_balance" class="form-label">Bank</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0" id="bank_balance" name="bank_balance"
                                   value="{{ old('bank_balance', $store->bank_balance) }}"
                                   class="form-control @error('bank_balance') is-invalid @enderror" required>
                            @error('bank_balance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary">Save books</button>
                    </div>
                </form>
                <p class="form-text mb-0 mt-2">This can only be saved once. After that, cash and bank move through income, expenses and girvi.</p>
            </div>
        </div>
    @endunless

    <div class="card gs-stat-card">
        <div class="card-header bg-white fw-semibold">Recent entries</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Remark</th>
                    <th>Type</th>
                    <th>Wallet</th>
                    <th class="text-end">Amount</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="small">{{ $entry->on->format('d M Y') }}</td>
                        <td>{{ $entry->narration }}</td>
                        <td class="small">
                            @if ($entry->direction === 'in')
                                <span class="text-success">{{ $entry->kind === 'investment' ? 'Investment' : 'Income' }}</span>
                            @else
                                <span class="text-danger">Expense</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $entry->wallet === 'bank' ? 'Bank' : 'Cash' }}</td>
                        <td class="text-end {{ $entry->direction === 'in' ? 'text-success' : 'text-danger' }}">
                            {{ $entry->direction === 'in' ? '+' : '−' }}{{ money($entry->amount) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No income or expenses recorded yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
