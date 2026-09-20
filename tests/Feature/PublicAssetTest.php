<?php

use App\Support\PublicAsset;

beforeEach(function () {
    $this->path = '/icons/public-asset-test.png';
    $this->file = public_path(ltrim($this->path, '/'));
    PublicAsset::flush();
});

afterEach(function () {
    @unlink($this->file);
    PublicAsset::flush();
});

it('leaves a path alone when there is no such file', function () {
    expect(PublicAsset::url('/icons/does-not-exist.png'))->toBe('/icons/does-not-exist.png');
});

it('versions the URL with a hash of the file contents', function () {
    file_put_contents($this->file, 'first');

    expect(PublicAsset::url($this->path))->toBe($this->path.'?v='.substr(md5('first'), 0, 8));
});

it('changes the version when the bytes change and not when they do not', function () {
    file_put_contents($this->file, 'first');
    $original = PublicAsset::url($this->path);

    file_put_contents($this->file, 'second');
    PublicAsset::flush();
    expect(PublicAsset::url($this->path))->not->toBe($original);

    file_put_contents($this->file, 'first');
    touch($this->file, time() + 60);
    PublicAsset::flush();
    expect(PublicAsset::url($this->path))->toBe($original);
});

it('hashes a file once per process', function () {
    file_put_contents($this->file, 'first');
    $original = PublicAsset::url($this->path);

    file_put_contents($this->file, 'second');

    expect(PublicAsset::url($this->path))->toBe($original);
});
