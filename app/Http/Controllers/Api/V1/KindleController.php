<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Books\RecordKindleSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreKindleReadingRequest;
use Illuminate\Http\JsonResponse;

final class KindleController extends Controller
{
    /**
     * Record a reading snapshot from the jailbroken Kindle.
     */
    public function __invoke(StoreKindleReadingRequest $request, RecordKindleSnapshot $record): JsonResponse
    {
        $result = $record($request->items());

        return response()->json(['data' => $result->toArray()], $result->created > 0 ? 201 : 200);
    }
}
