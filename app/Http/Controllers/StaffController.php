<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $staff = User::where('store_id', $request->user()->store_id)
            ->orderBy('name')
            ->get();

        return view('staff.index', compact('staff'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('staff.create', ['roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([...$data, 'store_id' => $request->user()->store_id]);
        AuditLog::record('staff.created', $user, ['role' => $user->role->value]);

        return redirect()->route('staff.index')->with('success', $user->name.' can now sign in.');
    }

    public function edit(User $staff): View
    {
        $this->authorize('update', $staff);

        return view('staff.edit', ['staff' => $staff, 'roles' => UserRole::cases()]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($staff->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $staff->update([...$data, 'is_active' => $request->boolean('is_active')]);
        AuditLog::record('staff.updated', $staff);

        return redirect()->route('staff.index')->with('success', $staff->name.' was updated.');
    }

    public function destroy(Request $request, User $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        // Soft-disable rather than delete so their ledger entries keep an author.
        $staff->update(['is_active' => false]);
        AuditLog::record('staff.deactivated', $staff);

        return redirect()->route('staff.index')->with('success', $staff->name.' was deactivated.');
    }
}
