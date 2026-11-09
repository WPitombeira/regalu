<?php

use Illuminate\Support\Facades\Schema;

it('rolls the complete SQLite migration chain back and reapplies it', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    expect(Schema::hasTable('newsletters'))->toBeTrue();
    $this->artisan('migrate:reset', ['--force' => true])->assertSuccessful();
    expect(Schema::hasTable('newsletters'))->toBeFalse()
        ->and(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('amigo_secreto_events'))->toBeFalse();

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    expect(Schema::hasTable('newsletters'))->toBeTrue()
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('amigo_secreto_events'))->toBeTrue();
});
