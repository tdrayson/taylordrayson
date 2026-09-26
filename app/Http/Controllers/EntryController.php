<?php

namespace App\Http\Controllers;

use App\Actions\AttachedMediaValues;
use App\Actions\BuildLinkFavicons;
use App\Actions\BuildLinkPreviews;
use App\Actions\BuildResponseContext;
use App\Data\ExportData;
use App\Data\TagLink;
use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Enums\TimelineType;
use App\Fields\AuthorableTypes;
use App\Fields\FieldRegistry;
use App\Fields\StatusFields;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Book;
use App\Models\Event;
use App\Models\Film;
use App\Models\Flight;
use App\Models\Food;
use App\Models\Fuel;
use App\Models\Note;
use App\Models\Place;
use App\Models\Tag;
use App\Models\ThisWeekWith;
use App\Models\TimelineEntry;
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;
use App\Presenters\Conversation;
use App\Presenters\Entries\FuelEntry;
use App\Presenters\ExportPresenter;
use App\Presenters\Exports\Formats\Format;
use App\Presenters\Exports\Formats\Formats;
use App\Queries\DayFood;
use App\Queries\EntryArtwork;
use App\Queries\EntryAtUrl;
use App\Queries\TripForEntry;
use App\Support\EntryMeta;
use App\Support\LocalTime;
use App\Support\OgMeta;
use App\Support\ShowTitle;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntryController extends Controller
{
    public function __construct(
        private readonly TripForEntry $tripForEntry,
        private readonly EntryAtUrl $entryAtUrl,
        private readonly DayFood $dayFood,
    ) {}

    public function show(int $year, int $month, int $day, string $slug): SymfonyResponse
    {
        $model = ($this->entryAtUrl)($year, $month, $day, $slug);

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        return $this->render($model, $model->relationLoaded('timelineEntry') ? $model->timelineEntry : null, sprintf('/%04d/%02d/%02d', $year, $month, $day));
    }

    /** An owner's draft at its own address, dated or not. */
    public function draft(string $dataset, int $id): SymfonyResponse
    {
        $definition = Datasets::for($dataset);

        if ($definition === null || ! $definition->draftable()) {
            throw new NotFoundHttpException;
        }

        $model = $definition->model()::query()->findOrFail($id);

        return $this->render($model, null, $model->occurred_at?->format('/Y/m/d'));
    }

    private function render(Model $model, ?TimelineEntry $entry, ?string $dayUrl): SymfonyResponse
    {
        if (! $model->isViewableBy(Auth::user())) {
            throw new NotFoundHttpException;
        }

        $locked = ! $model->isUnlockedFor(request());

        // Loaded here, ahead of the card and OG description below, because
        // those must render in full even while the rest of the body is locked.
        if ($model instanceof Flight) {
            $model->load('airline', 'origin', 'destination');
        }

        $card = CardPresenter::for($model);

        $props = [
            'type' => $card->type->value,
            'accent' => $card->accent,
            // Notes are title-less by definition; their card title is just
            // truncated content, which the detail body already shows in full.
            'title' => $card->type === TimelineType::Note ? null : $card->title,
            'titleTokens' => $card->titleTokens,
            ...$this->occurredFields($model),
            'og' => OgMeta::entry($entry, $model, $card),
            'dayUrl' => $dayUrl,
            'trip' => $this->trip($model),
            // The header stays for context while locked; the source link does not.
            'source' => $locked ? null : $this->source($model),
            'locked' => $locked,
            'unlockUrl' => $locked
                ? route('unlock', ['dataset' => $model->getMorphClass(), 'id' => $model->getKey()], false)
                : null,
            // A locked entry offers no formats: each would 404, and there is
            // nothing left to put in one.
            'formats' => $locked ? [] : $this->formats(ExportPresenter::for($model)),
        ];

        $response = Inertia::render('Entry', [
            ...$props,
            ...($locked ? ['entry' => null] : $this->bodyProps($model)),
        ])->toResponse(request());

        // A private page differs per session, so no shared cache may keep it.
        if ($model->status === EntryStatus::Private) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /**
     * The body of an entry page: the record itself plus every editing and
     * media prop, held back entirely while the entry is locked.
     *
     * @return array<string, mixed>
     */
    private function bodyProps(Model $model): array
    {
        if ($model instanceof Appearance || $model instanceof Activity || $model instanceof Note) {
            $model->load('media');
        }

        $authorable = AuthorableTypes::forModel($model);

        return [
            'entry' => $model instanceof Food
                ? ($this->dayFood)($model)
                : $this->entryPayload($model),
            // Server-rendered, not fetched: the replies and mentions carry
            // h-cite markup that other IndieWeb sites parse, and a reader with
            // no JS should still see what people said. Only the reply *form*
            // is loaded on demand.
            'conversation' => Conversation::shownFor($model, request()),
            'polyline' => data_get($model, 'meta.polyline'),
            'editing' => Auth::check() && request()->has('edit'),
            // A synced type edits its status alone: a field the sync also writes
            // would be silently overwritten by the next run.
            'editAction' => match (true) {
                ! Auth::check() => null,
                $authorable !== null => "/entries/{$authorable}/{$model->getKey()}",
                default => route('entries.status', ['dataset' => $model->getMorphClass(), 'id' => $model->getKey()], false),
            },
            'fields' => match (true) {
                ! Auth::check() => [],
                $authorable !== null => FieldRegistry::for($model),
                default => StatusFields::for($model),
            },
            'password' => Auth::check() ? $model->getAttribute('password') : null,
            // What the media fields already hold, so the editor opens showing
            // the attachments rather than an empty picker.
            'media' => Auth::check() && $authorable !== null
                ? app(AttachedMediaValues::class)($model, FieldRegistry::for($model))
                : [],
            'linkPreviews' => $model instanceof Article || $model instanceof Note
                ? app(BuildLinkPreviews::class)($model->resolvedContent())
                : [],
            'linkFavicons' => $model instanceof Article || $model instanceof Note
                ? (new BuildLinkFavicons)($model->resolvedContent())
                : [],
            // Stream series are large, so they're excluded from the main
            // entry payload and only sent once a profile chart is scrolled
            // into view and requests this deferred prop.
            'profile' => $model instanceof Activity
                ? Inertia::defer(fn (): array => [
                    'heart_rate' => $model->heart_rate,
                    'altitude' => $model->altitude,
                    'speed' => $model->speed,
                    'track' => $model->track,
                ])
                : null,
        ];
    }

    /**
     * The trip this entry falls inside, for the "part of" backlink, or null
     * when it happened outside every trip window.
     *
     * @return array{title: string, url: string}|null
     */
    private function trip(Model $model): ?array
    {
        $trip = ($this->tripForEntry)($model);

        return $trip === null ? null : ['title' => $trip->title, 'url' => $trip->url()];
    }

    /**
     * Local-time display fields for the entry header, all null for an undated draft.
     *
     * @return array{occurredAt: ?string, occurredLabel: ?string, occurredOffset: ?string}
     */
    private function occurredFields(Model $model): array
    {
        if ($model->occurred_at === null) {
            return ['occurredAt' => null, 'occurredLabel' => null, 'occurredOffset' => null];
        }

        $local = LocalTime::for($model->occurredAtForDisplay(), $model->timezone());

        return [
            'occurredAt' => $local['iso'],
            'occurredLabel' => $local['label'],
            'occurredOffset' => $local['offset'],
        ];
    }

    /**
     * Serialise a timeline model for its detail page, dropping audit timestamps
     * and adding the resolved thumbnail URL for media appearances.
     *
     * @return array<string, mixed>
     */
    private function entryPayload(Model $model): array
    {
        // Tags are now a relation rather than a plain attribute; models using
        // HasTags need a {name, slug} shape so entry pages can link each chip
        // to its /tags/{slug} page, not the serialised Tag models toArray()
        // would otherwise produce.
        if (method_exists($model, 'tagNames')) {
            $model->loadMissing('tags');
        }

        // `timeline_entry` is dropped because show() sets that relation and it
        // carries `entry` -- a second, unfiltered copy of this very
        // model. Serialised, it defeated every exclusion below it: the stream
        // arrays were shipped despite being excluded here and deferred
        // separately, and meta went out whole. Nothing on the client reads it.
        $data = Arr::except($model->toArray(), [
            'created_at', 'updated_at', 'heart_rate', 'altitude', 'speed', 'track', 'timeline_entry',
        ]);

        // meta is the one remaining attribute the syncs fill with more than the
        // page shows, so it is narrowed to the keys EntryMeta names rather than
        // sent whole. See that class for why the default points this way.
        if (array_key_exists('meta', $data)) {
            $data['meta'] = EntryMeta::published($model);
        }

        if (method_exists($model, 'tagNames')) {
            $data['tags'] = $model->tags
                ->map(fn (Tag $tag): array => TagLink::for($tag)->toArray())
                ->all();
        }

        if ($model instanceof Appearance) {
            $data['thumbnail'] = $model->thumbnailUrl();
            $data['thumbnailSrcset'] = $model->thumbnailSrcset();
        }

        // Overwritten rather than added alongside: the episode page reads these
        // three columns directly, so pointing them at the mirrored copy here
        // means every consumer prefers local storage without the page having to
        // know a mirror exists.
        if ($model instanceof ThisWeekWith) {
            $data['audio_url'] = $model->audio_url;
            $data['cover_image'] = $model->wideArtworkSrc();
            $data['thumbnail'] = $model->squareArtworkSrc();
        }

        if ($model instanceof Article) {
            $data['cover'] = $model->coverPhoto();
        }

        if ($model instanceof Article || $model instanceof Note) {
            $data['content'] = $model->resolvedContent();

            // The unresolved document, for the inline editor to seed its form
            // from. Sent only to a signed-in viewer: editing is auth-gated
            // anyway, and a guest has no use for a second copy of the body.
            // Without this the editor would round-trip the resolved text and
            // permanently overwrite a dynamic tag the first time it saves.
            if (Auth::check()) {
                $data['rawContent'] = $model->content;
            }
        }

        // Notes and articles are the only types that answer somebody: every
        // other one records something that happened.
        if ($model instanceof Article || $model instanceof Note) {
            $data['response'] = app(BuildResponseContext::class)($model)?->toArray();
        }

        if ($model instanceof Activity || $model instanceof Note || $model instanceof Event || $model instanceof Place) {
            $data['photos'] = $model->galleryPhotos();
        }

        // The show a TV episode belongs to has its own page gathering every
        // watched episode, so the detail row links to it rather than printing
        // the title as dead text. Null for a film, a book, or a show we hold no
        // TvShow row for.
        if ($model instanceof Film || $model instanceof TvEpisode || $model instanceof Book) {
            $data = [...$data, ...(new EntryArtwork)($model)];
            $data['showTitle'] = $model instanceof TvEpisode ? ShowTitle::for($model) : null;
            $data['showUrl'] = $data['showTitle'] === null ? null : $model->tvShow?->url();
        }

        // A venue's category is already a taxonomy with its own archive, so the
        // detail page links to it rather than printing it as dead text.
        if ($model instanceof Place && $model->type !== null) {
            $data['categoryHref'] = '/'.TypeRegistry::find('place')['taxonomy']['base'].'/'.Str::slug($model->type);
        }

        if ($model instanceof Event) {
            $address = $this->eventAddress($model);

            $data['location'] = $model->latitude !== null && $model->longitude !== null
                ? [
                    'lat' => (float) $model->latitude,
                    'lng' => (float) $model->longitude,
                    'address' => $address,
                    'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($address),
                ]
                : null;

            // Multi-day badge data ({label, days}), null for single-day events.
            // toArray() only serialises DB columns, so dateRange() (a computed
            // method, not an accessor) needs adding to the payload explicitly.
            $data['range'] = $model->dateRange();
        }

        if (! $model instanceof Event && $model->getAttribute('latitude') !== null && $model->getAttribute('longitude') !== null) {
            $address = trim(implode(', ', array_filter([
                $model->getAttribute('station_name') ?? $model->getAttribute('venue_name'),
                $model->getAttribute('address'),
                $model->getAttribute('postcode'),
                $model->getAttribute('city'),
            ])));

            $data['location'] = [
                'lat' => (float) $model->getAttribute('latitude'),
                'lng' => (float) $model->getAttribute('longitude'),
                'address' => $address,
                'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($address !== '' ? $address : $model->getAttribute('latitude').','.$model->getAttribute('longitude')),
            ];
        }

        if ($model instanceof Fuel) {
            $data = [...$data, ...(new FuelEntry)->present($model)];
        }

        return $data;
    }

    /**
     * Best available address string for maps: the geocoded address stored in
     * meta, else the venue/city/country the event carries.
     */
    private function eventAddress(Event $event): string
    {
        return data_get($event->meta, 'address')
            ?: collect([$event->venue_name, $event->city, $event->country])->filter()->implode(', ');
    }

    /**
     * Where this entry's data came from, with a link back to the original when available.
     *
     * @return array{platform: string, url: ?string}|null
     */
    private function source(Model $model): ?array
    {
        $platform = $model->source ?? null;

        if (! $platform) {
            return null;
        }

        return [
            'platform' => $platform,
            'url' => $model->platform_url,
        ];
    }

    /**
     * Every format this export supports, shaped for AppHead's alternate
     * links and the footer's format list.
     *
     * @return list<array{extension: string, type: string, label: string, url: string}>
     */
    private function formats(ExportData $export): array
    {
        return array_values(array_map(
            fn (Format $format): array => [
                'extension' => $format->format()->value,
                'type' => $format->format()->contentType(),
                'label' => $format->format()->label(),
                'url' => $export->url.'.'.$format->format()->value,
            ],
            Formats::for($export),
        ));
    }
}
