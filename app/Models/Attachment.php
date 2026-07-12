<?php

namespace App\Models;

use App\Content\EntryFileRepository;
use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasFlatFile;
use Illuminate\Database\Schema\Blueprint;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media Library's media model, stored in the `attachments` table so it does not
 * clash with the existing `media` timeline type (films/TV/books).
 */
class Attachment extends Media implements DefinesContentSchema
{
    protected $table = 'attachments';

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->morphs('model');
        $table->uuid()->nullable()->unique();
        $table->string('collection_name');
        $table->string('name');
        $table->string('file_name');
        $table->string('mime_type')->nullable();
        $table->string('disk');
        $table->string('conversions_disk')->nullable();
        $table->unsignedBigInteger('size');
        $table->json('manipulations');
        $table->json('custom_properties');
        $table->json('generated_conversions');
        $table->json('responsive_images');
        $table->unsignedInteger('order_column')->nullable()->index();
        $table->nullableTimestamps();
    }

    protected static function booted(): void
    {
        static::saved(fn (Attachment $attachment) => self::syncOwnerFlatFile($attachment));
        static::deleted(fn (Attachment $attachment) => self::syncOwnerFlatFile($attachment));
    }

    private static function syncOwnerFlatFile(Attachment $attachment): void
    {
        $model = $attachment->model;

        if ($model === null) {
            return;
        }

        if (! in_array(HasFlatFile::class, class_uses_recursive($model), true)) {
            return;
        }

        if (! in_array($attachment->collection_name, ['cover', 'photos'], true)) {
            return;
        }

        app(EntryFileRepository::class)->write($model);
    }
}
