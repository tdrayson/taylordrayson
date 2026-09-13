<?php

namespace App\Data;

use App\Actions\Entries\UpdateEntryStatus;
use App\Enums\EntryStatus;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/** What the owner's status control needs: where to save, the current status, the allowed ones. */
final readonly class StatusControlData implements Arrayable, JsonSerializable
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public function __construct(
        public string $action,
        public EntryStatus $status,
        public array $options,
        public bool $hasPassword,
    ) {}

    public static function for(Model $model): self
    {
        $canBeDraft = UpdateEntryStatus::canBeDraft($model);

        return new self(
            action: route('entries.status', ['dataset' => $model->getMorphClass(), 'id' => $model->getKey()], false),
            status: $model->status,
            options: array_values(array_filter(
                EntryStatus::options(),
                fn (array $option): bool => $canBeDraft || $option['value'] !== EntryStatus::Draft->value,
            )),
            hasPassword: filled($model->getRawOriginal('password')),
        );
    }

    /**
     * @return array{action: string, status: string, options: list<array{value: string, label: string}>, hasPassword: bool}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'status' => $this->status->value,
            'options' => $this->options,
            'hasPassword' => $this->hasPassword,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
