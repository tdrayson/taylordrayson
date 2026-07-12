<?php

namespace App\Content;

use App\Models\Calorie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class EntryFileImporter
{
    public function __construct(private EntryFileRepository $files) {}

    public function importPath(string $absolutePath): Model
    {
        $parsed = $this->files->parseFile($absolutePath);
        $frontmatter = $parsed['frontmatter'];
        $type = $frontmatter['type'] ?? null;

        if (! is_string($type) || ! ContentTypes::has($type)) {
            throw new \InvalidArgumentException("Unsupported or missing content type in {$absolutePath}");
        }

        if ($type === 'calorie') {
            return $this->importCalorieDay($frontmatter);
        }

        $class = ContentTypes::modelFor($type);
        $ulid = $frontmatter['id'] ?? (string) Str::ulid();

        /** @var Model $model */
        $model = $class::query()->firstOrNew(['ulid' => $ulid]);
        $model->fill($this->attributesFor($type, $frontmatter, $parsed['body']));
        $model->setAttribute('ulid', $ulid);
        $model->save();

        return $model;
    }

    /**
     * @return list<Model>
     */
    public function importAll(): array
    {
        $root = (string) config('content.path');

        if (! File::isDirectory($root)) {
            return [];
        }

        $models = [];
        $finder = Finder::create()
            ->files()
            ->in($root)
            ->name(['*.md', '*.json'])
            ->sortByName();

        foreach ($finder as $file) {
            $parsed = $this->files->parseFile($file->getPathname());
            $type = $parsed['frontmatter']['type'] ?? null;

            if (! is_string($type) || ! ContentTypes::has($type)) {
                continue;
            }

            $models[] = $this->importPath($file->getPathname());
        }

        return $models;
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    private function importCalorieDay(array $frontmatter): Calorie
    {
        $day = Carbon::parse($frontmatter['occurred_at'] ?? now())->toDateString();
        $timezone = $frontmatter['timezone'] ?? null;
        $items = is_array($frontmatter['items'] ?? null) ? $frontmatter['items'] : [];
        $keptUlids = [];
        $first = null;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ulid = isset($item['id']) && filled($item['id'])
                ? (string) $item['id']
                : (string) Str::ulid();
            $keptUlids[] = $ulid;

            $calorie = Calorie::query()->firstOrNew(['ulid' => $ulid]);
            $calorie->fill([
                'occurred_at' => Carbon::parse($item['occurred_at'] ?? $frontmatter['occurred_at'] ?? now()),
                'timezone' => $timezone,
                'source' => $item['source'] ?? null,
                'source_id' => $item['source_id'] ?? null,
                'name' => $item['name'] ?? 'Unknown',
                'icon' => $item['icon'] ?? null,
                'meal' => $item['meal'] ?? 'snacks',
                'quantity' => $item['quantity'] ?? 1,
                'units' => $item['units'] ?? 'serving',
                'calories' => $item['calories'] ?? 0,
                'fat' => $item['fat'] ?? null,
                'protein' => $item['protein'] ?? null,
                'carbs' => $item['carbs'] ?? null,
                'saturated_fat' => $item['saturated_fat'] ?? null,
                'sugars' => $item['sugars'] ?? null,
                'fibre' => $item['fibre'] ?? null,
                'cholesterol' => $item['cholesterol'] ?? null,
                'sodium' => $item['sodium'] ?? null,
            ]);
            $calorie->setAttribute('ulid', $ulid);
            $calorie->save();

            $first ??= $calorie;
        }

        Calorie::query()
            ->whereDate('occurred_at', $day)
            ->when(
                $keptUlids !== [],
                fn ($query) => $query->whereNotIn('ulid', $keptUlids),
                fn ($query) => $query,
            )
            ->get()
            ->each(fn (Calorie $calorie) => $calorie->delete());

        if ($first === null) {
            throw new \InvalidArgumentException('Calorie day file has no items');
        }

        return $first;
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     * @return array<string, mixed>
     */
    private function attributesFor(string $type, array $frontmatter, mixed $body): array
    {
        return match ($type) {
            'note' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'slug' => $frontmatter['slug'] ?? 'note',
                'content' => is_string($body) ? $body : '',
            ],
            'article' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'slug' => $frontmatter['slug'] ?? 'article',
                'title' => $frontmatter['title'] ?? 'Untitled',
                'excerpt' => $frontmatter['excerpt'] ?? null,
                'published' => (bool) ($frontmatter['published'] ?? false),
                'content' => is_array($body) ? $body : [],
            ],
            'project' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'slug' => $frontmatter['slug'] ?? 'project',
                'title' => $frontmatter['title'] ?? 'Untitled',
                'description' => $frontmatter['description'] ?? null,
                'long_description' => is_string($body) ? $body : ($frontmatter['long_description'] ?? null),
                'url' => $frontmatter['url'] ?? null,
                'github_url' => $frontmatter['github_url'] ?? null,
                'status' => $frontmatter['status'] ?? 'active',
                'featured' => (bool) ($frontmatter['featured'] ?? false),
            ],
            'page' => [
                'slug' => $frontmatter['slug'] ?? 'page',
                'title' => $frontmatter['title'] ?? 'Untitled',
                'excerpt' => $frontmatter['excerpt'] ?? null,
                'published' => (bool) ($frontmatter['published'] ?? false),
                'content' => is_array($body) ? $body : [],
            ],
            'appearance' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'type' => $frontmatter['kind'] ?? 'podcast',
                'title' => $frontmatter['title'] ?? 'Untitled',
                'show_name' => $frontmatter['show_name'] ?? null,
                'url' => $frontmatter['url'] ?? null,
                'video_url' => $frontmatter['video_url'] ?? null,
                'audio_url' => $frontmatter['audio_url'] ?? null,
                'duration' => $frontmatter['duration'] ?? null,
                'description' => is_string($body) ? $body : '',
            ],
            'event' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'ends_at' => isset($frontmatter['ends_at']) ? Carbon::parse($frontmatter['ends_at']) : null,
                'timezone' => $frontmatter['timezone'] ?? null,
                'type' => $frontmatter['kind'] ?? 'event',
                'name' => $frontmatter['name'] ?? 'Untitled',
                'all_day' => (bool) ($frontmatter['all_day'] ?? false),
                'organiser' => $frontmatter['organiser'] ?? null,
                'venue_name' => $frontmatter['venue_name'] ?? null,
                'city' => $frontmatter['city'] ?? null,
                'country' => $frontmatter['country'] ?? null,
                'latitude' => $frontmatter['latitude'] ?? null,
                'longitude' => $frontmatter['longitude'] ?? null,
                'url' => $frontmatter['url'] ?? null,
                'meta' => is_array($frontmatter['meta'] ?? null) ? $frontmatter['meta'] : null,
                'description' => is_string($body) ? $body : '',
            ],
            'checkin' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'venue_name' => $frontmatter['venue_name'] ?? 'Unknown',
                'category' => $frontmatter['category'] ?? null,
                'address' => $frontmatter['address'] ?? null,
                'city' => $frontmatter['city'] ?? null,
                'county' => $frontmatter['county'] ?? null,
                'country' => $frontmatter['country'] ?? null,
                'latitude' => $frontmatter['latitude'] ?? null,
                'longitude' => $frontmatter['longitude'] ?? null,
                'is_mayor' => (bool) ($frontmatter['is_mayor'] ?? false),
                'source' => $frontmatter['source'] ?? null,
                'source_id' => $frontmatter['source_id'] ?? null,
                'description' => is_string($body) ? $body : '',
            ],
            'flight' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'flight_number' => $frontmatter['flight_number'] ?? null,
                'airline_icao' => $frontmatter['airline_icao'] ?? null,
                'origin_iata' => $frontmatter['origin_iata'] ?? null,
                'destination_iata' => $frontmatter['destination_iata'] ?? null,
                'distance' => $frontmatter['distance'] ?? null,
                'duration' => $frontmatter['duration'] ?? null,
                'departure_timezone' => $frontmatter['departure_timezone'] ?? null,
                'arrival_timezone' => $frontmatter['arrival_timezone'] ?? null,
                'cabin_class' => $frontmatter['cabin_class'] ?? null,
                'reason' => $frontmatter['reason'] ?? null,
                'meta' => is_array($frontmatter['meta'] ?? null) ? $frontmatter['meta'] : null,
            ],
            'fuel' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'vehicle_id' => $frontmatter['vehicle_id'] ?? null,
                'litres' => $frontmatter['litres'] ?? null,
                'cost' => $frontmatter['cost'] ?? null,
                'fuel_card_cost' => $frontmatter['fuel_card_cost'] ?? null,
                'price_per_litre' => $frontmatter['price_per_litre'] ?? null,
                'odometer' => $frontmatter['odometer'] ?? null,
                'fuel_station_id' => $frontmatter['fuel_station_id'] ?? null,
            ],
            'activity' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'type' => $frontmatter['kind'] ?? 'activity',
                'name' => $frontmatter['name'] ?? null,
                'duration' => $frontmatter['duration'] ?? null,
                'calories' => $frontmatter['calories'] ?? null,
                'distance' => $frontmatter['distance'] ?? null,
                'average_heart_rate' => $frontmatter['average_heart_rate'] ?? null,
                'max_heart_rate' => $frontmatter['max_heart_rate'] ?? null,
                'heart_rate' => is_array($frontmatter['heart_rate'] ?? null) ? $frontmatter['heart_rate'] : null,
                'source' => $frontmatter['source'] ?? null,
                'source_id' => $frontmatter['source_id'] ?? null,
                'meta' => is_array($frontmatter['meta'] ?? null) ? $frontmatter['meta'] : null,
                'description' => is_string($body) ? $body : '',
            ],
            'sleep' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'bedtime' => isset($frontmatter['bedtime']) ? Carbon::parse($frontmatter['bedtime']) : null,
                'wake_time' => isset($frontmatter['wake_time']) ? Carbon::parse($frontmatter['wake_time']) : null,
                'duration' => $frontmatter['duration'] ?? null,
                'awake' => $frontmatter['awake'] ?? null,
                'rem' => $frontmatter['rem'] ?? null,
                'core' => $frontmatter['core'] ?? null,
                'deep' => $frontmatter['deep'] ?? null,
                'source' => $frontmatter['source'] ?? null,
                'stages' => is_array($frontmatter['stages'] ?? null) ? $frontmatter['stages'] : null,
                'score' => $frontmatter['score'] ?? null,
                'duration_score' => $frontmatter['duration_score'] ?? null,
                'bedtime_score' => $frontmatter['bedtime_score'] ?? null,
                'interruption_score' => $frontmatter['interruption_score'] ?? null,
            ],
            'podcast' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'season_number' => $frontmatter['season_number'] ?? 1,
                'episode_number' => $frontmatter['episode_number'] ?? 1,
                'topic' => $frontmatter['topic'] ?? null,
                'transcript' => $frontmatter['transcript'] ?? null,
                'duration' => $frontmatter['duration'] ?? null,
                'audio_url' => $frontmatter['audio_url'] ?? null,
                'video_url' => $frontmatter['video_url'] ?? null,
                'thumbnail' => $frontmatter['thumbnail'] ?? null,
                'cover_image' => $frontmatter['cover_image'] ?? null,
                'show_notes' => is_string($body) ? $body : '',
            ],
            'media' => [
                'occurred_at' => Carbon::parse($frontmatter['occurred_at'] ?? now()),
                'timezone' => $frontmatter['timezone'] ?? null,
                'type' => $frontmatter['kind'] ?? 'film',
                'title' => $frontmatter['title'] ?? 'Untitled',
                'rating' => $frontmatter['rating'] ?? null,
                'source' => $frontmatter['source'] ?? null,
                'source_id' => $frontmatter['source_id'] ?? null,
                'meta' => is_array($frontmatter['meta'] ?? null) ? $frontmatter['meta'] : null,
            ],
            default => [],
        };
    }
}
