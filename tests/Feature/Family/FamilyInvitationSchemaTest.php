<?php

use Illuminate\Support\Facades\Schema;

it('stores invitations separately from actual family membership', function () {
    expect(Schema::hasTable('family_invitations'))->toBeTrue();
    expect(Schema::hasColumns('family_invitations', [
        'id', 'family_id', 'inviter_id', 'email', 'generation', 'status',
        'expires_at', 'responded_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasIndex('family_invitations', ['family_id', 'email'], 'unique'))->toBeTrue();
});
