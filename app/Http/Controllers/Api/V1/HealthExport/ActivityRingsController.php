<?php

namespace App\Http\Controllers\Api\V1\HealthExport;

use App\Actions\Now\RecordNowState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HealthExport\StoreActivityRingsRequest;
use App\Support\Health\ActivityRings;
use Illuminate\Http\JsonResponse;

/**
 * Take today's rings and step count from Health Auto Export and write them to
 * the Now state.
 *
 * This runs inline rather than queued: a summarised send is one sample per
 * metric, and the reading is only worth having while it is current. The ring
 * goals are not in HealthKit's export, so they are left to the value already in
 * state, which the Now shortcut refreshes when it runs.
 */
class ActivityRingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => [
            'ok' => true,
            'accepts' => (new StoreActivityRingsRequest)->metrics(),
            'message' => 'Activity rings endpoint ready. Send these metrics with POST, summarised daily.',
        ]]);
    }

    public function store(StoreActivityRingsRequest $request, RecordNowState $record): JsonResponse
    {
        $rings = ActivityRings::from($request->payload());

        $record(['rings' => $rings]);

        // Echoing the values back makes a misconfigured automation obvious from
        // the phone: an empty object means the send carried nothing for today.
        return response()->json(['data' => ['ok' => true, 'rings' => $rings]]);
    }
}
