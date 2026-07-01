<?php

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Fuel;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use StatamicRadPack\Runway\Runway;

it('registers a runway resource for every editable lifelog model', function (string $handle, string $model) {
    $resource = Runway::findResource($handle);
    expect($resource->model())->toBeInstanceOf($model);
})->with([
    ['activity', Activity::class],
    ['calorie', Calorie::class],
    ['sleep', Sleep::class],
    ['fuel', Fuel::class],
    ['podcast', Podcast::class],
    ['checkin', Checkin::class],
    ['event', Event::class],
    ['appearance', Appearance::class],
    ['project', Project::class],
]);
