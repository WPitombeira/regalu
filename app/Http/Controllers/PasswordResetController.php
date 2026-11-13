<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgotForm(): View
    {
        return view('livewire.auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [], ['email' => __('password_recovery.attributes.email')]);

        Password::sendResetLink($request->only('email'));

        // Acknowledge every well-formed request without disclosing account existence.
        return back()->with('status', __('password_recovery.requested'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('livewire.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], [
            'email' => __('password_recovery.attributes.email'),
            'password' => __('password_recovery.attributes.password'),
            'token' => __('password_recovery.attributes.token'),
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
            return redirect()->route('dashboard')->with('status', __('password_recovery.success'));
        }

        return back()->withErrors(['email' => __('password_recovery.invalid_link')]);
    }
}
