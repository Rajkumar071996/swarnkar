@extends('layouts.app')

@section('title', 'New Girvi')
@section('heading', 'New Girvi')
@section('subheading', 'Take jewellery in against cash and print the receipt.')

@section('content')
    <form method="POST" action="{{ route('girvi.loans.store') }}" id="girviForm">
        @csrf

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card gs-stat-card h-100">
                    <div class="card-header bg-white fw-semibold">Customer</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="customerFilter" class="form-label">Find by ledger no, name or mobile</label>
                            <input type="search" id="customerFilter" class="form-control mb-2"
                                   placeholder="171, Ramesh, 98765...">
                            <select id="customer_id" name="customer_id" size="6"
                                    class="form-select @error('customer_id') is-invalid @enderror" required>
                                <option value="">Select a customer</option>
                                @foreach ($customers as $row)
                                    <option value="{{ $row->id }}"
                                            data-search="{{ Str::lower($row->ledger_no.' '.$row->full_name.' '.$row->mobile) }}"
                                            @selected((int) old('customer_id', $selectedCustomer) === $row->id)>
                                        @if ($row->ledger_no) [{{ $row->ledger_no }}] @endif
                                        {{ $row->full_name }} ({{ $row->maskedMobile() }})
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Not on the list yet?
                                <a href="{{ route('customers.create') }}">Add the customer first</a>.
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label for="invoice_no" class="form-label">Invoice No</label>
                                <input type="text" id="invoice_no" name="invoice_no" value="{{ old('invoice_no') }}"
                                       class="form-control @error('invoice_no') is-invalid @enderror">
                                @error('invoice_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label">Receipt No</label>
                                <input type="text" class="form-control" value="Generated on save" disabled>
                            </div>
                            <div class="col-6">
                                <label for="packet_no" class="form-label">Packet No</label>
                                <input type="text" id="packet_no" name="packet_no" value="{{ old('packet_no') }}"
                                       class="form-control @error('packet_no') is-invalid @enderror">
                                @error('packet_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6">
                                <label for="barcode" class="form-label">Barcode</label>
                                <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}"
                                       class="form-control @error('barcode') is-invalid @enderror">
                                @error('barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card gs-stat-card h-100">
                    <div class="card-header bg-white fw-semibold">Loan details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label for="disbursed_on" class="form-label">Date of Deposit</label>
                                <input type="date" id="disbursed_on" name="disbursed_on"
                                       value="{{ old('disbursed_on', now()->toDateString()) }}"
                                       max="{{ now()->toDateString() }}"
                                       class="form-control @error('disbursed_on') is-invalid @enderror" required>
                                @error('disbursed_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="duration_months" class="form-label">Duration</label>
                                <div class="input-group">
                                    <input type="number" id="duration_months" name="duration_months" min="1" max="120"
                                           value="{{ old('duration_months', config('girvi.duration_months')) }}"
                                           class="form-control @error('duration_months') is-invalid @enderror" required>
                                    <span class="input-group-text">mo</span>
                                </div>
                                @error('duration_months') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Maturity</label>
                                <input type="text" class="form-control" id="maturityOn" disabled>
                            </div>

                            <div class="col-6">
                                <label for="loan_reason" class="form-label">Loan reason</label>
                                <select id="loan_reason" name="loan_reason" class="form-select">
                                    @foreach (config('girvi.loan_reasons') as $reason)
                                        <option value="{{ $reason }}" @selected(old('loan_reason') === $reason)>
                                            {{ $reason }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="loan_type" class="form-label">Loan type</label>
                                <select id="loan_type" name="loan_type" class="form-select">
                                    @foreach (config('girvi.loan_types') as $type)
                                        <option value="{{ $type }}" @selected(old('loan_type') === $type)>
                                            {{ $type }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="interest_rate" class="form-label">Interest in % (per month)</label>
                                <input type="number" step="0.01" min="0" id="interest_rate" name="interest_rate"
                                       value="{{ old('interest_rate', number_format(config('girvi.interest_rate') / 12, 2, '.', '')) }}"
                                       class="form-control @error('interest_rate') is-invalid @enderror" required>
                                @error('interest_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <div class="border rounded p-2 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-semibold">Today's rate per gram</span>
                                        @can('create', App\Models\GoldLoan::class)
                                            <a href="{{ route('girvi.settings.edit') }}" class="small">Change</a>
                                        @endcan
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label for="goldRate" class="form-label small mb-1">Gold</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" id="goldRate"
                                                       value="{{ $rates['gold'] }}" class="form-control js-metal-rate"
                                                       data-metal="gold">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label for="silverRate" class="form-label small mb-1">Silver</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" step="0.01" min="0" id="silverRate"
                                                       value="{{ $rates['silver'] }}" class="form-control js-metal-rate"
                                                       data-metal="silver">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text mt-1">
                                        Filled into each item row from its metal. Editing here only affects this entry.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card gs-stat-card mt-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Item detail</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem">
                    <i class="bi bi-plus-lg me-1"></i>Add item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="itemsTable">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width: 110px;">Metal</th>
                        <th style="min-width: 130px;">Item</th>
                        <th style="min-width: 70px;">Qty</th>
                        <th style="min-width: 100px;">Gross Wt</th>
                        <th style="min-width: 100px;">Less Wt</th>
                        <th style="min-width: 100px;">Net Wt</th>
                        <th style="min-width: 90px;">Wt in %</th>
                        <th style="min-width: 100px;">Fine Wt</th>
                        <th style="min-width: 110px;">Rate</th>
                        <th style="min-width: 120px;">Amount</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                    <tfoot class="table-light">
                    <tr>
                        <th colspan="5" class="text-end">Totals</th>
                        <th id="totalNet">0.000</th>
                        <th></th>
                        <th id="totalFine">0.000</th>
                        <th></th>
                        <th id="totalAmount">0.00</th>
                        <th></th>
                    </tr>
                    </tfoot>
                </table>
            </div>
            @error('items') <div class="text-danger small px-3 py-2">{{ $message }}</div> @enderror
            @error('principal_amount') <div class="text-danger small px-3 py-2">{{ $message }}</div> @enderror
        </div>

        <div class="card gs-stat-card mt-3">
            <div class="card-header bg-white fw-semibold">Payment</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Final Amount</label>
                        <input type="text" class="form-control" id="finalAmount" disabled>
                    </div>
                    <div class="col-md-3">
                        <label for="estimate_percent" class="form-label">Estimate in %</label>
                        <input type="number" step="0.01" min="1" max="100" id="estimate_percent" name="estimate_percent"
                               value="{{ old('estimate_percent', config('girvi.estimate_percent')) }}"
                               class="form-control @error('estimate_percent') is-invalid @enderror" required>
                        @error('estimate_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estimate Amount</label>
                        <input type="text" class="form-control" id="estimateAmount" disabled>
                    </div>
                    <div class="col-md-3">
                        <label for="principal_amount" class="form-label">Loan Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="1" id="principal_amount" name="principal_amount"
                                   value="{{ old('principal_amount') }}"
                                   class="form-control @error('principal_amount') is-invalid @enderror" required>
                        </div>
                        <div class="form-text" id="loanHint"></div>
                    </div>
                    <div class="col-md-3">
                        <label for="paid_from" class="form-label">Paid from</label>
                        <select id="paid_from" name="paid_from"
                                class="form-select @error('paid_from') is-invalid @enderror" required>
                            <option value="cash" @selected(old('paid_from', 'cash') === 'cash')>Cash in hand</option>
                            <option value="bank" @selected(old('paid_from') === 'bank')>Bank</option>
                        </select>
                        @error('paid_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="refer_by" class="form-label">Refer By</label>
                        <input type="text" id="refer_by" name="refer_by" value="{{ old('refer_by') }}"
                               class="form-control">
                    </div>
                    <div class="col-md-8">
                        <label for="narration" class="form-label">Narration</label>
                        <input type="text" id="narration" name="narration" value="{{ old('narration') }}"
                               class="form-control">
                    </div>
                </div>

                <div class="mt-4 d-flex flex-column flex-sm-row gap-2 gs-form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-safe me-1"></i>Save girvi
                    </button>
                    <a href="{{ route('girvi.loans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>

    <template id="itemRowTemplate">
        <tr class="gs-item-row">
            <td>
                <select name="items[__INDEX__][metal_type]" class="form-select form-select-sm js-metal">
                    @foreach (config('girvi.metal_types') as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="items[__INDEX__][item_type]" class="form-select form-select-sm" required>
                    @foreach (config('girvi.item_types') as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="number" name="items[__INDEX__][quantity]" value="1" min="1"
                       class="form-control form-control-sm" required></td>
            <td><input type="number" step="0.001" min="0.001" name="items[__INDEX__][gross_weight_grams]"
                       class="form-control form-control-sm js-gross" required></td>
            <td><input type="number" step="0.001" min="0" name="items[__INDEX__][less_weight_grams]" value="0"
                       class="form-control form-control-sm js-less"></td>
            <td><input type="text" class="form-control form-control-sm js-net bg-light" value="0.000" readonly></td>
            <td><input type="number" step="0.01" min="1" max="100" name="items[__INDEX__][weight_percent]" value="100"
                       class="form-control form-control-sm js-percent" required></td>
            <td><input type="text" class="form-control form-control-sm js-fine bg-light" value="0.000" readonly></td>
            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][rate_per_gram]"
                       class="form-control form-control-sm js-rate" required></td>
            <td><input type="text" class="form-control form-control-sm js-amount bg-light" value="0.00" readonly></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger js-remove" aria-label="Remove item">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>
    </template>

    <script>
        (function () {
            const body = document.getElementById('itemsBody');
            const template = document.getElementById('itemRowTemplate');
            const estimatePercent = document.getElementById('estimate_percent');
            const principal = document.getElementById('principal_amount');
            const duration = document.getElementById('duration_months');
            const depositedOn = document.getElementById('disbursed_on');
            let index = 0;

            const round = (value, places) => Number.isFinite(value) ? value.toFixed(places) : (0).toFixed(places);
            const num = (input) => parseFloat(input.value) || 0;

            const metalRates = {};
            document.querySelectorAll('.js-metal-rate').forEach((input) => {
                metalRates[input.dataset.metal] = input;
            });

            /**
             * Rows take today's rate for whichever metal they are, unless the
             * counter has typed over it for this one item.
             */
            function applyMetalRate(row) {
                const rate = row.querySelector('.js-rate');

                if (rate.dataset.touched === 'true') {
                    return;
                }

                const source = metalRates[row.querySelector('.js-metal').value];
                rate.value = source ? source.value : '';
            }

            function recalcRow(row) {
                const net = Math.max(0, num(row.querySelector('.js-gross')) - num(row.querySelector('.js-less')));
                const fine = net * num(row.querySelector('.js-percent')) / 100;
                const amount = fine * num(row.querySelector('.js-rate'));

                row.querySelector('.js-net').value = round(net, 3);
                row.querySelector('.js-fine').value = round(fine, 3);
                row.querySelector('.js-amount').value = round(amount, 2);
            }

            function recalcTotals() {
                let net = 0, fine = 0, amount = 0;

                body.querySelectorAll('.gs-item-row').forEach((row) => {
                    recalcRow(row);
                    net += parseFloat(row.querySelector('.js-net').value) || 0;
                    fine += parseFloat(row.querySelector('.js-fine').value) || 0;
                    amount += parseFloat(row.querySelector('.js-amount').value) || 0;
                });

                document.getElementById('totalNet').textContent = round(net, 3);
                document.getElementById('totalFine').textContent = round(fine, 3);
                document.getElementById('totalAmount').textContent = round(amount, 2);
                document.getElementById('finalAmount').value = round(amount, 2);

                const estimate = amount * (parseFloat(estimatePercent.value) || 0) / 100;
                document.getElementById('estimateAmount').value = round(estimate, 2);
                principal.max = round(estimate, 2);
                document.getElementById('loanHint').textContent =
                    'Maximum ₹' + round(estimate, 2) + ' at ' + (estimatePercent.value || 0) + '%';
            }

            function recalcMaturity() {
                const start = depositedOn.value ? new Date(depositedOn.value) : null;
                const months = parseInt(duration.value, 10);

                if (!start || Number.isNaN(months)) {
                    document.getElementById('maturityOn').value = '';
                    return;
                }

                const end = new Date(start);
                end.setMonth(end.getMonth() + months);
                document.getElementById('maturityOn').value = end.toLocaleDateString('en-IN');
            }

            function addRow() {
                const markup = template.innerHTML.replaceAll('__INDEX__', index++);
                const holder = document.createElement('tbody');
                holder.innerHTML = markup.trim();
                const row = holder.firstElementChild;

                const rate = row.querySelector('.js-rate');

                rate.addEventListener('input', () => {
                    rate.dataset.touched = 'true';
                });

                // Switching metal always re-prices the row, even if the rate
                // was typed over for the metal it used to be.
                row.querySelector('.js-metal').addEventListener('change', () => {
                    delete rate.dataset.touched;
                    applyMetalRate(row);
                    recalcTotals();
                });

                row.querySelector('.js-remove').addEventListener('click', () => {
                    row.remove();
                    recalcTotals();
                });
                row.addEventListener('input', recalcTotals);

                applyMetalRate(row);
                body.appendChild(row);
                recalcTotals();
            }

            document.getElementById('customerFilter').addEventListener('input', (event) => {
                const needle = event.target.value.trim().toLowerCase();
                const select = document.getElementById('customer_id');

                select.querySelectorAll('option').forEach((option) => {
                    if (option.value === '') {
                        return;
                    }

                    const hide = needle !== '' && !(option.dataset.search || '').includes(needle);
                    option.hidden = hide;
                    option.disabled = hide;
                });
            });

            Object.values(metalRates).forEach((input) => {
                input.addEventListener('input', () => {
                    body.querySelectorAll('.gs-item-row').forEach(applyMetalRate);
                    recalcTotals();
                });
            });

            document.getElementById('addItem').addEventListener('click', addRow);
            estimatePercent.addEventListener('input', recalcTotals);
            duration.addEventListener('input', recalcMaturity);
            depositedOn.addEventListener('change', recalcMaturity);

            addRow();
            recalcMaturity();
        })();
    </script>
@endsection
