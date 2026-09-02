<?php

namespace App\Actions\Subjects;

use App\Enums\PhotoTagRole;
use App\Models\Attachment;
use App\Models\PhotoTag;
use App\Models\Subject;

/**
 * Places a subject or camera credit on a photograph. Re-tagging the same
 * attachment/subject/role moves the point rather than erroring; see commit.
 */
final class TagPhoto
{
    public function __invoke(Attachment $attachment, Subject $subject, PhotoTagRole $role, ?float $x, ?float $y): PhotoTag
    {
        return PhotoTag::query()->updateOrCreate(
            ['attachment_id' => $attachment->id, 'subject_id' => $subject->id, 'role' => $role->value],
            ['x' => $x, 'y' => $y],
        );
    }
}
