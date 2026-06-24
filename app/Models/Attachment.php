<?php

namespace App\Models;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media Library's media model, stored in the `attachments` table so it does not
 * clash with the existing `media` timeline type (films/TV/books).
 */
class Attachment extends Media
{
    protected $table = 'attachments';
}
