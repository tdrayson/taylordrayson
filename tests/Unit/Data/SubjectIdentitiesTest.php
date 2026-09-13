<?php

use App\Data\SubjectIdentities;

it('resolves a stored platform to its value', function () {
    $identities = SubjectIdentities::fromArray([
        ['platform' => 'website', 'value' => 'https://example.com'],
        ['platform' => 'instagram', 'value' => 'https://instagram.com/example'],
    ]);

    expect($identities->urlFor('website'))->toBe('https://example.com');
});

it('returns null for a platform that was never stored', function () {
    $identities = SubjectIdentities::fromArray([
        ['platform' => 'website', 'value' => 'https://example.com'],
    ]);

    expect($identities->urlFor('mastodon'))->toBeNull();
});

it('returns null for every platform when there are no identities', function () {
    expect(SubjectIdentities::fromArray([])->urlFor('website'))->toBeNull();
});
