<?php

namespace App\Fields;

use App\Actions\Entries\UpdateEntryStatus;
use App\Data\FieldData;
use App\Enums\EntryStatus;
use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;

/** What the editor offers a synced entry, which has no fields of its own: its status and password. */
final class StatusFields
{
    /**
     * @return list<FieldData>
     */
    public static function for(Model $model): array
    {
        $canBeDraft = UpdateEntryStatus::canBeDraft($model);

        return [
            FieldData::primary('status', 'Status', FieldType::Status, array_values(array_filter(
                EntryStatus::options(),
                fn (array $option): bool => $canBeDraft || $option['value'] !== EntryStatus::Draft->value,
            ))),
            FieldData::hidden('password', 'Password', FieldType::Text),
        ];
    }
}
