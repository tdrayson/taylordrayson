<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Where an OAuth client is allowed to have the authorization code sent.
    | The package ships "*", which would let anyone register a client that
    | redirects the code to a host they own; the login screen would look
    | entirely normal and the token would be theirs. Only Claude's own
    | callbacks belong here, plus localhost for Claude Code and the inspector.
    |
    */

    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'http://localhost',
    ],

];
