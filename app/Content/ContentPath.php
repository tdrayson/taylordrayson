<?php

namespace App\Content;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ContentPath
{
    public function absolute(Model $model, bool $original = false): string
    {
        return rtrim((string) config('content.path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $this->relative($model, $original));
    }

    public function relative(Model $model, bool $original = false): string
    {
        $slug = method_exists($model, 'flatFileSlug')
            ? $model->flatFileSlug($original)
            : $this->slug($model, $original);
        $extension = method_exists($model, 'flatFileExtension')
            ? $model->flatFileExtension()
            : 'md';

        if (method_exists($model, 'flatFileDirectory')) {
            return trim($model->flatFileDirectory(), '/').'/'.$slug.'.'.$extension;
        }

        $occurredAt = $this->occurredAt($model, $original);

        return $occurredAt->format('Y/m/d').'/'.$slug.'.'.$extension;
    }

    private function occurredAt(Model $model, bool $original): CarbonInterface
    {
        $value = $original
            ? $model->getOriginal('occurred_at')
            : $model->getAttribute('occurred_at');

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }

    private function slug(Model $model, bool $original): string
    {
        if ($original) {
            $slug = $model->getOriginal('slug');

            return filled($slug) ? (string) $slug : $model->flatFileType();
        }

        if (method_exists($model, 'slug')) {
            return $model->slug();
        }

        $slug = $model->getAttribute('slug');

        return filled($slug) ? (string) $slug : $model->flatFileType();
    }
}
