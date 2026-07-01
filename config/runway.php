<?php

use App\Models\Activity;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Appearance;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;

return [

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Configure the resources (models) you'd like to be available in Runway.
    |
    */

    'resources' => [
        Flight::class => [
            'name' => 'Flights',
            'blueprint' => 'flight',
            'route' => null,
        ],
        Airline::class => [
            'name' => 'Airlines',
            'blueprint' => 'airline',
            'route' => null,
            'read_only' => true,
        ],
        Airport::class => [
            'name' => 'Airports',
            'blueprint' => 'airport',
            'route' => null,
            'read_only' => true,
        ],
        Activity::class => [
            'name' => 'Activities',
            'blueprint' => 'activity',
            'route' => null,
        ],
        Calorie::class => [
            'name' => 'Calories',
            'blueprint' => 'calorie',
            'route' => null,
        ],
        Sleep::class => [
            'name' => 'Sleep',
            'blueprint' => 'sleep',
            'route' => null,
        ],
        Fuel::class => [
            'name' => 'Fuel',
            'blueprint' => 'fuel',
            'route' => null,
        ],
        Podcast::class => [
            'name' => 'Podcasts',
            'blueprint' => 'podcast',
            'route' => null,
        ],
        Checkin::class => [
            'name' => 'Checkins',
            'blueprint' => 'checkin',
            'route' => null,
        ],
        Event::class => [
            'name' => 'Events',
            'blueprint' => 'event',
            'route' => null,
        ],
        Appearance::class => [
            'name' => 'Appearances',
            'blueprint' => 'appearance',
            'route' => null,
        ],
        Project::class => [
            'name' => 'Projects',
            'blueprint' => 'project',
            'route' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runway URIs Table
    |--------------------------------------------------------------------------
    |
    | When using Runway's front-end routing functionality, Runway will store model
    | URIs in a table to enable easy "URI -> model" lookups. If needed, you can
    | customize the table name here.
    |
    */

    'uris_table' => 'runway_uris',

    /*
    |--------------------------------------------------------------------------
    | Disable Migrations?
    |--------------------------------------------------------------------------
    |
    | Should Runway's migrations be disabled?
    | (eg. not automatically run when you next vendor:publish)
    |
    */

    'disable_migrations' => false,

];
