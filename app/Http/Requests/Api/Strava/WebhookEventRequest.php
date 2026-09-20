<?php

namespace App\Http\Requests\Api\Strava;

use App\Data\StravaWebhookEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One pushed event. Deliberately loose on `object_type` and `aspect_type`:
 * rejecting a value Strava adds later would 422 three retries and then drop the
 * event, where accepting it costs a log line and nothing else.
 */
class WebhookEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'object_type' => ['required', 'string'],
            'object_id' => ['required'],
            'aspect_type' => ['required', 'string'],
            'owner_id' => ['required'],
            'subscription_id' => ['nullable', 'integer'],
            'event_time' => ['nullable', 'integer'],
            'updates' => ['nullable', 'array'],
        ];
    }

    public function event(): StravaWebhookEvent
    {
        return StravaWebhookEvent::fromPayload($this->validated());
    }
}
