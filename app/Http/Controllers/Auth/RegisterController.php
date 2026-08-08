<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Opens a new jeweller on the network: one store and its owner account in a
 * single step. Staff accounts are added later by that owner from inside the app.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => MobileNumber::normalize($request->input('phone')),
            'email' => filled($request->input('email')) ? $request->input('email') : null,
        ]);

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^\d{6}$/'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => MobileNumber::rules(unique: true),
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'phone.unique' => 'An account with this mobile number already exists. Sign in instead.',
            'pincode.regex' => 'Enter a valid 6-digit PIN code.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $store = Store::create([
                'name' => $data['store_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'address_line' => $data['address_line'],
                'city' => $data['city'],
                'state' => $data['state'],
                'pincode' => $data['pincode'],
            ]);

            $user = User::create([
                'store_id' => $store->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'role' => UserRole::Owner,
                'is_active' => true,
            ]);

            // Outside fillable on purpose — verification is an account event,
            // not something a request body should ever set.
            $user->email_verified_at = now();
            $user->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('auth.register', $user->store);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Welcome to GoldScore. Your store is ready — start by adding a customer or checking a GoldScore.');
    }
}
