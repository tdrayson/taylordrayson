<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'foursquare' => [
        'access_token' => env('FOURSQUARE_ACCESS_TOKEN'),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
    ],

    'logostream' => [
        'key' => env('LOGOSTREAM_KEY'),
        'url' => env('LOGOSTREAM_URL', 'https://airlines-api.logostream.dev'),
        'aviation_url' => env('LOGOSTREAM_AVIATION_URL', 'https://aviation-api.logostream.dev'),
    ],

    'timeapi' => [
        'url' => env('TIMEAPI_URL', 'https://timeapi.io'),
    ],

    'strava' => [
        'client_id' => env('STRAVA_CLIENT_ID'),
        'client_secret' => env('STRAVA_CLIENT_SECRET'),
        'refresh_token' => env('STRAVA_REFRESH_TOKEN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
