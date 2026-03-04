<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

describe('password reset', function () {
    it('can view forgot password form', function () {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(__("messages.auth.forgot_password"));
    });

    it('can request a reset link', function () {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post(route('password.email'), ['email' => 'reset@example.com'])
            ->assertSessionHas('status', __("messages.auth.reset_link_sent"));
    });

    it('cannot request reset for non-existent email', function () {
        // Should still return success for security (no email enumeration)
        $this->post(route('password.email'), ['email' => 'nonexistent@example.com'])
            ->assertSessionHas('status', __("messages.auth.reset_link_sent"));
    });
});
