<?php

namespace Database\Factories;

use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\SyndicatedResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SyndicatedResponse> */
class SyndicatedResponseFactory extends Factory
{
    protected $model = SyndicatedResponse::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'source' => Source::Strava->value,
            'kind' => WebmentionKind::Like,
            'author_name' => fake()->firstName().' '.fake()->randomLetter().'.',
            'occurred_at' => now(),
        ];
    }
}
