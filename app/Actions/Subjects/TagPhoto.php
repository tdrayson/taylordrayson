<?php

namespace App\Actions\Subjects;

use App\Enums\PhotoTagRole;
use App\Enums\SubjectKind;
use App\Models\Attachment;
use App\Models\PhotoTag;
use App\Models\Subject;
use Illuminate\Validation\ValidationException;

/**
 * Places a subject or camera credit on a photograph. Re-tagging the same
 * attachment/subject/role moves the point rather than erroring; see commit.
 */
final class TagPhoto
{
    public function __invoke(Attachment $attachment, Subject $subject, PhotoTagRole $role, ?float $x, ?float $y): PhotoTag
    {
        if ($role === PhotoTagRole::Camera) {
            $this->assertCamera($subject);
            $this->clearCamera($attachment);
        }

        return PhotoTag::query()->updateOrCreate(
            ['attachment_id' => $attachment->id, 'subject_id' => $subject->id, 'role' => $role->value],
            ['x' => $x, 'y' => $y],
        );
    }

    /** A camera is a thing: nothing else can have taken the photograph. */
    private function assertCamera(Subject $subject): void
    {
        if ($subject->kind !== SubjectKind::Thing) {
            throw ValidationException::withMessages([
                'subject_id' => 'A camera credit has to be a thing.',
            ]);
        }
    }

    /** One camera per photograph, so a new credit replaces the old one. */
    private function clearCamera(Attachment $attachment): void
    {
        PhotoTag::query()
            ->where('attachment_id', $attachment->id)
            ->where('role', PhotoTagRole::Camera->value)
            ->delete();
    }
}
