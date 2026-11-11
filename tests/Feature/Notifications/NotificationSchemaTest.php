<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('migrates nullable domain notice keys while enforcing non-null replay uniqueness', function () {
    expect(Schema::hasColumns('notifications', ['kind', 'deduplication_key']))->toBeTrue();
    $row = ['user_id' => User::factory()->create()->id, 'type' => 'system', 'title' => 'Legacy', 'message' => 'Legacy'];
    DB::table('notifications')->insert([$row, $row]);
    $keyed = [...$row, 'kind' => 'family_invitation', 'deduplication_key' => str_repeat('a', 64)];
    DB::table('notifications')->insert($keyed);
    expect(fn () => DB::table('notifications')->insert($keyed))->toThrow(UniqueConstraintViolationException::class);
    expect(DB::table('notifications')->count())->toBe(3);
});

it('preserves populated legacy notices across downgrade and reapplication', function () {
    $migration = require database_path('migrations/2026_11_11_000001_add_domain_keys_to_notifications.php');
    $migration->down();
    try {
        $row = ['user_id' => User::factory()->create()->id, 'type' => 'system', 'title' => 'Legacy title', 'message' => 'Legacy message', 'is_read' => true, 'action_url' => '/dashboard'];
        $id = DB::table('notifications')->insertGetId($row);
        $migration->up();
        $upgraded = DB::table('notifications')->where('id', $id)->first();
        expect($upgraded->title)->toBe($row['title'])->and($upgraded->kind)->toBeNull()->and($upgraded->deduplication_key)->toBeNull();
        $migration->down();
        expect(Schema::hasColumn('notifications', 'deduplication_key'))->toBeFalse();
        $retained = DB::table('notifications')->where('id', $id)->first();
        expect($retained->message)->toBe($row['message'])->and((bool) $retained->is_read)->toBeTrue()->and($retained->action_url)->toBe('/dashboard');
    } finally {
        if (! Schema::hasColumn('notifications', 'kind')) {
            $migration->up();
        }
    }
});
