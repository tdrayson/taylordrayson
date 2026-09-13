<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\ReviewPhoto;
use App\Enums\ReviewKind;
use App\Http\Requests\PhotoReviewRequest;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;

/**
 * Marks a photograph reviewed for a dimension, or puts it back.
 */
class PhotoReviewController extends Controller
{
    public function store(PhotoReviewRequest $request, Attachment $attachment, ReviewPhoto $review): RedirectResponse
    {
        $review($attachment, ReviewKind::from($request->validated('kind')), true);

        return back();
    }

    public function destroy(PhotoReviewRequest $request, Attachment $attachment, ReviewPhoto $review): RedirectResponse
    {
        $review($attachment, ReviewKind::from($request->validated('kind')), false);

        return back();
    }
}
