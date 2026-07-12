<?php

namespace App\Console\Commands;

use App\Content\CalorieDayFileSynchronizer;
use App\Content\ContentTypes;
use App\Content\EntryFileRepository;
use App\Models\Calorie;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Signature('content:export {--type= : Entry type to export. Omit for all supported types.}')]
#[Description('Write database entries out to the content tree (file-first bootstrap)')]
class ContentExportCommand extends Command
{
    public function handle(EntryFileRepository $files): int
    {
        $type = $this->option('type');
        $types = ContentTypes::fileTypes();
        $types = $type === null || $type === ''
            ? $types
            : (isset($types[$type]) ? [$type => $types[$type]] : []);

        if ($types === []) {
            $this->error('Unknown type. Supported: '.implode(', ', array_keys(ContentTypes::fileTypes())));

            return self::FAILURE;
        }

        foreach ($types as $name => $class) {
            if ($name === 'calorie') {
                $this->exportCalories();

                continue;
            }

            $count = 0;

            $class::query()->orderBy('id')->each(function (Model $model) use ($files, &$count): void {
                if (blank($model->getAttribute('ulid'))) {
                    $model->setAttribute('ulid', (string) Str::ulid());
                    $model->saveQuietly();
                }

                $files->write($model);
                $count++;
            });

            $this->info("Exported {$count} {$name}(s)");
        }

        $this->line('Content path: '.config('content.path'));

        return self::SUCCESS;
    }

    private function exportCalories(): void
    {
        $days = Calorie::query()
            ->toBase()
            ->selectRaw('DATE(occurred_at) as day')
            ->distinct()
            ->orderBy('day')
            ->pluck('day');

        $synchronizer = app(CalorieDayFileSynchronizer::class);

        foreach ($days as $day) {
            $synchronizer->sync((string) $day);
        }

        $this->info('Exported '.$days->count().' calorie day(s)');
    }
}
