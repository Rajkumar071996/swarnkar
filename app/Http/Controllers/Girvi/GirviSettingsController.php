<?php

namespace App\Http\Controllers\Girvi;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GoldLoan;
use App\Services\Girvi\MetalRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GirviSettingsController extends Controller
{
    public function __construct(private readonly MetalRates $rates) {}

    public function edit(Request $request): View
    {
        $this->authorize('create', GoldLoan::class);

        $storeId = $request->user()->store_id;

        return view('girvi.settings', [
            'rates' => $this->rates->current($storeId),
            'history' => $this->rates->history($storeId),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('create', GoldLoan::class);

        $data = $request->validate([
            'gold' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'silver' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ], [], [
            'gold' => 'gold rate',
            'silver' => 'silver rate',
        ]);

        foreach (['gold', 'silver'] as $metal) {
            $this->rates->set($request->user()->store_id, $metal, (float) $data[$metal], $request->user());
        }

        AuditLog::record('girvi.rates_updated', $request->user()->store, [
            'gold' => (float) $data['gold'],
            'silver' => (float) $data['silver'],
        ]);

        return back()->with('success', "Today's rates saved. New girvi entries will use them.");
    }
}
