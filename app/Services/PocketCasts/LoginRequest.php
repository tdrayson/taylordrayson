<?php

namespace App\Services\PocketCasts;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/** Exchange the account credentials for a JWT. */
class LoginRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $email,
        private readonly string $password,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/user/login';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return ['email' => $this->email, 'password' => $this->password, 'scope' => 'webplayer'];
    }
}
