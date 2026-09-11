<?php

namespace App\Http\Controllers;

use App\Queries\DynamicTagsPayload;
use Illuminate\Http\JsonResponse;

/** Every registered tag, with the option schema and preview the editor reads. */
class DynamicTagsController extends Controller
{
    public function __invoke(DynamicTagsPayload $payload): JsonResponse
    {
        return response()->json([
            'data' => $payload(),
            // The zone a tag's `period`/`from`/`to` bound is interpreted in,
            // so the editor can show it beside the date fields.
            'homeTimezone' => (string) config('app.home_timezone'),
        ]);
    }
}
