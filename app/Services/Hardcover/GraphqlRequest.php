<?php

namespace App\Services\Hardcover;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use stdClass;

/** One GraphQL query, with its variables. */
class GraphqlRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param  array<string, mixed>  $variables */
    public function __construct(
        private readonly string $graphql,
        private readonly array $variables = [],
    ) {}

    public function resolveEndpoint(): string
    {
        return '/graphql';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return array_filter([
            'query' => $this->graphql,
            // An empty object, not an empty array: GraphQL rejects `[]` here.
            'variables' => $this->variables === [] ? new stdClass : $this->variables,
        ], fn (mixed $value): bool => $value !== null);
    }
}
