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
    ],

    'strava' => [
        'client_id' => env('STRAVA_CLIENT_ID'),
        'client_secret' => env('STRAVA_CLIENT_SECRET'),
        'refresh_token' => env('STRAVA_REFRESH_TOKEN'),
    ],

    'health_export' => [
        'token' => env('HEALTH_EXPORT_TOKEN'),
    ],

    'rovi' => [
        'key' => env('ROVI_KEY'),
        'base_url' => env('ROVI_BASE_URL', 'https://europe-west1-rovi-16b3a.cloudfunctions.net/personalApi'),
    ],

    'pocketcasts' => [
        'email' => env('POCKETCASTS_EMAIL'),
        'password' => env('POCKETCASTS_PASSWORD'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'api' => [
        'token' => env('API_TOKEN'),
    ],

];
