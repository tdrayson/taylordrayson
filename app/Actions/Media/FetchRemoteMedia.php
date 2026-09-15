<?php

namespace App\Actions\Media;

use App\Data\FieldData;
use App\Support\PendingUploads;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Swap `url:` items in media fields for pending uploads, so an image a lookup
 * offered saves exactly like one uploaded by hand.
 */
final class FetchRemoteMedia
{
    private const PREFIX = 'url:';

    private const TIMEOUT_SECONDS = 10;

    /**
     * @param  list<FieldData>  $fields
     * @param  array<string, mixed>  $attributes  Validated editor values.
     * @return array<string, mixed>
     *
     * @throws ValidationException When an image cannot be fetched.
     */
    public function __invoke(array $fields, array $attributes): array
    {
        foreach ($fields as $field) {
            if (! $field->type->isMedia() || ! is_array($attributes[$field->name] ?? null)) {
                continue;
            }

            $attributes[$field->name] = array_map(
                fn (string $item): string => str_starts_with($item, self::PREFIX)
                    ? 'pending:'.$this->fetch($field->name, substr($item, strlen(self::PREFIX)))
                    : $item,
                $attributes[$field->name],
            );
        }

        return $attributes;
    }

    private function fetch(string $field, string $url): string
    {
        $failed = ValidationException::withMessages([$field => "Couldn't fetch that image, upload one instead."]);

        if (! str_starts_with($url, 'https://')) {
            throw $failed;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get($url);
        } catch (ConnectionException) {
            throw $failed;
        }

        if ($response->failed() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            throw $failed;
        }

        return PendingUploads::storeContents($response->body(), basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image.jpg');
    }
}
