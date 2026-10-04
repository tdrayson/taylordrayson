<?php

namespace App\Actions\Attachments;

use App\Enums\ReviewKind;
use App\Models\Attachment;

/**
 * Marks a photograph reviewed for a dimension, or puts it back. Review is a
 * filter over unreviewed photos, never a lock on editing.
 */
final class ReviewPhoto
{
    public function __invoke(Attachment $attachment, ReviewKind $kind, bool $reviewed): Attachment
    {
        if ($reviewed) {
            $attachment->setCustomProperty($kind->property(), now()->toIso8601String());
        } else {
            $attachment->forgetCustomProperty($kind->property());
        }

        $attachment->save();

        return $attachment;
    }
}
