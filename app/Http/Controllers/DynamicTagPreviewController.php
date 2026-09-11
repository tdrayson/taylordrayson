<?php

namespace App\Http\Controllers;

use App\Enums\Placement;
use App\Http\Requests\ResolveDynamicTagRequest;
use App\Queries\DynamicTagPreview;
use Illuminate\Http\JsonResponse;

/** What one tag currently resolves to for an arbitrary, validated option set. */
class DynamicTagPreviewController extends Controller
{
    public function __invoke(ResolveDynamicTagRequest $request, DynamicTagPreview $preview): JsonResponse
    {
        $resolved = $preview(
            (string) $request->input('name'),
            (array) $request->input('options', []),
            Placement::from((string) $request->input('placement')),
        );

        return response()->json(['data' => [
            'preview' => $resolved['text'] ?? null,
            'value' => $resolved['value'] ?? null,
            'icon' => $resolved['icon'] ?? null,
        ]]);
    }
}
