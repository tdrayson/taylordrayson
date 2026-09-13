<?php

namespace App\Models\Scopes;

use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Spine rows a visitor may see in a listing. Fail closed: opting out is explicit. */
final class ListedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('status'), EntryStatus::Published->value);
    }
}
