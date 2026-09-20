<?php

namespace App\Http\Requests\Api\Strava;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The one-off GET Strava makes while creating a subscription, which must echo
 * `hub.challenge` back as JSON within two seconds or the create is refused.
 *
 * Strava sends the parameters dotted, but PHP rewrites a dot in a query key to
 * an underscore before anything here sees it, so they are read as `hub_mode`,
 * `hub_challenge` and `hub_verify_token`. Only the response key keeps the dot.
 */
class VerifySubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expected = config('services.strava.webhook_verify_token');
        $provided = $this->query('hub_verify_token');

        return is_string($expected)
            && $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hub_mode' => ['required', 'string', 'in:subscribe'],
            'hub_challenge' => ['required', 'string'],
        ];
    }

    public function challenge(): string
    {
        return (string) $this->query('hub_challenge');
    }
}
