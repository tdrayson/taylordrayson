<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Now\RecordNowState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNowStateRequest;
use Illuminate\Http\JsonResponse;

/**
 * Takes readings from Apple Shortcuts for the Now page. One endpoint serves
 * both the scheduled shortcut that sends everything and the plug-in shortcut
 * that sends only the battery, because a send only touches the groups it
 * carries.
 */
class NowStateController extends Controller
{
    /**
     * Confirm the endpoint is reachable and the token accepted, and describe
     * what it takes, so a shortcut can be checked from the phone.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'ok' => true,
                'accepts' => StoreNowStateRequest::SCHEMA,
                'message' => 'POST any subset of these groups. Fields you omit keep their current value.',
            ],
        ]);
    }

    public function store(StoreNowStateRequest $request, RecordNowState $record): JsonResponse
    {
        // Echoing the written groups back makes a misbuilt shortcut obvious from
        // the phone: an empty list means nothing landed.
        return response()->json(['data' => ['ok' => true, 'written' => $record($request->validated())]]);
    }
}
