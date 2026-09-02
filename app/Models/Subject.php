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

class Subject extends Model
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
