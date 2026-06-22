<?php

use App\Support\HtmlSanitizer;

it('keeps allowed anchors but strips their other attributes', function () {
    $html = 'See <a class="rank-math-link" target="_blank" href="https://example.com">example</a>';

    expect(HtmlSanitizer::clean($html))->toBe('See <a href="https://example.com">example</a>');
});

it('preserves plain text and line breaks', function () {
    $html = "First line\n\nSecond line";

    expect(HtmlSanitizer::clean($html))->toBe("First line\n\nSecond line");
});

it('unwraps disallowed tags to their text content', function () {
    $html = 'Hello <script>alert(1)</script><strong>world</strong>';

    expect(HtmlSanitizer::clean($html))->toBe('Hello alert(1)world');
});

it('drops links with unsafe schemes but keeps their text', function (string $href) {
    $html = "Click <a href=\"{$href}\">here</a>";

    expect(HtmlSanitizer::clean($html))->toBe('Click here');
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html,alert(1)',
]);

it('allows http, https and mailto links', function (string $href) {
    $html = "Reach <a href=\"{$href}\">us</a>";

    expect(HtmlSanitizer::clean($html))->toBe("Reach <a href=\"{$href}\">us</a>");
})->with([
    'https://thisweekwith.co.uk',
    'http://example.com',
    'mailto:hello@example.com',
]);

it('returns an empty string for blank input', function () {
    expect(HtmlSanitizer::clean('   '))->toBe('');
});
