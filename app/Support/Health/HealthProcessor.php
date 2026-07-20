<?php

namespace App\Support\Health;

interface HealthProcessor
{
    /**
     * Metric names (HAE `metric.name`) this processor consumes.
     *
     * @return list<string>
     */
    public function handles(): array;

    /**
     * Process a decoded Health Auto Export payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void;
}
