<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller {
    public function showForgotForm(): View {
        return view('livewire.auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        // Always return success to prevent email enumeration
        return back()->with('status', __("messages.auth.reset_link_sent"));
    }

    public function showResetForm(Request $request, string $token): View {
        return view('livewire.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('dashboard')->with('status', __("messages.auth.reset_success"));
        }

        return back()->withErrors(['email' => __("messages.auth.invalid_token")]);
    }
}
