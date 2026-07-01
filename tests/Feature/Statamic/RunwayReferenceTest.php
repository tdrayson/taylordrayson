<?php

use StatamicRadPack\Runway\Runway;

it('registers airlines and airports as read-only reference resources', function (string $handle) {
    $resource = Runway::findResource($handle);
    expect($resource->readOnly())->toBeTrue();
})->with(['airline', 'airport']);
