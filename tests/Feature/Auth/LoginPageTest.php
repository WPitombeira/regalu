<?php

use function Pest\Laravel\get;

it('renders the login page with its interactive login form', function () {
    get(route('login'))
        ->assertOk()
        ->assertSee('wire:snapshot', false)
        ->assertSee('home.login-form');
});

it('links the login form to the password reset request route', function () {
    get(route('login'))
        ->assertOk()
        ->assertSee(route('password.request'), false);
});
