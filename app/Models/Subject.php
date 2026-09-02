<?php

namespace App\Models;

use App\Data\SubjectIdentities;
use App\Data\SubjectMeta;
use App\Data\SubjectRules;
use App\Enums\SubjectCategory;
use App\Enums\SubjectKind;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;

class Subject extends Model implements HasMedia
{
    use HasAttachments, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'kind', 'category', 'name', 'slug', 'bio',
        'meta', 'identities', 'rules', 'latitude', 'longitude',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'kind' => SubjectKind::class,
            'category' => SubjectCategory::class,
            'bio' => 'array',
            'meta' => SubjectMeta::class,
            'identities' => SubjectIdentities::class,
            'rules' => SubjectRules::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function url(): string
    {
        return "/life/{$this->kind->segment()}/{$this->slug}";
    }

    /** @return array{src: string, srcset: ?string, full: string, alt: ?string}|null */
    public function coverPhoto(): ?array
    {
        $media = $this->getFirstMedia('cover');

        if ($media === null) {
            return null;
        }

        return [
            'src' => $media->getUrl('card'),
            'srcset' => $media->getSrcset('card') ?: null,
            'full' => $media->getUrl(),
            'alt' => $media->getCustomProperty('alt'),
        ];
    }

    /** Every attachment this subject is tagged on, with its position. */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class)
            ->using(PhotoTag::class)
            ->withPivot(['role', 'x', 'y'])
            ->withTimestamps();
    }

    /** @param class-string<Model> $type */
    public function entriesOf(string $type): MorphToMany
    {
        return $this->morphedByMany($type, 'subjectable');
    }
}
