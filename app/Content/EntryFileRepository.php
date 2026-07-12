<?php

namespace App\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Yaml\Yaml;

class EntryFileRepository
{
    public function __construct(private ContentPath $paths) {}

    public function write(Model $model): void
    {
        $path = $this->paths->absolute($model);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->serialize($model));
    }

    public function delete(Model $model): void
    {
        $this->deletePath($this->paths->absolute($model));
    }

    public function deleteOriginal(Model $model): void
    {
        $this->deletePath($this->paths->absolute($model, original: true));
    }

    /**
     * @return array{frontmatter: array<string, mixed>, body: mixed}
     */
    public function parseFile(string $absolutePath): array
    {
        $contents = File::get($absolutePath);

        if (str_ends_with(strtolower($absolutePath), '.json')) {
            /** @var array<string, mixed> $document */
            $document = json_decode($contents, true) ?? [];
            $body = $document['content'] ?? null;
            unset($document['content']);

            return [
                'frontmatter' => $document,
                'body' => $body,
            ];
        }

        return $this->parseMarkdown($contents);
    }

    /**
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function parse(string $contents): array
    {
        return $this->parseMarkdown($contents);
    }

    public function serialize(Model $model): string
    {
        if ($model->flatFileExtension() === 'json') {
            return json_encode(
                $this->document($model),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            )."\n";
        }

        $frontmatter = $this->document($model);
        unset($frontmatter['content']);

        $yaml = Yaml::dump(
            array_filter(
                $frontmatter,
                fn (mixed $value): bool => $value !== null && $value !== '',
            ),
            4,
            2,
            Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
        );

        $body = $model->flatFileBody();

        return '---'."\n".$yaml.'---'."\n\n".$body.($body === '' ? '' : "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function document(Model $model): array
    {
        $attributes = $model->getAttributes();
        $photos = method_exists($model, 'flatFilePhotoNames')
            ? $model->flatFilePhotoNames()
            : [];

        return array_filter(
            [
                'id' => $attributes['ulid'] ?? null,
                'type' => $model->flatFileType(),
                'occurred_at' => $model->occurred_at?->toIso8601String(),
                'timezone' => $attributes['timezone'] ?? null,
                'slug' => method_exists($model, 'flatFileSlug')
                    ? $model->flatFileSlug()
                    : (method_exists($model, 'slug') ? $model->slug() : ($attributes['slug'] ?? null)),
                'photos' => $photos === [] ? null : $photos,
                ...$model->flatFileMeta(),
            ],
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    private function parseMarkdown(string $contents): array
    {
        $document = YamlFrontMatter::parse($contents);

        /** @var array<string, mixed> $frontmatter */
        $frontmatter = $document->matter();

        return [
            'frontmatter' => $frontmatter,
            'body' => trim($document->body()),
        ];
    }

    private function deletePath(string $path): void
    {
        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
