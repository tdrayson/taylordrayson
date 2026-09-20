<?php

namespace App\Http\Requests\Api\V1\HealthExport;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for a Health Auto Export send. Each endpoint owns one
 * domain and names the metrics it takes, so an automation pointed at the wrong
 * path fails on the phone instead of being quietly dropped by a catch-all.
 */
abstract class HealthExportRequest extends FormRequest
{
    /**
     * HAE `metric.name` values this endpoint accepts.
     *
     * @return list<string>
     */
    abstract public function metrics(): array;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
            'data.metrics' => ['required', 'array', 'min:1'],
            'data.metrics.*.name' => ['required', 'string'],
            // Present rather than required: a ring the day has not touched yet
            // arrives as a named metric with no samples, and that honestly
            // means zero.
            'data.metrics.*.data' => ['present', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('data.metrics', []) as $index => $metric) {
                    $name = is_array($metric) ? ($metric['name'] ?? null) : null;

                    if (is_string($name) && ! in_array($name, $this->metrics(), true)) {
                        $validator->errors()->add(
                            "data.metrics.{$index}.name",
                            "This endpoint does not take `{$name}`. Expected one of: ".implode(', ', $this->metrics()).'.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * The send as HAE shaped it, for a processor that reads that shape directly.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->all();
    }

    /**
     * Metric name to the number of samples it carried. Processing happens on the
     * queue, so this is all the response can honestly say about what arrived,
     * and a zero makes a misconfigured automation obvious from the phone.
     *
     * @return array<string, int>
     */
    public function received(): array
    {
        $counts = [];

        foreach ((array) $this->input('data.metrics', []) as $metric) {
            if (is_array($metric) && is_string($metric['name'] ?? null)) {
                $counts[$metric['name']] = is_array($metric['data'] ?? null) ? count($metric['data']) : 0;
            }
        }

        return $counts;
    }
}
