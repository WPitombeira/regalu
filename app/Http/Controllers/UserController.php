<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller {
    public function authenticate(Request $request): RedirectResponse {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $remember = $request->filled('remember') ?? false;

        // Auth::attempt(['email' => $credentials->email, 'password' => $credentials->password, 'active' => 1]);

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function loginWithoutRedirect(string $email, string $password, bool $remember): bool {
        if (Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return true;
        }

        return false;
    }

    public function logout(): RedirectResponse {
        Auth::logout();
        return redirect('/');
    }

    public function logoutAll(Request $request): RedirectResponse {
        Auth::logoutOtherDevices($request->password);

        return back()->with('status', 'Password confirmed. You have been logged out on other devices.');
    }
}
