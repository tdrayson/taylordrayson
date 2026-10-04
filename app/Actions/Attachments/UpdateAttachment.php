<?php

namespace App\Actions\Attachments;

use App\Models\Attachment;

/**
 * Writes a photograph's alt text and caption. Lives outside `Subjects\`
 * because it has nothing to do with subjects and outlives that feature.
 */
final class UpdateAttachment
{
    public function __invoke(Attachment $attachment, ?string $alt, ?string $caption): Attachment
    {
        $attachment
            ->setCustomProperty('alt', $alt)
            ->setCustomProperty('caption', $caption)
            ->save();

        return $attachment;
    }
}
