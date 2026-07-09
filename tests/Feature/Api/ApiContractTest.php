<?php

it('rejects requests without a token', function () {
    config()->set('services.api.token', 'test-token');

    $this->getJson('/api/v1/ping')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('rejects requests with a wrong token', function () {
    config()->set('services.api.token', 'test-token');

    $this->withToken('wrong')->getJson('/api/v1/ping')->assertUnauthorized();
});

it('fails closed when no token is configured', function () {
    config()->set('services.api.token', null);

    $this->withToken('anything')->getJson('/api/v1/ping')->assertUnauthorized();
});

it('fails closed when the configured token is an empty string', function () {
    config()->set('services.api.token', '');

    $this->withToken('anything')->getJson('/api/v1/ping')->assertUnauthorized();
});

it('responds to ping with a valid token', function () {
    config()->set('services.api.token', 'test-token');

    $this->withToken('test-token')->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJson(['data' => ['ok' => true]]);
});

it('returns JSON 404 for unknown v1 routes when authenticated', function () {
    config()->set('services.api.token', 'test-token');

    $this->withToken('test-token')->getJson('/api/v1/does-not-exist')->assertNotFound();
});
