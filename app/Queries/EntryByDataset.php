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
        return $this->find($dataset, $id) ?? throw new NotFoundHttpException;
    }

    /** The status-carrying model a morph alias and id name, or null. */
    public function find(string $dataset, int $id): ?Model
    {
        $class = Datasets::morphMap()[$dataset] ?? null;

        if ($class === null || ! in_array(HasStatus::class, class_uses_recursive($class), true)) {
            return null;
        }

        return $class::query()->find($id);
    }
}
