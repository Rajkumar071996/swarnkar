<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
        <tr>
            <th>Metal</th>
            <th>Item</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Gross</th>
            <th class="text-end">Less</th>
            <th class="text-end">Net</th>
            <th class="text-end">Wt %</th>
            <th class="text-end">Fine</th>
            <th class="text-end">Rate</th>
            <th class="text-end">Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($loan->items as $item)
            <tr>
                <td>{{ config('girvi.metal_types')[$item->metal_type] ?? Str::headline($item->metal_type) }}</td>
                <td>{{ $item->item_type }}</td>
                <td class="text-end">{{ $item->quantity }}</td>
                <td class="text-end">{{ number_format((float) $item->gross_weight_grams, 3) }}</td>
                <td class="text-end">{{ number_format((float) $item->less_weight_grams, 3) }}</td>
                <td class="text-end">{{ number_format((float) $item->net_weight_grams, 3) }}</td>
                <td class="text-end">{{ number_format((float) $item->weight_percent, 2) }}</td>
                <td class="text-end">{{ number_format((float) $item->fine_weight_grams, 3) }}</td>
                <td class="text-end">{{ money($item->rate_per_gram) }}</td>
                <td class="text-end fw-semibold">{{ money($item->total_amount) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot class="table-light">
        <tr>
            <th colspan="5" class="text-end">Totals</th>
            <th class="text-end">{{ number_format((float) $loan->net_weight_grams, 3) }}</th>
            <th></th>
            <th class="text-end">{{ number_format((float) $loan->fine_weight_grams, 3) }}</th>
            <th></th>
            <th class="text-end">{{ money($loan->total_value) }}</th>
        </tr>
        </tfoot>
    </table>
</div>
