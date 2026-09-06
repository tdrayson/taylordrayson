<?php

namespace App\Actions\Subjects;

use App\Enums\PhotoTagRole;
use App\Models\Attachment;
use App\Models\PhotoTag;
use App\Models\Subject;

/**
 * Removes a subject or camera credit from a photograph.
 */
final class UntagPhoto
{
    public function __invoke(Attachment $attachment, Subject $subject, PhotoTagRole $role): void
    {
        PhotoTag::query()
            ->where('attachment_id', $attachment->id)
            ->where('subject_id', $subject->id)
            ->where('role', $role->value)
            ->delete();
    }
}
