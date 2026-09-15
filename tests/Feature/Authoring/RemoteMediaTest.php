<?php

use App\Actions\Media\FetchRemoteMedia;
use App\Data\FieldData;
use App\Enums\FieldType;
use App\Fields\FieldRules;
use App\Support\PendingUploads;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function coverField(): array
{
    return [FieldData::optional('cover', 'Cover', FieldType::Image, collection: 'cover')];
}

it('parks a remote image as a pending upload', function () {
    Http::fake(['assets.example.com/*' => Http::response(fakeJpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

    $result = app(FetchRemoteMedia::class)(coverField(), [
        'title' => 'Atomic Habits',
        'cover' => ['url:https://assets.example.com/books/cover.jpeg'],
    ]);

    $token = substr($result['cover'][0], strlen('pending:'));

    expect($result['cover'][0])->toStartWith('pending:')
        ->and(PendingUploads::path($token))->not->toBeNull()
        ->and($result['title'])->toBe('Atomic Habits');

    PendingUploads::forget($token);
});

it('leaves attached and uploaded items alone', function () {
    Http::fake();

    $items = ['9b1f6a0e-uuid', 'pending:'.str_repeat('a', 40)];

    expect(app(FetchRemoteMedia::class)(coverField(), ['cover' => $items])['cover'])->toBe($items);
    Http::assertNothingSent();
});

it('refuses an image it cannot fetch', function (string $url) {
    Http::fake(['assets.example.com/*' => Http::response('Not found', 404, ['Content-Type' => 'text/html'])]);

    app(FetchRemoteMedia::class)(coverField(), ['cover' => ['url:'.$url]]);
})->throws(ValidationException::class, "Couldn't fetch that image, upload one instead.")
    ->with(['https://assets.example.com/missing.jpeg', 'http://assets.example.com/plain.jpeg']);

it('never posts a read-only field', function () {
    $rules = FieldRules::for([FieldData::readOnly('source_id', 'Kindle ID', FieldType::Text)], creating: false);

    expect($rules['source_id'])->toBe(['exclude'])
        ->and(FieldData::readOnly('progress_percent', 'Progress', FieldType::Number, suffix: '%')->toArray())
        ->toMatchArray(['readOnly' => true, 'suffix' => '%']);
});
