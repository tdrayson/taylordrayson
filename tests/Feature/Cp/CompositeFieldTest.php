<?php

use App\Cp\CpResource;
use App\Cp\ResourceRegistry;
use App\Cp\Resources\FlightResource;
use App\Http\Controllers\Cp\ResourceController;
use App\Models\Flight;
use Illuminate\Database\Eloquent\Model;

/** Test-only subclass exposing the protected split/merge helpers. */
class CompositeControllerProbe extends ResourceController
{
    public function probeRecordValues(CpResource $definition, Model $record): array
    {
        return $this->recordValues($definition, $record);
    }

    public function probePrepare(CpResource $definition, array $validated): array
    {
        return $this->prepare($definition, $validated);
    }
}

function probe(): CompositeControllerProbe
{
    return new CompositeControllerProbe(app(ResourceRegistry::class));
}

/** A Flight resource exposing a typed group + key/value remainder over `meta`. */
function compositeResource(): CpResource
{
    return new class extends CpResource
    {
        public function model(): string
        {
            return Flight::class;
        }

        public function slug(): string
        {
            return 'flights-test';
        }

        public function label(): string
        {
            return 'Flight';
        }

        public function pluralLabel(): string
        {
            return 'Flights';
        }

        public function composites(): array
        {
            return [
                [
                    'key' => 'scheduling',
                    'label' => 'Scheduling',
                    'type' => 'group',
                    'column' => 'meta',
                    'fields' => [
                        ['key' => 'departed_scheduled', 'label' => 'Scheduled departure', 'type' => 'datetime'],
                        ['key' => 'arrived_scheduled', 'label' => 'Scheduled arrival', 'type' => 'datetime'],
                    ],
                ],
                ['key' => 'meta_extra', 'label' => 'Other meta', 'type' => 'keyvalue', 'column' => 'meta'],
            ];
        }
    };
}

it('appends composite fields and hides the raw consumed column', function () {
    $keys = collect(compositeResource()->fields())->pluck('key');

    expect($keys)->toContain('scheduling');
    expect($keys)->toContain('meta_extra');
    expect($keys)->not->toContain('meta');
});

it('splits a stored json column into group values and a key/value remainder on load', function () {
    $resource = compositeResource();

    $flight = new Flight([
        'occurred_at' => '2026-06-03 17:20:00',
        'meta' => [
            'departed_scheduled' => '2026-06-03T17:20',
            'arrived_scheduled' => '2026-06-03T20:55',
            'gate' => 'B12',
        ],
    ]);

    $values = probe()->probeRecordValues($resource, $flight);

    expect($values['scheduling']['departed_scheduled'])->toBe('2026-06-03T17:20');
    expect($values['scheduling'])->not->toHaveKey('gate');
    expect($values['meta_extra'])->toBe(['gate' => 'B12']);
});

it('merges group values and key/value rows back into the column on save', function () {
    $resource = compositeResource();

    $merged = probe()->probePrepare($resource, [
        'occurred_at' => '2026-06-03T17:20',
        'scheduling' => ['departed_scheduled' => '2026-06-03T17:20', 'arrived_scheduled' => ''],
        'meta_extra' => ['gate' => 'B12', '' => 'dropme'],
    ]);

    expect($merged)->not->toHaveKey('scheduling');
    expect($merged)->not->toHaveKey('meta_extra');
    expect($merged['meta'])->toBe([
        'departed_scheduled' => '2026-06-03T17:20',
        'gate' => 'B12',
    ]);
});

it('exposes a scheduling group and hides raw meta on the real flight resource', function () {
    $keys = collect((new FlightResource)->fields())->pluck('key');

    expect($keys)->toContain('scheduling');
    expect($keys)->not->toContain('meta');
});

it('places the flight scheduling group in a main section', function () {
    $layout = (new FlightResource)->layout();

    $mainKeys = collect($layout['sections'])
        ->where('area', 'main')
        ->flatMap(fn ($s) => collect($s['fields'])->pluck('key'));

    expect($mainKeys)->toContain('scheduling');
});
