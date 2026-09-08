<?php

use App\Actions\ResolveDynamicTags;
use App\DynamicTags\DynamicTagRegistry;
use App\Enums\Placement;
use App\Models\Checkin;
use App\Queries\PhotoStream;
use App\Support\PortableText;
use Illuminate\Support\Facades\Storage;

it('is legal only as an image source', function () {
    expect(app(DynamicTagRegistry::class)->find('entries.photo')->supports())
        ->toBe([Placement::Image]);
});

it('rewrites an image tag into the most recent photo\'s url', function () {
    Storage::fake('public');

    $entry = Checkin::factory()->create(['occurred_at' => now()->subDay()]);
    $entry->addMediaFromString(fakeJpeg())->usingFileName('photo.jpg')->toMediaCollection('photos');

    $document = [['_type' => 'image', '_key' => 'i1', 'tag' => 'entries.photo', 'options' => []]];

    $resolved = app(ResolveDynamicTags::class)($document);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->not->toHaveKeys(['tag', 'options'])
        ->and($resolved[0]['url'])->toBe(app(PhotoStream::class)(1)[0]['full']);
});

it('drops an image tag when the timeline has no photos, rather than rendering broken', function () {
    // No photos in the test database, so the node drops rather than rendering broken.
    $document = [['_type' => 'image', '_key' => 'i1', 'tag' => 'entries.photo', 'options' => []]];

    expect(app(ResolveDynamicTags::class)($document))->toBe([]);
});

it('drops an image whose tag cannot resolve', function () {
    $document = [['_type' => 'image', '_key' => 'i1', 'tag' => 'entries.nope', 'options' => []]];

    expect(app(ResolveDynamicTags::class)($document))->toBe([]);
});

it('drops only the unresolvable image, leaving the rest of the document intact', function () {
    $textBlock = PortableText::block('Before and after the image.');

    $document = [
        $textBlock,
        ['_type' => 'image', '_key' => 'i1', 'tag' => 'entries.photo', 'options' => []],
    ];

    $resolved = app(ResolveDynamicTags::class)($document);

    expect($resolved)->toBe([$textBlock])
        ->and(PortableText::plainText($resolved))->toBe('Before and after the image.');
});

it('leaves an ordinary image with a real url and no tag untouched', function () {
    $document = [['_type' => 'image', '_key' => 'i1', 'url' => 'https://example.com/photo.jpg']];

    expect(app(ResolveDynamicTags::class)($document))->toBe($document);
});
