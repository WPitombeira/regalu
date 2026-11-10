<?php

namespace App\Http\Controllers;

use App\Services\LoginAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function authenticate(Request $request, LoginAttemptService $loginAttempts): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => ['required', 'string'],
        ]);
        $remember = $request->boolean('remember');

        if ($loginAttempts->attempt($credentials['email'], $credentials['password'], $remember, $request->ip())) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function logoutAll(Request $request): RedirectResponse
    {
        Auth::logoutOtherDevices($request->password);

        return back()->with('status', 'Password confirmed. You have been logged out on other devices.');
    }

    public function viewSettings(): View
    {
        return view('settings');
    }
}
