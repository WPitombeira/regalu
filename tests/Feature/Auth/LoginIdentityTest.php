<?php

use App\Models\User;
use App\Services\LoginAttemptService;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollatedLoginUser extends User
{
    protected $table = 'collated_login_users';
}

it('shares the attempt budget for every spelling the configured provider matches', function () {
    // Exercise a real SQL lookup with case/accent-insensitive equality. This fixture
    // models the relevant MySQL collation behavior, not the full MySQL dialect.
    DB::connection()->getPdo()->sqliteCreateCollation('test_case_accent_ci', fn (string $left, string $right) => strcmp(
        Str::lower(Str::ascii($left)), Str::lower(Str::ascii($right))
    ));
    DB::statement('CREATE TABLE collated_login_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT COLLATE test_case_accent_ci UNIQUE, password TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT)');
    $user = CollatedLoginUser::create(['name' => 'Jose', 'email' => 'jose@example.com', 'password' => 'correct-password']);
    Auth::guard()->setProvider(new EloquentUserProvider(app('hash'), CollatedLoginUser::class));
    $service = app(LoginAttemptService::class);

    expect(Auth::getProvider()->retrieveByCredentials(['email' => 'JOSÉ@example.com'])->getAuthIdentifier())->toBe($user->id);
    foreach (range(1, 5) as $attempt) {
        expect($service->attempt('jose@example.com', 'incorrect', false, '192.0.2.1'))->toBeFalse();
    }
    expect($service->attempt('JOSÉ@example.com', 'correct-password', false, '192.0.2.1'))->toBeFalse();
    $this->assertGuest();

    expect($service->attempt('JOSÉ@example.com', 'correct-password', false, '192.0.2.2'))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    Auth::logout();
    expect($service->attempt('unknown@example.com', 'correct-password', false, '192.0.2.2'))->toBeFalse();
});
