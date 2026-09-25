<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Link-in-bio pages
    |--------------------------------------------------------------------------
    |
    | The personal (/td) and business (/tct) cards served on their own
    | subdomain, keyed by App\Enums\LinkPage. Name, avatar and the social
    | profiles come from config/identity.php; this holds only what differs
    | per card. Contact details live in .env so they are never committed.
    |
    */

    'domain' => env('PROFILE_DOMAIN', 'profile.'.parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    // The "Send me your details" form sends nothing yet, so it stays off
    // wherever a real visitor could fill it in.
    'details_form' => (bool) env('PROFILE_DETAILS_FORM', false),

    'pages' => [

        'td' => [
            'bio' => null,
            'organisation' => 'The Creative Tinker',
            'title' => 'Web Developer',
            'phone' => env('PROFILE_PERSONAL_PHONE'),
            'email' => env('PROFILE_PERSONAL_EMAIL'),
            // Friends get both inboxes and sites, so business mail still lands in the work inbox.
            'vcard_emails' => ['home' => env('PROFILE_PERSONAL_EMAIL'), 'work' => env('PROFILE_BUSINESS_EMAIL')],
            'vcard_websites' => ['home' => env('APP_URL', 'https://taylordrayson.com'), 'work' => 'https://thecreativetinker.com'],
            // Y-m-d, on the personal vCard only.
            'birthday' => env('PROFILE_BIRTHDAY'),
            'sections' => [
                [
                    'heading' => 'My website',
                    'links' => [
                        ['label' => 'taylordrayson.com', 'description' => 'Everything I track, write and share', 'href' => env('APP_URL', 'https://taylordrayson.com'), 'icon' => 'site'],
                    ],
                ],
                [
                    'heading' => 'Work and side quests',
                    'links' => [
                        ['label' => 'The Creative Tinker', 'description' => 'My web design studio', 'href' => 'https://thecreativetinker.com', 'icon' => 'tinker'],
                        ['label' => 'WP Extended', 'description' => 'Our all-in-one WordPress plugin', 'href' => 'https://wpextended.io', 'logo' => '/logos/wp-extended.png'],
                        ['label' => 'This Week With', 'description' => 'Weekly podcast with my dad, :episodes episodes and counting', 'href' => 'https://thisweekwith.co.uk', 'logo' => '/logos/this-week-with.jpg'],
                    ],
                ],
            ],
            // Identity profile label => the label shown on this card.
            'social_heading' => 'Stay in touch',
            'socials' => ['GitHub' => 'GitHub', 'LinkedIn' => 'LinkedIn', 'Strava' => 'Strava'],
        ],

        'tct' => [
            'bio' => "I design and build websites, keep things simple, and tinker until it's right.",
            'organisation' => 'The Creative Tinker',
            'title' => 'Web Developer',
            'phone' => env('PROFILE_BUSINESS_PHONE'),
            'email' => env('PROFILE_BUSINESS_EMAIL'),
            'vcard_emails' => ['work' => env('PROFILE_BUSINESS_EMAIL')],
            'vcard_websites' => ['work' => 'https://thecreativetinker.com'],
            'sections' => [
                [
                    'heading' => 'My website',
                    'links' => [
                        ['label' => 'See my work', 'description' => 'thecreativetinker.com', 'href' => 'https://thecreativetinker.com', 'icon' => 'tinker'],
                    ],
                ],
                [
                    'heading' => 'Also from me',
                    'links' => [
                        ['label' => 'WP Extended', 'description' => 'Our all-in-one WordPress plugin', 'href' => 'https://wpextended.io', 'logo' => '/logos/wp-extended.png'],
                        ['label' => 'This Week With', 'description' => 'Weekly podcast with my dad, :episodes episodes and counting', 'href' => 'https://thisweekwith.co.uk', 'logo' => '/logos/this-week-with.jpg'],
                    ],
                ],
            ],
            'social_heading' => 'Stay in touch',
            'socials' => ['LinkedIn' => 'Connect on LinkedIn'],
        ],

    ],

];
