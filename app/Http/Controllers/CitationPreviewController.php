<?php

namespace App\Http\Controllers;

use App\Actions\BuildResponseContext;
use App\Actions\Citations\ResolveCitation;
use App\Enums\ResponseKind;
use App\Http\Requests\Citations\PreviewCitationRequest;
use App\Models\Note;
use App\Support\Links;
use Illuminate\Http\JsonResponse;

/** The editor's preview of a reply's context, built on an unsaved note exactly as the entry page builds it. */
class CitationPreviewController extends Controller
{
    public function __invoke(PreviewCitationRequest $request, ResolveCitation $resolve, BuildResponseContext $build): JsonResponse
    {
        $url = (string) $request->validated('url');
        $citation = Links::internalPath($url) === null ? $resolve($url, $request->boolean('refresh')) : null;

        $draft = new Note([
            'response_kind' => ResponseKind::from($request->validated('kind')),
            'response_url' => $url,
        ]);
        $draft->setRelation('citation', $citation);

        return response()->json(['data' => $build($draft)?->toArray()]);
    }
}
