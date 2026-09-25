<?php

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

function profileUrl(string $path): string
{
    return 'https://'.config('profile.domain').$path;
}

it('serves each card on the profile domain, out of search indexes', function (string $page, string $component) {
    get(profileUrl("/{$page}"))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia->component($component)->where('card.page', $page));

    get(profileUrl("/{$page}/details"))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $inertia) => $inertia->component("{$component}Details")->where('backHref', "/{$page}"));
})->with([
    'personal' => ['td', 'LinkPage/Personal'],
    'business' => ['tct', 'LinkPage/Business'],
]);

it('sends the bare profile domain to the personal card', function () {
    get(profileUrl('/'))->assertRedirect(profileUrl('/td'));
});

it('keeps the cards and the rest of the site apart', function (string $path) {
    get($path)->assertNotFound();
})->with([
    'card on the main domain' => '/td',
    'details on the main domain' => '/tct/details',
    'vcard on the main domain' => '/td/contact',
    'unknown card' => fn () => profileUrl('/nope'),
    'main-site page on the profile domain' => fn () => profileUrl('/now'),
]);

it('shows the details row only while the form is switched on', function (bool $enabled, ?string $href) {
    config(['profile.details_form' => $enabled]);

    get(profileUrl('/td'))->assertInertia(fn (Assert $inertia) => $inertia->where('card.detailsHref', $href));
})->with([
    'off' => [false, null],
    'on' => [true, '/td/details'],
]);

it('hands out the business card with its organisation and the personal one without', function () {
    get(profileUrl('/tct/contact'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/vcard; charset=utf-8')
        ->assertHeader('Content-Disposition', 'attachment; filename=taylor-drayson-the-creative-tinker.vcf')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('ORG:The Creative Tinker')
        ->assertSee('URL:https://thecreativetinker.com');

    $personal = get(profileUrl('/td/contact'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=taylor-drayson.vcf')
        ->assertSee('FN:Taylor Drayson')
        ->getContent();

    expect($personal)->not->toContain('ORG:')->not->toContain('TITLE:');
});
