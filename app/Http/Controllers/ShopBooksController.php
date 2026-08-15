<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StoreExpense;
use App\Models\StoreIncome;
use App\Models\User;
use App\Services\StoreBooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The shop's capital, till, bank, income and expenses. Opening figures are
 * captured at signup; this screen records money that later comes in or goes out.
 */
class ShopBooksController extends Controller
{
    public function __construct(private readonly StoreBooks $books) {}

    public function index(Request $request): View
    {
        $this->authorize('manageStaff', User::class);

        $store = $request->user()->store;

        $expenses = StoreExpense::query()
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $incomes = StoreIncome::query()
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $entries = $incomes->map(fn (StoreIncome $row) => (object) [
            'on' => $row->received_on,
            'id' => $row->id,
            'direction' => 'in',
            'kind' => $row->kind,
            'narration' => $row->narration,
            'wallet' => $row->received_in,
            'amount' => $row->amount,
        ])->concat($expenses->map(fn (StoreExpense $row) => (object) [
            'on' => $row->paid_on,
            'id' => $row->id,
            'direction' => 'out',
            'kind' => 'expense',
            'narration' => $row->narration,
            'wallet' => $row->paid_from,
            'amount' => $row->amount,
        ]))->sortByDesc(fn ($row) => $row->on->format('Y-m-d').$row->direction.$row->id)
            ->take(40)
            ->values();

        return view('books.index', [
            'books' => $this->books->snapshot($store),
            'store' => $store,
            'entries' => $entries,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $data = $request->validate([
            'opening_capital' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'cash_in_hand' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'bank_balance' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $store = $request->user()->store;
        $store->forceFill([
            'opening_capital' => round((float) $data['opening_capital'], 2),
            'cash_in_hand' => round((float) $data['cash_in_hand'], 2),
            'bank_balance' => round((float) $data['bank_balance'], 2),
        ])->save();

        AuditLog::record('books.updated', $store, [
            'capital' => $store->opening_capital,
            'cash' => $store->cash_in_hand,
            'bank' => $store->bank_balance,
        ]);

        return back()->with('success', 'Shop books updated.');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'paid_from' => ['required', 'in:cash,bank'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'narration' => ['required', 'string', 'max:255'],
        ]);

        $expense = $this->books->recordExpense(
            $request->user()->store,
            (float) $data['amount'],
            $data['paid_from'],
            Carbon::parse($data['paid_on']),
            $data['narration'],
            $request->user(),
        );

        AuditLog::record('books.expense', $expense, [
            'amount' => $expense->amount,
            'paid_from' => $expense->paid_from,
        ]);

        return back()->with('success', money($expense->amount).' expense recorded.');
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $data = $request->validate([
            'income_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'received_in' => ['required', 'in:cash,bank'],
            'kind' => ['required', 'in:income,investment'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'income_narration' => ['required', 'string', 'max:255'],
        ]);

        $income = $this->books->recordIncome(
            $request->user()->store,
            (float) $data['income_amount'],
            $data['received_in'],
            $data['kind'],
            Carbon::parse($data['received_on']),
            $data['income_narration'],
            $request->user(),
        );

        AuditLog::record('books.income', $income, [
            'amount' => $income->amount,
            'kind' => $income->kind,
            'received_in' => $income->received_in,
        ]);

        $label = $income->isInvestment() ? 'investment' : 'income';

        return back()->with('success', money($income->amount).' '.$label.' recorded.');
    }
}
