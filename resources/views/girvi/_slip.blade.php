<div class="gs-slip">
    <div class="gs-slip-copy">{{ $copy }}</div>
    <div class="gs-slip-header">
        <div class="gs-slip-shop">{{ $store->name }}</div>
        <div>{{ $store->fullAddress() }}</div>
        <div class="gs-slip-meta">
            <span>No. {{ $loan->receipt_no }}</span>
            @if ($store->phone)
                <span>PH No : {{ $store->phone }}</span>
            @endif
        </div>
    </div>

    <ol class="gs-slip-fields">
        <li><span>Customer Name</span> : {{ $loan->customer->full_name }}</li>
        <li><span>Address</span> : {{ $loan->customer->fullAddress() ?: '--' }}</li>
        <li><span>Loan Type - ornament/without ornament</span> : {{ $loan->loan_type ?: 'Ornaments' }}</li>
        <li><span>Loan Amount Rs.</span> : {{ number_format((float) $loan->principal_amount, 0, '.', '') }}</li>
        <li><span>Loan Date</span> : {{ $loan->disbursed_on->format('d/m/Y') }}</li>
        <li><span>Valid For</span> : {{ $loan->due_on->format('d/m/Y') }}</li>
        <li><span>Loan Interest rate</span> : {{ number_format($loan->monthlyInterestRate(), 2) }}% per month</li>
        <li>
            <span>Ornament Description</span> :
            <table class="gs-slip-items">
                <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-end">Total weight</th>
                    <th class="text-end">Net. wt</th>
                    <th class="text-end">Quantity</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($loan->items as $item)
                    <tr>
                        <td>{{ Str::headline($item->metal_type) }}-{{ $item->item_type }}</td>
                        <td class="text-end">{{ number_format((float) $item->gross_weight_grams, 3) }}</td>
                        <td class="text-end">{{ number_format((float) $item->net_weight_grams, 3) }}</td>
                        <td class="text-end">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="gs-slip-totals">
                <div>Total weight : {{ number_format((float) $loan->gross_weight_grams, 3) }}</div>
                <div>Predicted Value : {{ number_format((float) $loan->total_value, 0, '.', '') }}</div>
            </div>
        </li>
        <li><span>Document Description, If any</span> : {{ $loan->packet_no ?: '' }}</li>
        <li><span>Purpose of Loan</span> : {{ $loan->loan_reason ?: '' }}</li>
        <li><span>Disclaimer</span> : we will not take any responsibility if the receipt is lost</li>
    </ol>

    <div class="gs-slip-signs">
        <div>Customer sign</div>
        <div>Lender sign</div>
    </div>
</div>
