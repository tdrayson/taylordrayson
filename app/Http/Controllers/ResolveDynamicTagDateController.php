<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveDynamicTagDateRequest;
use App\Queries\ResolveFreeTextDate;
use Illuminate\Http\JsonResponse;

/** What one typed string resolves to as a date, for the dynamic tag editor's `from`/`to` fields. */
class ResolveDynamicTagDateController extends Controller
{
    public function __invoke(ResolveDynamicTagDateRequest $request, ResolveFreeTextDate $resolve): JsonResponse
    {
        return response()->json(['data' => [
            'date' => $resolve((string) $request->input('value')),
        ]]);
    }
}
