<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

it('exposes public avatar bytes through the documented storage link and can refresh it', function () {
    $root = sys_get_temp_dir().'/regalu-public-storage-'.Str::random(20);
    $target = $root.'/uploads';
    $link = $root.'/public/storage';
    mkdir($target.'/avatars', 0700, true);
    mkdir(dirname($link), 0700, true);
    // A one-pixel PNG keeps the link test independent of optional image-generation extensions.
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX+XDSwAAAABJRU5ErkJggg==', true);
    file_put_contents($target.'/avatars/avatar.png', $bytes);
    config(['filesystems.links' => [$link => $target]]);

    try {
        $this->artisan('storage:link')->assertSuccessful();
        expect(is_link($link))->toBeTrue()
            ->and(file_get_contents($link.'/avatars/avatar.png'))->toBe($bytes);
        $this->artisan('storage:link', ['--force' => true])->assertSuccessful();
        expect(readlink($link))->toBe($target)
            ->and(file_get_contents($link.'/avatars/avatar.png'))->toBe($bytes);
    } finally {
        if (is_link($link)) {
            unlink($link);
        }
        (new Filesystem)->deleteDirectory($root);
    }
});
