<?php

namespace App\Http\Controllers;

use App\Enums\DefaultFlagReason;
use App\Enums\DefaultFlagStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DefaultFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DefaultFlagController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('create', DefaultFlag::class);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(DefaultFlagReason::class)],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
            'amount_involved' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'narrative' => ['required', 'string', 'max:2000'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        DefaultFlag::create([
            ...$data,
            'customer_id' => $customer->id,
            'reported_by_user_id' => $request->user()->id,
            // Reports start unverified. Damaging someone's standing across the
            // network on one merchant's say-so is exactly what the audit trail
            // in the PRD exists to prevent.
            'status' => DefaultFlagStatus::Pending,
            'evidence_path' => $request->file('evidence')->store('evidence', 'local'),
        ]);

        AuditLog::record('flag.reported', $customer, ['reason' => $data['reason']]);

        return back()->with(
            'success',
            'Report submitted for review. It will affect the network score once verified.'
        );
    }
}
