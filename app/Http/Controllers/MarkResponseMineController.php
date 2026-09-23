<?php

namespace App\Http\Controllers;

use App\Http\Requests\Interactions\MarkResponseMineRequest;
use App\Models\SyndicatedResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Marks a Strava or Swarm reply as mine, or unmarks it, for the replies whose
 * source could not say who wrote them.
 */
class MarkResponseMineController extends Controller
{
    public function __invoke(MarkResponseMineRequest $request, SyndicatedResponse $response): RedirectResponse
    {
        $response->update(['mine' => $request->boolean('mine')]);

        return back();
    }
}
