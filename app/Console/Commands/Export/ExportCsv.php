<?php

namespace App\Console\Commands\Export;

use App\Content\ContentTypes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('export:csv {file : Path to write the CSV to} {type : The model type to export (e.g. calorie, activity, sleep)}')]
#[Description('Export a database table back to a CSV whose headers are the model fillable columns')]
class ExportCsv extends Command
{
    public function handle(): int
    {
        $type = $this->argument('type');
        $models = ContentTypes::csvTypes();

        if (! isset($models[$type])) {
            $this->error('Unknown type: '.$type.'. Available: '.implode(', ', array_keys($models)));

            return self::FAILURE;
        }

        $modelClass = $models[$type];
        $headers = (new $modelClass)->getFillable();

        $handle = fopen($this->argument('file'), 'w');
        fputcsv($handle, $headers, ',', '"', '\\');

        $exported = 0;

        $modelClass::query()->orderBy('id')->chunk(500, function ($rows) use ($handle, $headers, &$exported): void {
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
