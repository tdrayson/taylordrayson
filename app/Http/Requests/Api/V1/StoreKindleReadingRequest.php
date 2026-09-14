<?php

namespace App\Http\Requests\Api\V1;

use App\Data\KindleItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreKindleReadingRequest extends FormRequest
{
    /** 2020-01-01, which catches a Kindle that booted with its clock reset to 1970. */
    private const EARLIEST = 1577836800;

    /**
     * A full snapshot of every book on the device with progress above zero.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:32'],
            'synced_at' => ['required', 'integer', 'min:'.self::EARLIEST],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.cde_key' => ['required', 'string', 'max:64', 'distinct'],
            'items.*.type' => ['nullable', 'string', 'max:16'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.percent' => ['required', 'numeric', 'between:0,100'],
            'items.*.last_open' => ['required', 'integer', 'min:'.self::EARLIEST],
        ];
    }

    /**
     * @return list<KindleItem>
     */
    public function items(): array
    {
        return array_values(array_map(KindleItem::from(...), $this->validated('items')));
    }

    /** The device keeps only 300 characters of a 422, so the full rejection is logged here. */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('kindle sync rejected', [
            'errors' => $validator->errors()->toArray(),
            'payload' => $this->all(),
        ]);

        parent::failedValidation($validator);
    }
}
