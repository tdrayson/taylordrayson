<?php

namespace App\Console\Commands\Export;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
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
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('export:csv {file : Path to write the CSV to} {type : The model type to export (e.g. calorie, activity, sleep)}')]
#[Description('Export a database table back to a CSV whose headers are the model fillable columns')]
class ExportCsv extends Command
{
    /** @var array<string, class-string<Model>> */
    private array $models = [
        'activity' => Activity::class,
        'appearance' => Appearance::class,
        'article' => Article::class,
        'calorie' => Calorie::class,
        'checkin' => Checkin::class,
        'event' => Event::class,
        'flight' => Flight::class,
        'fuel' => Fuel::class,
        'media' => Media::class,
        'note' => Note::class,
        'podcast' => Podcast::class,
        'project' => Project::class,
        'sleep' => Sleep::class,
    ];

    public function handle(): int
    {
        $type = $this->argument('type');

        if (! isset($this->models[$type])) {
            $this->error("Unknown type: {$type}. Available: ".implode(', ', array_keys($this->models)));

            return self::FAILURE;
        }

        $modelClass = $this->models[$type];
        $model = new $modelClass;
        $headers = $model->getFillable();

        // Round-trip relational tags as a trailing pipe-separated `tags` column,
        // mirroring ImportCsv (the tag table has no fillable column of its own).
        $hasTags = method_exists($model, 'tagNames');
        if ($hasTags) {
            $headers[] = 'tags';
        }

        $handle = fopen($this->argument('file'), 'w');
        fputcsv($handle, $headers, ',', '"', '\\');

        $exported = 0;

        $modelClass::query()->orderBy('id')->when($hasTags, fn ($query) => $query->with('tags'))->chunk(500, function ($rows) use ($handle, $headers, &$exported): void {
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn (string $header): string => $this->stringify($row, $header), $headers), ',', '"', '\\');
                $exported++;
            }
        });

        fclose($handle);

        $this->info("Exported {$exported} {$type} rows to {$this->argument('file')}.");

        return self::SUCCESS;
    }

    /**
     * The raw database value for a column as a CSV-ready string. JSON-cast columns
     * keep their stored JSON, dates keep their stored format, and nulls become empty.
     */
    private function stringify(Model $row, string $header): string
    {
        if ($header === 'tags' && method_exists($row, 'tagNames')) {
            return implode('|', $row->tagNames());
        }

        $value = $row->getRawOriginal($header);

        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }
}
