<div class="gs-slip">
    <div class="gs-slip-copy">{{ $copy }}</div>
    <div class="gs-slip-header">
        <div class="gs-slip-shop">{{ $store->name }}</div>
        <div>{{ $store->fullAddress() }}</div>
        <div>Release Receipt</div>
        <div class="gs-slip-meta">
            <span>No. {{ $settlement?->receipt_no ?? '--' }}</span>
            @if ($store->phone)
                <span>PH No : {{ $store->phone }}</span>
            @endif
        </div>
    </div>

    <ol class="gs-slip-fields">
        <li><span>Customer Name</span> : {{ $loan->customer->full_name }}</li>
        <li><span>Address</span> : {{ $loan->customer->fullAddress() ?: '--' }}</li>
        <li><span>Girvi receipt</span> : {{ $loan->receipt_no }}</li>
        <li><span>Mortgage No</span> : {{ $loan->loan_no }}</li>
        <li><span>Loan Amount Rs.</span> : {{ number_format((float) $loan->principal_amount, 0, '.', '') }}</li>
        <li><span>Loan Date</span> : {{ $loan->disbursed_on->format('d/m/Y') }}</li>
        <li><span>Released On</span> : {{ $loan->released_on?->format('d/m/Y') ?? '--' }}</li>
        <li><span>Interest collected Rs.</span> : {{ number_format((float) $loan->interest_collected, 0, '.', '') }}</li>
        @if ((float) $loan->extra_amount > 0)
            <li><span>Extra amount Rs.</span> : {{ number_format((float) $loan->extra_amount, 0, '.', '') }}</li>
        @endif
        @if ((float) $loan->notice_charge > 0)
            <li><span>Notice charge Rs.</span> : {{ number_format((float) $loan->notice_charge, 0, '.', '') }}</li>
        @endif
        @if ((float) $loan->discount > 0)
            <li><span>Discount Rs.</span> : {{ number_format((float) $loan->discount, 0, '.', '') }}</li>
        @endif
        <li><span>Total collected Rs.</span> : {{ number_format((float) ($settlement?->amount ?? 0), 0, '.', '') }}</li>
        <li>
            <span>Ornament returned</span> :
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
            </div>
        </li>
        <li><span>Disclaimer</span> : jewellery returned in full and this account is closed</li>
    </ol>

    @include('girvi._sign-block', ['signatureUri' => $loan->customer->signatureDataUri()])
</div>
