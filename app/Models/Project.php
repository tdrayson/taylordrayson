<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'ulid',
    'occurred_at',
    'title',
    'slug',
    'description',
    'long_description',
    'url',
    'github_url',
    'status',
    'featured',
])]
class Project extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<ProjectFactory> */
    use HasAttachments, HasFactory, HasFlatFile, HasTags, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'featured' => 'boolean',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->string('title');
        $table->string('slug');
        $table->text('description')->nullable();
        $table->text('long_description')->nullable();
        $table->string('url')->nullable();
        $table->string('github_url')->nullable();
        $table->string('status')->default('active');
        $table->boolean('featured')->default(false);
        $table->timestamps();
    }

    public function slug(): string
    {
        return $this->getAttribute('slug');
    }

    public function flatFileType(): string
    {
        return 'project';
    }

    public function flatFileBody(): string
    {
        return (string) ($this->getAttributes()['long_description'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'title' => $attributes['title'] ?? null,
            'description' => $attributes['description'] ?? null,
            'url' => $attributes['url'] ?? null,
            'github_url' => $attributes['github_url'] ?? null,
            'status' => $attributes['status'] ?? null,
            'featured' => (bool) ($attributes['featured'] ?? false),
        ];
    }

    public function card(): array
    {
        return [
            'type' => 'project',
            'icon' => 'rocket',
            'title' => $this->title,
            'subtitle' => $this->description,
            'occurred_at' => $this->occurred_at,
            'accent' => 'project',
            'meta' => [],
        ];
    }
}
