<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted senders
    |--------------------------------------------------------------------------
    |
    | Hosts whose mentions skip the moderation queue and appear straight away.
    | Matched on the whole host, so "example.com" never admits
    | "example.com.evil.tld" or "notexample.com".
    |
    | A subdomain is its own host: trusting "example.com" does not trust
    | "blog.example.com". List both if you mean both.
    |
    | Everything else is held for a first read, and a host is trusted from then
    | on once you have approved one of its mentions.
    |
    */

    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WEBMENTION_TRUSTED_HOSTS', '')),
    ))),

];
