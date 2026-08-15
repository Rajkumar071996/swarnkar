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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Capital, till, bank, income and expenses for whichever product the
 * jeweller is working in — GoldScore or Girvi — never both mixed.
 */
class ShopBooksController extends Controller
{
    public function __construct(private readonly StoreBooks $books) {}

    public function index(Request $request): View
    {
        $this->authorize('manageStaff', User::class);

        $store = $request->user()->store;
        $module = $this->module($request);
        $columns = $this->books->columns($module);

        $expenses = StoreExpense::query()
            ->where('module', $module)
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $incomes = StoreIncome::query()
            ->where('module', $module)
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
            'books' => $this->books->snapshot($store, $module),
            'store' => $store,
            'module' => $module,
            'opening' => [
                'capital' => $store->{$columns['capital']},
                'cash' => $store->{$columns['cash']},
                'bank' => $store->{$columns['bank']},
            ],
            'booksAreSet' => $store->openingBooksAreSet($module),
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
        $module = $this->module($request);
        $columns = $this->books->columns($module);

        if ($store->openingBooksAreSet($module)) {
            throw ValidationException::withMessages([
                'opening_capital' => 'Opening books can only be set once.',
            ]);
        }

        $store->forceFill([
            $columns['capital'] => round((float) $data['opening_capital'], 2),
            $columns['cash'] => round((float) $data['cash_in_hand'], 2),
            $columns['bank'] => round((float) $data['bank_balance'], 2),
            $columns['set_at'] => now(),
        ])->save();

        AuditLog::record('books.updated', $store, [
            'module' => $module,
            'capital' => $store->{$columns['capital']},
            'cash' => $store->{$columns['cash']},
            'bank' => $store->{$columns['bank']},
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

        $module = $this->module($request);

        $expense = $this->books->recordExpense(
            $request->user()->store,
            (float) $data['amount'],
            $data['paid_from'],
            Carbon::parse($data['paid_on']),
            $data['narration'],
            $request->user(),
            $module,
        );

        AuditLog::record('books.expense', $expense, [
            'module' => $module,
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

        $module = $this->module($request);

        $income = $this->books->recordIncome(
            $request->user()->store,
            (float) $data['income_amount'],
            $data['received_in'],
            $data['kind'],
            Carbon::parse($data['received_on']),
            $data['income_narration'],
            $request->user(),
            $module,
        );

        AuditLog::record('books.income', $income, [
            'module' => $module,
            'amount' => $income->amount,
            'kind' => $income->kind,
            'received_in' => $income->received_in,
        ]);

        $label = $income->isInvestment() ? 'investment' : 'income';

        return back()->with('success', money($income->amount).' '.$label.' recorded.');
    }

    private function module(Request $request): string
    {
        return $this->books->resolveModule($request->session()->get('active_module'));
    }
}
