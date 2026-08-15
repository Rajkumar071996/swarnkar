@php
    $books = $books ?? ['capital' => 0, 'cash' => 0, 'bank' => 0, 'income' => 0, 'expenses' => 0, 'profit' => 0];
    $profit = (float) ($books['profit'] ?? 0);
    $isLoss = $profit < 0;
@endphp

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Capital', 'value' => money($books['capital']), 'icon' => 'piggy-bank', 'tone' => '', 'hint' => 'Started with'],
        ['label' => 'Cash in hand', 'value' => money($books['cash']), 'icon' => 'cash', 'tone' => '', 'hint' => 'Till'],
        ['label' => 'Bank', 'value' => money($books['bank']), 'icon' => 'bank', 'tone' => '', 'hint' => 'In the account'],
        ['label' => 'Income', 'value' => money($books['income']), 'icon' => 'arrow-down-circle', 'tone' => $books['income'] > 0 ? 'text-success' : '', 'hint' => 'Came in'],
        ['label' => 'Expenses', 'value' => money($books['expenses']), 'icon' => 'receipt', 'tone' => $books['expenses'] > 0 ? 'text-danger' : '', 'hint' => 'Paid out'],
        [
            'label' => $isLoss ? 'Loss' : 'Profit',
            'value' => money(abs($profit)),
            'icon' => $isLoss ? 'graph-down-arrow' : 'graph-up-arrow',
            'tone' => $isLoss ? 'text-danger' : ($profit > 0 ? 'text-success' : ''),
            'hint' => $isLoss ? 'Expenses ahead' : 'Income − expenses',
        ],
    ] as $card)
        <div class="col-6 col-sm-4 col-xl">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <i class="bi bi-{{ $card['icon'] }} text-muted flex-shrink-0"></i>
                    </div>
                    <div class="h4 gs-stat-value mb-0 mt-2 {{ $card['tone'] }}">{{ $card['value'] }}</div>
                    <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@can('manageStaff', App\Models\User::class)
    @unless (request()->routeIs('books.*'))
        <div class="d-flex justify-content-end mb-4 mt-n3">
            <a href="{{ route('books.index') }}" class="small">Record income / expense</a>
        </div>
    @endunless
@endcan
