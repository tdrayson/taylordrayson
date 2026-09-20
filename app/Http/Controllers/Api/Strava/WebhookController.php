<?php

namespace App\Http\Controllers\Api\Strava;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Strava\VerifySubscriptionRequest;
use App\Http\Requests\Api\Strava\WebhookEventRequest;
use App\Jobs\ProcessStravaWebhookEvent;
use Illuminate\Http\JsonResponse;

/**
 * Strava's push callback. Both methods answer without touching the API: the
 * callback has roughly two seconds to return 200 before Strava treats it as
 * failed, so an event is queued and the detail fetched out of band.
 */
class WebhookController extends Controller
{
    public function verify(VerifySubscriptionRequest $request): JsonResponse
    {
        return response()->json(['hub.challenge' => $request->challenge()]);
    }

    public function store(WebhookEventRequest $request): JsonResponse
    {
        ProcessStravaWebhookEvent::dispatch($request->event());

        return response()->json(['ok' => true]);
    }
}
