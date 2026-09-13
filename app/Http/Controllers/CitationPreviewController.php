<?php

namespace App\Http\Controllers;

use App\Actions\BuildResponseContext;
use App\Actions\Citations\FetchCitation;
use App\Actions\Citations\StoreCitation;
use App\Enums\ResponseKind;
use App\Http\Requests\Citations\PreviewCitationRequest;
use App\Models\Citation;
use App\Models\Note;
use App\Support\Links;
use Illuminate\Http\JsonResponse;

/**
 * What a reply's context will look like, for the editor's preview.
 *
 * Built through the same BuildResponseContext as the entry page, on an unsaved
 * note, so the preview can never drift from what gets published.
 */
class CitationPreviewController extends Controller
{
    public function __invoke(PreviewCitationRequest $request, FetchCitation $fetch, StoreCitation $store): JsonResponse
    {
        $url = (string) $request->validated('url');
        $citation = Links::internalPath($url) === null ? $this->citation($url, $request->boolean('refresh'), $fetch, $store) : null;

        $draft = new Note([
            'response_kind' => ResponseKind::from($request->validated('kind')),
            'response_url' => $url,
        ]);
        $draft->setRelation('citation', $citation);

        return response()->json(['data' => app(BuildResponseContext::class)($draft)?->toArray()]);
    }

    private function citation(string $url, bool $refresh, FetchCitation $fetch, StoreCitation $store): ?Citation
    {
        $stored = Citation::query()->firstWhere('url', $url);

        if ($stored !== null && ! $refresh) {
            return $stored;
        }

        $data = $fetch($url);

        return $data === null ? $stored : $store($data);
    }
}
