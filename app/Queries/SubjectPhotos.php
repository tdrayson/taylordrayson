<?php

namespace App\Queries;

use App\Enums\PhotoTagRole;
use App\Models\Attachment;
use App\Models\Subject;
use App\Support\GalleryPhotos;
use Illuminate\Support\Collection;

/**
 * Every photograph this subject is tagged in at a point, newest first, shaped
 * by {@see GalleryPhotos} against each photograph's own owning entry so the
 * caption, date and link stay correct across a mix of different entries.
 */
final class SubjectPhotos
{
    /** @return array<int, array<string, mixed>> */
    public function __invoke(Subject $subject): array
    {
        return $subject->attachments()
            ->wherePivot('role', PhotoTagRole::Subject->value)
            ->orderByDesc('attachments.created_at')
            ->get()
            ->groupBy(fn (Attachment $attachment): string => "{$attachment->model_type}:{$attachment->model_id}")
            ->flatMap(function (Collection $media): array {
                $owner = $media->first()->model;

                if (! GalleryPhotos::contributesPhotos($owner)) {
                    return [];
                }

                return GalleryPhotos::shape($owner, $media);
            })
            ->values()
            ->all();
    }
}
