<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * The `meta` keys an entry publishes to its own detail page.
 *
 * The `meta` column is a loose bag filled by the syncs with everything the
 * source API returned, most of which no page displays: Strava's power and
 * cadence figures, a flight's booking reference, a ticket's order number.
 * Serialising the model handed the browser all of it, so anything private had
 * to be remembered and removed one key at a time. Nobody remembered `pnr`.
 *
 * This inverts that. A key reaches the browser only by being named here, so a
 * field a sync starts storing tomorrow stays on the server until someone
 * decides to publish it. Forgetting now means a value does not appear on the
 * page, which is visible, rather than a private value being published, which
 * is not.
 *
 * Keep each list to what that type's detail component actually reads.
 */
final class EntryMeta
{
    /**
     * @var array<class-string<Model>, list<string>>
     */
    private const PUBLISHED = [
        // ActivityDetail.vue: the route, the strength-set table, elevation.
        Activity::class => ['polyline', 'sets', 'total_elevation_gain'],

        // FlightDetail.vue. Deliberately excludes `pnr`: a booking reference
        // plus a surname is enough to open the booking on many airline sites.
        Flight::class => ['aircraft', 'seat', 'seat_type'],

        // EventDetail.vue. The address is published through `location`, built
        // separately by the controller; `order_no` and `price` are not shown.
        Event::class => ['seat'],

        // MediaDetail.vue. `tmdb` is public film metadata from TMDB, carried
        // whole because the genres list is read from inside it.
        Media::class => ['year', 'runtime', 'season', 'episode', 'show_title', 'author', 'isbn', 'genres', 'tmdb'],
    ];

    /**
     * The publishable subset of a model's meta. Empty for any type not listed
     * above, so an unregistered model publishes nothing rather than everything.
     *
     * @return array<string, mixed>
     */
    public static function published(Model $model): array
    {
        $keys = self::PUBLISHED[$model::class] ?? [];

        if ($keys === []) {
            return [];
        }

        return Arr::only($model->getAttribute('meta') ?? [], $keys);
    }
}
