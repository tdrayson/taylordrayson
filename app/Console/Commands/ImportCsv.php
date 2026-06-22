<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Airline;
use App\Models\Airport;
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

#[Signature('import:csv {file : Path to the CSV file} {type : The model type to import (e.g. calorie, activity, sleep)}')]
#[Description('Import data from a CSV file where headers match database columns')]
class ImportCsv extends Command
{
    /** @var array<string, class-string<Model>> */
    private array $models = [
        'activity' => Activity::class,
        'airline' => Airline::class,
        'airport' => Airport::class,
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
        $file = $this->argument('file');
        $type = $this->argument('type');

        if (! isset($this->models[$type])) {
            $this->error("Unknown type: {$type}. Available: ".implode(', ', array_keys($this->models)));

            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $modelClass = $this->models[$type];
        $model = new $modelClass;
        $fillable = $model->getFillable();
        $casts = $model->getCasts();

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        $unknown = array_diff($headers, $fillable);
        if ($unknown) {
            $this->warn('Skipping columns not in fillable: '.implode(', ', $unknown));
        }

        $validHeaders = array_intersect($headers, $fillable);

        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $data = array_combine($headers, $row);

            $mapped = [];
            foreach ($validHeaders as $header) {
                $value = $data[$header];
                if ($value === '' || $value === 'n/a') {
                    $mapped[$header] = null;
                } elseif (isset($casts[$header]) && in_array($casts[$header], ['array', 'json', 'collection'])) {
                    $mapped[$header] = json_decode($value, true);
                } elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $value)) {
                    // Strip thousands separators from numbers like "1,088" so they stay numeric.
                    $mapped[$header] = str_replace(',', '', $value);
                } else {
                    $mapped[$header] = $value;
                }
            }

            $modelClass::create($mapped);
            $imported++;
        }

        fclose($handle);

        $this->info("Imported {$imported} {$type} rows.");

        return self::SUCCESS;
    }
}
