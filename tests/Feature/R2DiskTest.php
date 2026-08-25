<?php

use Illuminate\Support\Facades\Storage;

/*
 * Storage::fake('r2') swaps in a local disk, so every other test that touches
 * R2 passes whether or not the S3 adapter is installed at all. This is the one
 * place the real driver is built, which is what the backup and mirror commands
 * do on the server.
 */

it('can build the r2 disk with the real s3 driver', function () {
    $disk = Storage::build([
        ...config('filesystems.disks.r2'),
        'bucket' => 'probe',
        'endpoint' => 'https://example.invalid',
    ]);

    expect($disk->path('x'))->toBeString();
});
