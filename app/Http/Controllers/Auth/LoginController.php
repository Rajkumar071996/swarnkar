<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => MobileNumber::normalize($request->input('phone')),
        ]);

        $credentials = $request->validate([
            'phone' => MobileNumber::rules(),
            'password' => ['required', 'string'],
        ], [
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ]);

        $user = User::where('phone', $credentials['phone'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => 'Those credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => 'This account has been deactivated. Contact the store owner.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        AuditLog::record('auth.login');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record('auth.logout');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
