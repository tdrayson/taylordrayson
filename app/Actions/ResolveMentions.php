<?php

namespace App\Actions;

use App\Models\Article;
use App\Models\Event;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Project;
use App\Models\Subject;
use App\Presenters\CardPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve the mentions in Portable Text content. A mention stores only
 * `{kind, id}`, so renaming an entry updates every mention of it, and a dead
 * target reports `exists: false` for the renderer to handle.
 */
class ResolveMentions
{
    /**
     * The kinds a mention may point at, and the model behind each. Types that
     * arrive from a sync are absent: you do not mention last night's sleep.
     *
     * @var array<string, class-string<Model>>
     */
    private const KINDS = [
        'article' => Article::class,
        'page' => Page::class,
        'note' => Note::class,
        'project' => Project::class,
        'event' => Event::class,
        'book' => Media::class,
        // All four subject kinds (person, pet, spot, thing) share this one
        // mention kind, since a subject id is already unique across kinds.
        'subject' => Subject::class,
    ];

    /**
     * @param  array<int, array<string, mixed>>|null  $blocks
     * @return array<string, array{title: string|null, url: string|null, kind: string, exists: bool}>
     */
    public function __invoke(?array $blocks): array
    {
        $resolved = [];

        foreach ($this->references($blocks ?? []) as $key => [$kind, $id]) {
            $resolved[$key] = $this->resolve($kind, $id);
        }

        return $resolved;
    }

    /**
     * Every distinct mention in the content, keyed "kind:id" so the same target
     * mentioned twice is only looked up once.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, array{0: string, 1: int|string}>
     */
    private function references(array $blocks): array
    {
        $references = [];

        foreach ($blocks as $block) {
            // Callouts nest blocks, so their children are walked too.
            if (($block['_type'] ?? null) === 'callout') {
                $references = [...$references, ...$this->references($block['children'] ?? [])];

                continue;
            }

            foreach ($block['children'] ?? [] as $child) {
                if (($child['_type'] ?? null) !== 'mention') {
                    continue;
                }

                $kind = $child['kind'] ?? null;
                $id = $child['id'] ?? null;

                if (is_string($kind) && $id !== null) {
                    $references["{$kind}:{$id}"] = [$kind, $id];
                }
            }
        }

        return $references;
    }

    /**
     * @return array{title: string|null, url: string|null, kind: string, exists: bool}
     */
    private function resolve(string $kind, int|string $id): array
    {
        $missing = ['title' => null, 'url' => null, 'kind' => $kind, 'exists' => false];

        $class = self::KINDS[$kind] ?? null;

        if ($class === null) {
            return $missing;
        }

        $model = $class::query()->find($id);

        if ($model === null || ! $this->visible($model)) {
            return $missing;
        }

        return [
            'title' => $this->title($model),
            'url' => $model->url(),
            'kind' => $kind,
            'exists' => true,
        ];
    }

    /**
     * An unpublished target is a real record but not a public one, so to a
     * guest it reads exactly like a deleted one: no title, no link. Signed in,
     * it resolves normally, which is what makes a draft worth mentioning from
     * another draft.
     */
    private function visible(Model $model): bool
    {
        if (Auth::check()) {
            return true;
        }

        if ($model instanceof Article || $model instanceof Page) {
            return (bool) $model->published;
        }

        return true;
    }

    private function title(Model $model): ?string
    {
        if ($model instanceof Page) {
            return $model->title;
        }

        if ($model instanceof Subject) {
            return $model->name;
        }

        // Everything else is timelineable, so its card already knows how it
        // titles itself, including notes, which have no title column.
        return CardPresenter::for($model)->title;
    }
}
