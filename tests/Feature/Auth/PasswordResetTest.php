<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

describe('password reset', function () {
    it('can view forgot password form', function () {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(__('messages.auth.forgot_password'));
    });

    it('can request a reset link', function () {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post(route('password.email'), ['email' => 'reset@example.com'])
            ->assertSessionHas('status', __('messages.auth.reset_link_sent'));
    });

    it('cannot request reset for non-existent email', function () {
        // Should still return success for security (no email enumeration)
        $this->post(route('password.email'), ['email' => 'nonexistent@example.com'])
            ->assertSessionHas('status', __('messages.auth.reset_link_sent'));
    });

    it('rejects a non-string reset token without calling the password broker', function () {
        $user = User::factory()->create(['email' => 'malformed-reset@example.com']);

        $this->from(route('password.reset', ['token' => 'valid-looking-token']))
            ->post(route('password.update'), [
                'token' => ['not-a-token'],
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'valid-looking-token']))
            ->assertSessionHasErrors('token');

        $this->assertGuest();
    });

    it('resets the password with a valid token and rotates the remember token', function () {
        $user = User::factory()->create([
            'email' => 'valid-reset@example.com',
            'password' => 'old-password',
        ]);
        $oldRememberToken = $user->remember_token;
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        expect(Hash::check('new-password-123', $user->password))->toBeTrue();
        expect($user->remember_token)->not->toBe($oldRememberToken);
        $this->assertAuthenticatedAs($user);
    });

    it('rejects an invalid reset token without changing credentials', function () {
        $user = User::factory()->create([
            'email' => 'invalid-reset@example.com',
            'password' => 'old-password',
        ]);

        $this->from(route('password.reset', ['token' => 'invalid-token']))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token']))
            ->assertSessionHasErrors('email');

        expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
        $this->assertGuest();
    });

    it('rejects a replayed reset token after the first reset succeeds', function () {
        $user = User::factory()->create([
            'email' => 'replayed-reset@example.com',
            'password' => 'old-password',
        ]);
        $token = Password::createToken($user);
        $reset = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];

        $this->post(route('password.update'), $reset)
            ->assertRedirect(route('dashboard'));

        Auth::logout();

        $this->from(route('password.reset', ['token' => $token]))
            ->post(route('password.update'), [
                ...$reset,
                'password' => 'another-password-123',
                'password_confirmation' => 'another-password-123',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token]))
            ->assertSessionHasErrors('email');

        expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
        $this->assertGuest();
    });
});
