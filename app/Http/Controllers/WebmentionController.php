<?php

namespace App\Http\Controllers;

use App\Jobs\VerifyWebmention;
use App\Models\Webmention;
use App\Support\WebmentionTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public webmention endpoint.
 *
 * Answers fast and verifies later, which is what the spec expects: the checks
 * here are the ones that can be made without leaving the server, and anything
 * needing the source fetched happens in a queued job.
 */
class WebmentionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $source = trim((string) $request->input('source'));
        $target = trim((string) $request->input('target'));

        if ($error = $this->reject($source, $target)) {
            return response()->json(['error' => $error], 400);
        }

        // Keyed on the pair, so a re-send updates the row it already made.
        // That is also how an edit or a delete at the source arrives.
        $mention = Webmention::query()->updateOrCreate(
            ['source_url' => $source, 'target_url' => $target],
            ['last_checked_at' => null],
        );

        VerifyWebmention::dispatch($mention->id);

        return response()->json(['status' => 'accepted'], 202);
    }

    /**
     * Why this mention cannot be accepted, or null if it can.
     *
     * Only the checks that can be made without leaving the server, which is
     * also why the source is not vetted here: deciding whether it is safe to
     * fetch means resolving its DNS, and that belongs immediately before the
     * fetch itself rather than a queue-length earlier.
     */
    private function reject(string $source, string $target): ?string
    {
        return match (true) {
            $source === '' || $target === '' => 'Both source and target are required.',
            ! filter_var($source, FILTER_VALIDATE_URL) => 'source is not a valid URL.',
            ! filter_var($target, FILTER_VALIDATE_URL) => 'target is not a valid URL.',
            $source === $target => 'source and target cannot be the same.',

            // Internal links already render as link previews, so a self-ping
            // would only duplicate one further down the same page.
            WebmentionTarget::isOurs($source) => 'source cannot be on this site.',

            WebmentionTarget::resolve($target) === null => 'target is not a page that accepts webmentions.',
            default => null,
        };
    }
}
