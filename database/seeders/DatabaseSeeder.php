<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Asset;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const TV_SHOWS = [
        'Breaking Bad',
        'The Bear',
        'Severance',
        'The Last of Us',
        'Shogun',
        'Slow Horses',
        'Succession',
        'The White Lotus',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Taylor Drayson',
            'email' => 'taylor@example.com',
        ]);

        $this->seedOldCalorieStreak();
        $this->seedRecentData();
        $this->seedOneOffData();
    }

    /**
     * Seed ~2,145 days of minimal calorie data for the streak widget.
     * Uses withoutEvents so observers don't fire for old data.
     */
    private function seedOldCalorieStreak(): void
    {
        Model::withoutEvents(function (): void {
            $startDate = now()->subDays(2145 + 180);

            for ($i = 0; $i < 2145; $i++) {
                $date = $startDate->copy()->addDays($i);

                Calorie::factory()->create([
                    'occurred_at' => $date->setTime(12, 0),
                ]);
            }
        });
    }

    /**
     * Seed 6 months of recent data with observers firing normally.
     */
    private function seedRecentData(): void
    {
        $podcastEpisode = 1;

        for ($dayOffset = 179; $dayOffset >= 0; $dayOffset--) {
            $date = now()->subDays($dayOffset)->startOfDay();

            $this->seedSleep($date);
            $this->seedCalories($date);
            $this->seedActivities($date);
            $this->seedCheckins($date);
            $this->seedNotes($date);
            $this->seedMediaFilms($date);
            $this->seedMediaTvEpisodes($date);
            $podcastEpisode = $this->seedPodcasts($date, $podcastEpisode);
            $this->seedArticles($date);
            $this->seedFlights($date);
            $this->seedFuel($date);
            $this->seedEvents($date);
            $this->seedMediaBooks($date);
        }
    }

    /**
     * Seed one-off data: projects and appearances.
     */
    private function seedOneOffData(): void
    {
        $statuses = ['active', 'active', 'maintained', 'archived', 'on_hold'];

        foreach ($statuses as $index => $status) {
            $project = Project::factory()->create([
                'status' => $status,
                'occurred_at' => now()->subDays(fake()->numberBetween(10, 170)),
            ]);

            $this->attachCover($project);

            if (fake()->boolean(30)) {
                $this->attachPhotos($project, fake()->numberBetween(1, 3));
            }
        }

        $appearanceCount = fake()->numberBetween(2, 3);

        for ($i = 0; $i < $appearanceCount; $i++) {
            $appearance = Appearance::factory()->create([
                'occurred_at' => now()->subDays(fake()->numberBetween(10, 170)),
            ]);

            $this->attachCover($appearance);
        }
    }

    private function seedSleep(Carbon $date): void
    {
        Sleep::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(6, 8), fake()->randomElement([0, 15, 30, 45])),
        ]);
    }

    private function seedCalories(Carbon $date): void
    {
        $breakfastCount = fake()->numberBetween(1, 2);
        for ($i = 0; $i < $breakfastCount; $i++) {
            Calorie::factory()->create([
                'occurred_at' => $date->copy()->setTime(fake()->numberBetween(7, 9), fake()->numberBetween(0, 59)),
                'meal' => 'breakfast',
            ]);
        }

        $lunchCount = fake()->numberBetween(1, 2);
        for ($i = 0; $i < $lunchCount; $i++) {
            Calorie::factory()->create([
                'occurred_at' => $date->copy()->setTime(fake()->numberBetween(12, 13), fake()->numberBetween(0, 59)),
                'meal' => 'lunch',
            ]);
        }

        $dinnerCount = fake()->numberBetween(1, 3);
        for ($i = 0; $i < $dinnerCount; $i++) {
            Calorie::factory()->create([
                'occurred_at' => $date->copy()->setTime(fake()->numberBetween(18, 20), fake()->numberBetween(0, 59)),
                'meal' => 'dinner',
            ]);
        }

        $snackCount = fake()->numberBetween(0, 2);
        for ($i = 0; $i < $snackCount; $i++) {
            Calorie::factory()->create([
                'occurred_at' => $date->copy()->setTime(fake()->numberBetween(10, 16), fake()->numberBetween(0, 59)),
                'meal' => 'snacks',
            ]);
        }
    }

    private function seedActivities(Carbon $date): void
    {
        if (! fake()->boolean(50)) {
            return;
        }

        $activity = Activity::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(6, 19), fake()->numberBetween(0, 59)),
        ]);

        $isCardio = in_array($activity->type, ['run', 'ride', 'walk', 'swim']);

        if ($isCardio) {
            $this->attachMap($activity);
        }

        if (fake()->boolean(50)) {
            $this->attachPhotos($activity, fake()->numberBetween(1, 3));
        }
    }

    private function seedCheckins(Carbon $date): void
    {
        if (! fake()->boolean(60)) {
            return;
        }

        $count = fake()->numberBetween(1, 2);

        for ($i = 0; $i < $count; $i++) {
            $checkin = Checkin::factory()->create([
                'occurred_at' => $date->copy()->setTime(fake()->numberBetween(9, 21), fake()->numberBetween(0, 59)),
            ]);

            if (fake()->boolean(30)) {
                $this->attachPhotos($checkin, fake()->numberBetween(1, 2));
            }

            if (fake()->boolean(50)) {
                $this->attachMap($checkin);
            }
        }
    }

    private function seedNotes(Carbon $date): void
    {
        if (! fake()->boolean(15)) {
            return;
        }

        $note = Note::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(8, 22), fake()->numberBetween(0, 59)),
        ]);

        if (fake()->boolean(20)) {
            $this->attachPhotos($note, fake()->numberBetween(1, 2));
        }
    }

    private function seedMediaFilms(Carbon $date): void
    {
        if (! fake()->boolean(40)) {
            return;
        }

        $film = Media::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(19, 22), fake()->numberBetween(0, 59)),
            'type' => 'film',
            'meta' => [
                'year' => fake()->numberBetween(1990, 2026),
                'runtime' => fake()->numberBetween(80, 200),
                'genres' => fake()->randomElements(
                    ['Drama', 'Action', 'Comedy', 'Thriller', 'Sci-Fi', 'Horror', 'Romance', 'Documentary'],
                    fake()->numberBetween(1, 3),
                ),
            ],
        ]);

        $this->attachCover($film);
    }

    private function seedMediaTvEpisodes(Carbon $date): void
    {
        if (! fake()->boolean(50)) {
            return;
        }

        $showTitle = fake()->randomElement(self::TV_SHOWS);
        $seasonNumber = fake()->numberBetween(1, 5);
        $startEpisode = fake()->numberBetween(1, 20);
        $episodeCount = fake()->numberBetween(1, 3);

        for ($i = 0; $i < $episodeCount; $i++) {
            $episode = Media::factory()->create([
                'occurred_at' => $date->copy()->setTime(fake()->numberBetween(19, 23), fake()->numberBetween(0, 59)),
                'type' => 'tv_episode',
                'title' => $showTitle,
                'meta' => [
                    'show_title' => $showTitle,
                    'season_number' => $seasonNumber,
                    'episode_number' => $startEpisode + $i,
                    'episode_title' => fake()->words(fake()->numberBetween(2, 4), true),
                    'runtime' => fake()->numberBetween(25, 65),
                ],
            ]);

            $this->attachCover($episode);
        }
    }

    /**
     * @return int The next episode number to use.
     */
    private function seedPodcasts(Carbon $date, int $currentEpisode): int
    {
        if (! fake()->boolean(15)) {
            return $currentEpisode;
        }

        $seasonNumber = (int) ceil($currentEpisode / 10);

        $podcast = Podcast::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(8, 18), fake()->numberBetween(0, 59)),
            'season_number' => $seasonNumber,
            'episode_number' => $currentEpisode,
        ]);

        $this->attachCover($podcast);

        return $currentEpisode + 1;
    }

    private function seedArticles(Carbon $date): void
    {
        if (! fake()->boolean(3)) {
            return;
        }

        $article = Article::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(9, 17), fake()->numberBetween(0, 59)),
        ]);

        $this->attachCover($article);
    }

    private function seedFlights(Carbon $date): void
    {
        if (! fake()->boolean(3)) {
            return;
        }

        $flight = Flight::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(5, 20), fake()->numberBetween(0, 59)),
        ]);

        $this->attachMap($flight);
    }

    private function seedFuel(Carbon $date): void
    {
        if (! fake()->boolean(5)) {
            return;
        }

        Fuel::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(7, 19), fake()->numberBetween(0, 59)),
        ]);
    }

    private function seedEvents(Carbon $date): void
    {
        if (! fake()->boolean(5)) {
            return;
        }

        $event = Event::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(17, 21), fake()->numberBetween(0, 59)),
        ]);

        $this->attachCover($event);

        if (fake()->boolean(50)) {
            $this->attachPhotos($event, fake()->numberBetween(1, 4));
        }
    }

    private function seedMediaBooks(Carbon $date): void
    {
        if (! fake()->boolean(2)) {
            return;
        }

        $book = Media::factory()->create([
            'occurred_at' => $date->copy()->setTime(fake()->numberBetween(19, 22), fake()->numberBetween(0, 59)),
            'type' => 'book',
            'meta' => [
                'author' => fake()->name(),
                'isbn' => fake()->isbn13(),
            ],
        ]);

        $this->attachCover($book);
    }

    private function attachCover(Model $model): void
    {
        Asset::factory()->create([
            'assetable_type' => $model->getMorphClass(),
            'assetable_id' => $model->getKey(),
            'type' => 'cover',
        ]);
    }

    /**
     * @param  int  $count  Number of photos to attach.
     */
    private function attachPhotos(Model $model, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Asset::factory()->create([
                'assetable_type' => $model->getMorphClass(),
                'assetable_id' => $model->getKey(),
                'type' => 'photo',
                'order' => $i,
            ]);
        }
    }

    private function attachMap(Model $model): void
    {
        Asset::factory()->create([
            'assetable_type' => $model->getMorphClass(),
            'assetable_id' => $model->getKey(),
            'type' => 'map',
        ]);
    }
}
