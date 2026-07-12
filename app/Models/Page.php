<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasFlatFile;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

/**
 * A standalone, CP-managed content page (e.g. /sleep-score), rendered with the
 * article layout. Not a timeline entry: pages are routed by slug, not by date.
 */
#[Fillable([
    'ulid',
    'title',
    'slug',
    'excerpt',
    'content',
    'published',
])]
class Page extends Model implements DefinesContentSchema
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasFlatFile;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published' => 'boolean',
        ];
    }

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->string('title');
        $table->string('slug')->unique();
        $table->string('excerpt')->nullable();
        $table->text('content');
        $table->boolean('published')->default(false);
        $table->timestamps();
    }

    public function slug(): string
    {
        return (string) ($this->getAttributes()['slug'] ?? 'page');
    }

    public function flatFileType(): string
    {
        return 'page';
    }

    public function flatFileExtension(): string
    {
        return 'json';
    }

    public function flatFileDirectory(): string
    {
        return 'pages';
    }

    public function flatFileBody(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'title' => $attributes['title'] ?? null,
            'excerpt' => $attributes['excerpt'] ?? null,
            'published' => (bool) ($attributes['published'] ?? false),
            'content' => $this->content,
        ];
    }
}
