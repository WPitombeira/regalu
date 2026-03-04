<?php

use Illuminate\Support\Facades\Schema;

describe('database migrations', function () {
    it('creates the users table with profile fields', function () {
        expect(Schema::hasTable('users'))->toBeTrue();
        expect(Schema::hasColumns('users', ['avatar_url', 'phone', 'locale']))->toBeTrue();
    });

    it('creates the families table', function () {
        expect(Schema::hasTable('families'))->toBeTrue();
        expect(Schema::hasColumns('families', ['id', 'name', 'description', 'invite_code', 'creator_id', 'is_archived']))->toBeTrue();
    });

    it('creates the family_members table', function () {
        expect(Schema::hasTable('family_members'))->toBeTrue();
        expect(Schema::hasColumns('family_members', ['id', 'family_id', 'user_id', 'role', 'joined_at']))->toBeTrue();
    });

    it('creates the wishlists table', function () {
        expect(Schema::hasTable('wishlists'))->toBeTrue();
        expect(Schema::hasColumns('wishlists', ['id', 'user_id', 'family_id', 'name', 'type', 'privacy', 'is_archived']))->toBeTrue();
    });

    it('creates the wishlist_items table', function () {
        expect(Schema::hasTable('wishlist_items'))->toBeTrue();
        expect(Schema::hasColumns('wishlist_items', ['id', 'wishlist_id', 'name', 'priority', 'status', 'bought_by_user_id']))->toBeTrue();
    });

    it('creates the wishlist_shares table', function () {
        expect(Schema::hasTable('wishlist_shares'))->toBeTrue();
        expect(Schema::hasColumns('wishlist_shares', ['id', 'wishlist_id', 'shared_with_user_id', 'access_level']))->toBeTrue();
    });

    it('creates the amigo_secreto_events table', function () {
        expect(Schema::hasTable('amigo_secreto_events'))->toBeTrue();
        expect(Schema::hasColumns('amigo_secreto_events', ['id', 'organizer_id', 'name', 'event_code', 'status']))->toBeTrue();
    });

    it('creates the amigo_secreto_participants table', function () {
        expect(Schema::hasTable('amigo_secreto_participants'))->toBeTrue();
        expect(Schema::hasColumns('amigo_secreto_participants', ['id', 'event_id', 'user_id', 'status']))->toBeTrue();
    });

    it('creates the amigo_secreto_draws table', function () {
        expect(Schema::hasTable('amigo_secreto_draws'))->toBeTrue();
        expect(Schema::hasColumns('amigo_secreto_draws', ['id', 'event_id', 'drawer_user_id', 'target_user_id']))->toBeTrue();
    });

    it('creates the amigo_secreto_exclusions table', function () {
        expect(Schema::hasTable('amigo_secreto_exclusions'))->toBeTrue();
        expect(Schema::hasColumns('amigo_secreto_exclusions', ['id', 'event_id', 'user_a_id', 'user_b_id']))->toBeTrue();
    });

    it('creates the notifications table', function () {
        expect(Schema::hasTable('notifications'))->toBeTrue();
        expect(Schema::hasColumns('notifications', ['id', 'user_id', 'type', 'title', 'message', 'is_read', 'action_url']))->toBeTrue();
    });
});
