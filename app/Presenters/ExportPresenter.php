<?php

namespace App\Presenters;

use App\Data\ExportData;
use App\Datasets\Datasets;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Resolves a model to its export payload. Mirrors CardPresenter: the
 * per-type presenter is declared on the dataset, so a new timeline type
 * cannot ship without one.
 */
final class ExportPresenter
{
    public static function for(Model $model): ExportData
    {
        return self::export($model)->present($model);
    }

    public static function export(Model $model): object
    {
        return Datasets::forModel($model)?->export()
            ?? throw new LogicException('No export presenter registered for '.$model::class);
    }
}
