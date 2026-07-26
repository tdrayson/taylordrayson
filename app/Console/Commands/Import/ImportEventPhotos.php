<?php

namespace App\Console\Commands\Import;

use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('events:import-photos {folder : Folder of event photos named by event} {--apply : Attach the matched photos (dry-run otherwise)}')]
#[Description('Attach event photos from a folder, matching each filename to an event by name, using a trailing year or date to disambiguate recurring events and a trailing number for extra photos of one event.')]
class ImportEventPhotos extends Command
{
    /** @var list<string> */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'heic'];

    public function handle(): int
    {
        $folder = rtrim((string) $this->argument('folder'), '/');

        if (! is_dir($folder)) {
            $this->error("Folder not found: {$folder}");

            return self::FAILURE;
        }

        $events = Event::query()->get();
        $apply = (bool) $this->option('apply');

        $files = collect(scandir($folder) ?: [])
            ->reject(fn (string $file): bool => str_starts_with($file, '.'))
            ->filter(fn (string $file): bool => in_array(Str::lower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->values();

        $attached = 0;
        $skipped = 0;
        $matches = [];
        $unmatched = [];

        foreach ($files as $file) {
            $event = $this->match($events, $file);

            if ($event === null) {
                $unmatched[] = $file;

                continue;
            }

            $matches[] = sprintf('%-58s -> %s (%s)', $file, $event->name, $event->occurred_at->toDateString());

            if (! $apply) {
                continue;
            }

            // Idempotent on the source filename we stamp below, since the media
            // library sanitises the stored file name and would not round-trip.
            $exists = $event->getMedia('photos')
                ->contains(fn (Media $media): bool => $media->getCustomProperty('source') === $file);

            if ($exists) {
                $skipped++;

                continue;
            }

            $event->addMedia($folder.'/'.$file)
                ->preservingOriginal()
                ->withCustomProperties(['source' => $file])
                ->toMediaCollection('photos');

            $attached++;
        }

        foreach ($matches as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->info(sprintf('%d matched, %d unmatched.', count($matches), count($unmatched)));

        foreach ($unmatched as $file) {
            $this->warn('  unmatched: '.$file);
        }

        if ($apply) {
            $this->info(sprintf('Attached %d photo(s), skipped %d already present.', $attached, $skipped));
        } else {
            $this->comment('Dry run. Re-run with --apply to attach.');
        }

        return self::SUCCESS;
    }

    /**
     * Resolve a filename to exactly one event, or null when it matches none or
     * is ambiguous (so ambiguity surfaces as "unmatched" rather than silently
     * attaching to the wrong recurrence).
     *
     * Two passes, because a trailing year can be either part of the name
     * ("WordCamp Europe 2024") or a disambiguator ("Hamilton" seen twice):
     * first match the whole name as-is, then peel off a trailing year/date and
     * use it to pick between same-named events.
     *
     * @param  Collection<int, Event>  $events
     */
    private function match(Collection $events, string $file): ?Event
    {
        $raw = pathinfo($file, PATHINFO_FILENAME);

        // The whole filename as the event name first, so a title that genuinely
        // ends in a number ("Apollo 13") or carries a year ("WordCamp Europe
        // 2024") is matched before the photo-index strip below could mangle it.
        if (($event = $this->uniqueByName($events, $raw)) !== null) {
            return $event;
        }

        // Trailing extra-photo index (" 1" or ")1"), guarded so it never eats the
        // last digits of a 4-digit year (which is preceded by another digit).
        $base = (string) preg_replace('/(?:\s+|(?<=\)))\d{1,2}$/', '', $raw);

        if ($base !== $raw && ($event = $this->uniqueByName($events, $base)) !== null) {
            return $event;
        }

        $name = $base;
        $year = null;
        $date = null;

        if (preg_match('/^(.*)\s(\d{4}-\d{2}-\d{2})$/', $base, $matches) === 1) {
            $name = $matches[1];
            $date = $matches[2];
        } elseif (preg_match('/^(.*)\s((?:19|20)\d{2})$/', $base, $matches) === 1) {
            $name = $matches[1];
            $year = (int) $matches[2];
        }

        $candidates = $events->filter(fn (Event $event): bool => $this->normalise($event->name) === $this->normalise($name));

        if ($date !== null) {
            $candidates = $candidates->filter(fn (Event $event): bool => $event->occurred_at->toDateString() === $date);
        } elseif ($year !== null) {
            $candidates = $candidates->filter(fn (Event $event): bool => (int) $event->occurred_at->format('Y') === $year);
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * The single event whose name normalises to $name, or null if none or more
     * than one does (ambiguity is surfaced as "unmatched", never guessed).
     *
     * @param  Collection<int, Event>  $events
     */
    private function uniqueByName(Collection $events, string $name): ?Event
    {
        $matches = $events->filter(fn (Event $event): bool => $this->normalise($event->name) === $this->normalise($name));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Fold a name to ASCII letters, digits and single spaces so a filename can
     * match an event whose title carries punctuation the filename dropped
     * (colons in "Derren Brown: Miracle", the "&" in "Guys & Dolls") or accents
     * a filename often loses ("Beyoncé" vs "Beyonce").
     */
    private function normalise(string $value): string
    {
        $ascii = Str::ascii(Str::lower($value));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9 ]/', ' ', $ascii)));
    }
}
