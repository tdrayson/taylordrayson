<?php

namespace App\Http\Controllers;

use App\Actions\Subjects\TagPhoto;
use App\Actions\Subjects\UntagPhoto;
use App\Enums\PhotoTagRole;
use App\Http\Requests\PhotoTagDestroyRequest;
use App\Http\Requests\PhotoTagRequest;
use App\Models\Attachment;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;

/**
 * Places or removes a subject tag on a photograph: a point for a subject, or
 * a position-less camera credit.
 */
class PhotoSubjectController extends Controller
{
    public function store(PhotoTagRequest $request, Attachment $attachment, TagPhoto $tag): RedirectResponse
    {
        $tag(
            $attachment,
            Subject::findOrFail($request->validated('subject_id')),
            PhotoTagRole::from($request->validated('role')),
            $request->validated('x') !== null ? (float) $request->validated('x') : null,
            $request->validated('y') !== null ? (float) $request->validated('y') : null,
        );

        return back();
    }

    public function destroy(PhotoTagDestroyRequest $request, Attachment $attachment, UntagPhoto $untag): RedirectResponse
    {
        $untag(
            $attachment,
            Subject::findOrFail($request->validated('subject_id')),
            PhotoTagRole::from($request->validated('role')),
        );

        return back();
    }
}
