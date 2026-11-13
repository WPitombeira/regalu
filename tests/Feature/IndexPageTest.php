<?php

it('renders the hero section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.hero.headline'));
    $response->assertSee(__('messages.hero.subheadline'));
    $response->assertSee(__('messages.buttons.get_started'));
});

it('renders the features section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.features.headline'));
    $response->assertSee(__('messages.features.subheadline'));
    $response->assertSee(__('messages.features.wishlist.headline'));
    $response->assertSee(__('messages.features.wishlist.subheadline'));
    $response->assertSee(__('messages.features.family_friends.headline'));
    $response->assertSee(__('messages.features.family_friends.subheadline'));
});

it('renders the privacy section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.features.privacy.headline'));
    $response->assertSee(__('messages.features.privacy.subheadline'));
});

it('renders the newsletter section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee(__('messages.newsletter.headline'));
    $response->assertSee(__('messages.newsletter.subheadline'));
    $response->assertSee(__('messages.buttons.notify_me'));
    $response->assertSee(__('messages.newsletter.caredata'));
    $response->assertSee(__('messages.newsletter.privacy_policy'));
});

it('renders the contact section', function () {
    $response = $this->get(route('home'));
    $response->assertStatus(200);
    $response->assertSee('Contact delivery is unavailable. Please do not enter a message; this form cannot send it.');
    $response->assertSee('Your name (optional)');
    $response->assertSee('Your email (optional)');
    $response->assertSee('Your message');
    $response->assertSee('Sending unavailable');
});
