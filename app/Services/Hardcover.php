<?php

namespace App\Services;

use App\Exceptions\HardcoverException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client for the Hardcover GraphQL API, used to look up books for the media
 * timeline. A failed response or a GraphQL `errors` payload throws rather than
 * returning an empty result set.
 */
class Hardcover
{
    private const BASE = 'https://api.hardcover.app/v1/graphql';

    /**
     * Run an arbitrary GraphQL query/mutation and return the `data` object.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        $response = $this->request($query, $variables);

        if ($response->failed()) {
            throw new HardcoverException("Hardcover GraphQL request failed with status {$response->status()}.");
        }

        /** @var array{data?: array<string, mixed>, errors?: list<array<string, mixed>>} $payload */
        $payload = $response->json() ?? [];

        if (isset($payload['errors']) && $payload['errors'] !== []) {
            $message = data_get($payload, 'errors.0.message', 'Unknown GraphQL error');

            throw new HardcoverException("Hardcover GraphQL error: {$message}");
        }

        return $payload['data'] ?? [];
    }

    /**
     * Search Hardcover for books, returning the raw `search` payload whose
     * `results.hits` holds the matched documents.
     *
     * @return array{error: mixed, page: int|null, per_page: int|null, query: string|null, query_type: string|null, results: array<string, mixed>|null}
     */
    public function search(string $query): array
    {
        $data = $this->query(<<<'GRAPHQL'
            query SearchBooks($query: String!) {
              search(query: $query) {
                error
                page
                per_page
                query
                query_type
                results
              }
            }
            GRAPHQL, [
            'query' => $query,
        ]);

        /** @var array{error: mixed, page: int|null, per_page: int|null, query: string|null, query_type: string|null, results: array<string, mixed>|null} */
        return $data['search'] ?? [
            'error' => null,
            'page' => null,
            'per_page' => null,
            'query' => null,
            'query_type' => null,
            'results' => null,
        ];
    }

    /**
     * Book documents from a search, flattened out of `results.hits`.
     *
     * @return list<array<string, mixed>>
     */
    public function searchDocuments(string $query): array
    {
        $hits = data_get($this->search($query), 'results.hits', []);

        if (! is_array($hits)) {
            return [];
        }

        return collect($hits)
            ->map(fn (mixed $hit): array => is_array($hit) ? ($hit['document'] ?? []) : [])
            ->filter(fn (array $document): bool => $document !== [])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function request(string $query, array $variables): Response
    {
        $key = config('services.hardcover.key');

        if (! is_string($key) || $key === '') {
            throw new HardcoverException('HARDCOVER_API_KEY is not configured.');
        }

        // Tokens sometimes arrive already prefixed with "Bearer "; strip so
        // withToken() does not double up the scheme.
        $token = str_starts_with($key, 'Bearer ') ? substr($key, 7) : $key;

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(3, 500, when: fn (\Throwable $e): bool => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response?->status() === 429), throw: false)
            ->post(self::BASE, array_filter([
                'query' => $query,
                'variables' => $variables === [] ? new \stdClass : $variables,
            ], fn (mixed $value): bool => $value !== null));
    }
}
