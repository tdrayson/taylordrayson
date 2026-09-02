<?php

namespace App\Queries;

use App\Models\Attachment;
use App\Models\Subject;
use App\Support\GalleryPhotos;
use Illuminate\Support\Collection;

/**
 * Every photograph this subject is tagged on, at a point or as the camera
 * credit, newest first, shaped by {@see GalleryPhotos} against each
 * photograph's own owning entry so the caption, date and link stay correct
 * across a mix of different entries. Both roles are included: a thing tagged
 * only as the camera (a phone, a drone) still has a page worth showing what it
 * shot, and role never mixes in practice (`camera` never occurs for a person).
 */
final class SubjectPhotos
{
    /** @return array<int, array<string, mixed>> */
    public function __invoke(Subject $subject): array
    {
        return $subject->attachments()
            ->orderByDesc('attachments.created_at')
            ->get()
            ->unique('id')
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
