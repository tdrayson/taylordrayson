<?php

namespace App\Http\Controllers;

use App\Fields\AuthorableTypes;
use App\Fields\FieldRegistry;
use App\Fields\FieldRules;
use App\Presenters\CardPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creating and saving hand-written entries.
 *
 * Every type goes through here rather than through a controller of its own:
 * {@see AuthorableTypes} says which model and actions to reach for, and
 * {@see FieldRegistry} says what the form contains, so adding a type needs no
 * change to this file.
 *
 * Guarded by the auth middleware on the routes.
 */
class AuthoringController extends Controller
{
    /**
     * The quick-add hub: a field to start writing in and a tile per type.
     */
    public function new(?string $type = null): Response
    {
        if ($type !== null && ! AuthorableTypes::has($type)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('New', [
            'types' => AuthorableTypes::forPicker(),
            'type' => $type,
            'fields' => $type === null ? [] : FieldRegistry::for($this->blank($type)),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $definition = $this->definition($type);
        $fields = FieldRegistry::for($this->blank($type));

        $attributes = $request->validate(FieldRules::for($fields, creating: true));

        $model = app($definition['create'])($this->expand($attributes));

        return redirect($this->urlFor($model).'?edit');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $definition = $this->definition($type);
        $model = $definition['model']::query()->findOrFail($id);
        $fields = FieldRegistry::for($model);

        $attributes = $request->validate(FieldRules::for($fields, creating: false));

        app($definition['update'])($model, $this->expand($attributes));

        return back();
    }

    /**
     * Everything unpublished, grouped by type.
     *
     * Queried off the models rather than off timeline entries, which drafts
     * deliberately do not have.
     */
    public function drafts(): Response
    {
        $groups = [];

        foreach (AuthorableTypes::draftable() as $type) {
            $definition = AuthorableTypes::get($type);

            $rows = $definition['model']::query()
                ->where('published', false)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Model $model): array => [
                    'title' => $this->titleFor($model),
                    'url' => $this->urlFor($model).'?edit',
                    'updated' => $model->updated_at?->toIso8601String(),
                ])
                ->all();

            if ($rows !== []) {
                $groups[] = ['type' => $type, 'label' => $definition['label'], 'rows' => $rows];
            }
        }

        return Inertia::render('Drafts', ['groups' => $groups]);
    }

    /**
     * Turn dotted field names back into the nested arrays the actions expect,
     * so `meta.author` arrives as `['meta' => ['author' => ...]]`.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function expand(array $attributes): array
    {
        $expanded = [];

        foreach ($attributes as $key => $value) {
            data_set($expanded, $key, $value);
        }

        return $expanded;
    }

    /**
     * @return array{model: class-string<Model>, create: class-string, update: class-string, label: string, icon: string, draftable: bool}
     */
    private function definition(string $type): array
    {
        $definition = AuthorableTypes::get($type);

        if ($definition === null) {
            throw new NotFoundHttpException;
        }

        return $definition;
    }

    /**
     * An unsaved instance, purely so FieldRegistry can dispatch on its class.
     */
    private function blank(string $type): Model
    {
        $class = $this->definition($type)['model'];

        return new $class;
    }

    private function urlFor(Model $model): string
    {
        return method_exists($model, 'url') ? $model->url() : '/'.$model->getAttribute('slug');
    }

    private function titleFor(Model $model): string
    {
        $title = $model->getAttribute('title');

        return is_string($title) && $title !== ''
            ? $title
            : (method_exists($model, 'url') ? CardPresenter::for($model)->title : 'Untitled');
    }
}
