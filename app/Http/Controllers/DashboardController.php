<?php

namespace App\Http\Controllers;

use App\Enums\RiskBand;
use App\Models\Customer;
use App\Models\KhataAdvance;
use App\Models\Udhaar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = Carbon::today();
        $storeId = $request->user()->store_id;

        $dueSoon = Udhaar::query()
            ->outstanding()
            ->whereDate('due_on', '>=', $today)
            ->whereDate('due_on', '<=', $today->copy()->addDays(7))
            ->with('customer')
            ->orderBy('due_on')
            ->limit(10)
            ->get();

        $overdueUdhaars = Udhaar::query()
            ->overdue()
            ->with('customer')
            ->orderBy('due_on')
            ->limit(10)
            ->get();

        $advanceHeld = KhataAdvance::query()
            ->where('store_id', $storeId)
            ->where('balance', '>', 0)
            ->with('customer')
            ->orderByDesc('balance')
            ->limit(10)
            ->get();

        return view('dashboard', [
            'stats' => [
                'outstanding' => (float) Udhaar::query()->outstanding()
                    ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
                'overdue' => (float) Udhaar::query()->overdue()
                    ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
                'due_this_week' => (float) Udhaar::query()->outstanding()
                    ->whereDate('due_on', '>=', $today)
                    ->whereDate('due_on', '<=', $today->copy()->addDays(7))
                    ->selectRaw('COALESCE(SUM(principal_amount - amount_paid), 0) AS total')->value('total'),
                'open_khatas' => Udhaar::query()->outstanding()->distinct()->count('customer_id'),
                'advance_held' => (float) KhataAdvance::query()
                    ->where('store_id', $storeId)
                    ->sum('balance'),
                'advance_customers' => KhataAdvance::query()
                    ->where('store_id', $storeId)
                    ->where('balance', '>', 0)
                    ->count(),
                'customers' => Customer::query()
                    ->whereHas('stores', fn ($q) => $q->whereKey($storeId))
                    ->count(),
            ],
            'dueSoon' => $dueSoon,
            'overdueUdhaars' => $overdueUdhaars,
            'advanceHeld' => $advanceHeld,
            'riskMix' => $this->riskMix($storeId),
        ]);
    }

    /**
     * How this store's own customer book splits across the risk bands.
     *
     * @return array<string, int>
     */
    private function riskMix(int $storeId): array
    {
        $counts = Customer::query()
            ->whereHas('stores', fn ($q) => $q->whereKey($storeId))
            ->with('latestScore')
            ->get()
            ->countBy(fn (Customer $c) => ($c->latestScore?->band ?? RiskBand::Unscored)->value);

        return collect(RiskBand::cases())
            ->mapWithKeys(fn (RiskBand $band) => [$band->value => (int) $counts->get($band->value, 0)])
            ->all();
    }
}
