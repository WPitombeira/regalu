<?php

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

it('uses JSON session payloads while preserving session cookie naming', function () {
    $session = app('session')->driver();
    $session->start();
    $session->put('preferences', ['locale' => 'pt_BR']);
    $session->save();

    $stored = $session->getHandler()->read($session->getId());
    expect(json_decode($stored, true, flags: JSON_THROW_ON_ERROR)['preferences'])->toBe(['locale' => 'pt_BR']);
    expect(config('session.cookie'))->toEndWith('_session');
});

it('does not rehydrate arbitrary cached objects but supports scalar metadata', function () {
    $cache = Cache::store('database');
    $cache->put('metadata', ['title' => 'Gift'], 60);
    $cache->put('object', (object) ['title' => 'Gift'], 60);
    expect($cache->get('metadata'))->toBe(['title' => 'Gift']);
    expect($cache->get('object'))->not->toBeInstanceOf(stdClass::class);
});

it('enables development safeguards against lazy loading and discarded model attributes', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
    expect(fn () => (new Wishlist)->fill(['typo_name' => 'Lost value']))
        ->toThrow(MassAssignmentException::class);
});
