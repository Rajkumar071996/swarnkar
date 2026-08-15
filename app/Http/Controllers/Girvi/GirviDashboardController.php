<?php

namespace App\Http\Controllers\Girvi;

use App\Http\Controllers\Controller;
use App\Models\GoldLoan;
use App\Models\GoldLoanItem;
use App\Models\GoldLoanPayment;
use App\Services\StoreBooks;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class GirviDashboardController extends Controller
{
    public function __construct(private readonly StoreBooks $books) {}

    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', GoldLoan::class);

        $today = Carbon::today();

        $unreleased = GoldLoan::query()->unreleased();

        $dueSoon = GoldLoan::query()
            ->unreleased()
            ->whereDate('due_on', '>=', $today)
            ->whereDate('due_on', '<=', $today->copy()->addDays(30))
            ->with('customer')
            ->orderBy('due_on')
            ->limit(10)
            ->get();

        $overdue = GoldLoan::query()
            ->overdue()
            ->with('customer')
            ->orderBy('due_on')
            ->limit(10)
            ->get();

        return view('girvi.dashboard', [
            'stats' => [
                'money_out' => (float) (clone $unreleased)
                    ->selectRaw('COALESCE(SUM(principal_amount - principal_repaid), 0) AS total')
                    ->value('total'),
                'pledges' => (clone $unreleased)->count(),
                'overdue' => GoldLoan::query()->overdue()->count(),
                'held' => GoldLoanItem::fineWeightHeld(),
                'interest_this_month' => (float) GoldLoanPayment::query()
                    ->where('type', 'interest')
                    ->whereBetween('paid_on', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->whereIn('gold_loan_id', GoldLoan::query()->select('id'))
                    ->sum('amount'),
                'released' => GoldLoan::query()->released()->count(),
            ],
            'dueSoon' => $dueSoon,
            'overdue' => $overdue,
            'books' => $this->books->snapshot($request->user()->store),
        ]);
    }
}
