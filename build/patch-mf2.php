<?php

/*
 * mf2/mf2 declares `DOMElement $context = null`, which PHP 8.4 deprecates in
 * favour of an explicit `?DOMElement`. The package registers Parser.php under
 * composer's `autoload.files`, so it is included on every process boot and the
 * notice lands in the log of every request, queue worker and artisan command,
 * whether or not anything parses microformats.
 *
 * Upstream is aware and unfixed on main:
 *   https://github.com/microformats/php-mf2/issues/269
 *   https://github.com/microformats/php-mf2/issues/264
 *
 * Idempotent, so re-running composer install is safe. Delete this script and
 * its composer hook once a released version carries the fix.
 */

$parser = __DIR__.'/../vendor/mf2/mf2/Mf2/Parser.php';

if (! is_file($parser)) {
    return;
}

$source = file_get_contents($parser);
$patched = str_replace('DOMElement $context = null', '?DOMElement $context = null', $source);

// Guard against double-patching, which would produce `??DOMElement`.
$patched = str_replace('??DOMElement', '?DOMElement', $patched);

if ($patched !== $source) {
    file_put_contents($parser, $patched);
    echo "Patched mf2/mf2 for PHP 8.4 implicit nullability (upstream #269).\n";
}
