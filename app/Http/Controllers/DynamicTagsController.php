<?php

namespace App\Http\Controllers;

use App\Queries\DynamicTagsPayload;
use Illuminate\Http\JsonResponse;

/** Every registered tag, with the option schema and preview the editor reads. */
class DynamicTagsController extends Controller
{
    public function __invoke(DynamicTagsPayload $payload): JsonResponse
    {
        return response()->json(['data' => $payload()]);
    }
}
