<?php

namespace App\Console\Commands\Import;

use App\Content\ContentTypes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('import:csv {file : Path to the CSV file} {type : The model type to import (e.g. calorie, activity, sleep)}')]
#[Description('Import data from a CSV file where headers match database columns')]
class ImportCsv extends Command
{
    public function handle(): int
    {
        $file = $this->argument('file');
        $type = $this->argument('type');
        $models = ContentTypes::csvTypes();

        if (! isset($models[$type])) {
            $this->error('Unknown type: '.$type.'. Available: '.implode(', ', array_keys($models)));

            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $modelClass = $models[$type];
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
