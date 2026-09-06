<?php

namespace App\Services\Pushover;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/** One message to the configured user. */
class SendMessageRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $token,
        private readonly string $user,
        private readonly string $title,
        private readonly string $message,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/messages.json';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'token' => $this->token,
            'user' => $this->user,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}
