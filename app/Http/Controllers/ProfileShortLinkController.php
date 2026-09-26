<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ProfileShortLinkController extends Controller
{
    /**
     * Send a short link such as /linkedin, in any case, to that identity profile.
     */
    public function __invoke(string $profile): RedirectResponse
    {
        $match = collect(config('identity.profiles'))
            ->first(fn (array $candidate): bool => Str::lower($candidate['label']) === Str::lower($profile));

        abort_if($match === null, 404);

        return redirect()->away($match['href']);
    }
}
