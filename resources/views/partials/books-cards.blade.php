@php
    $books = $books ?? ['capital' => 0, 'cash' => 0, 'bank' => 0, 'expenses' => 0];
@endphp

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Capital', 'value' => money($books['capital']), 'icon' => 'piggy-bank', 'tone' => '', 'hint' => 'Started with'],
        ['label' => 'Cash in hand', 'value' => money($books['cash']), 'icon' => 'cash', 'tone' => '', 'hint' => 'Till'],
        ['label' => 'Bank', 'value' => money($books['bank']), 'icon' => 'bank', 'tone' => '', 'hint' => 'In the account'],
        ['label' => 'Expenses', 'value' => money($books['expenses']), 'icon' => 'receipt', 'tone' => $books['expenses'] > 0 ? 'text-danger' : '', 'hint' => 'Paid out'],
    ] as $card)
        <div class="col-6 col-md-3">
            <div class="card gs-stat-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <i class="bi bi-{{ $card['icon'] }} text-muted"></i>
                    </div>
                    <div class="h4 mb-0 mt-2 {{ $card['tone'] }}">{{ $card['value'] }}</div>
                    <div class="small text-muted mt-1">{{ $card['hint'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@can('manageStaff', App\Models\User::class)
    @unless (request()->routeIs('books.*'))
        <div class="d-flex justify-content-end mb-4 mt-n3">
            <a href="{{ route('books.index') }}" class="small">Record expense / correct books</a>
        </div>
    @endunless
@endcan
