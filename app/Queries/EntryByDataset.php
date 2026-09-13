<?php

namespace App\Queries;

use App\Datasets\Datasets;
use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EntryByDataset
{
    /** The status-carrying model a morph alias and id name, or a 404. */
    public function __invoke(string $dataset, int $id): Model
    {
        $class = Datasets::morphMap()[$dataset] ?? null;

        if ($class === null || ! in_array(HasStatus::class, class_uses_recursive($class), true)) {
            throw new NotFoundHttpException;
        }

        return $class::query()->findOrFail($id);
    }
}
