<?php

namespace App\DynamicTags\Ambient;

use App\Data\TagOption;
use App\DynamicTags\DynamicTag;
use App\Queries\NowState;
use App\Support\EntryInstant;
use Carbon\CarbonImmutable;
use IntlTimeZone;

/**
 * One reading from the phone, e.g. `ambient.weather.temp`. Generated from
 * {@see NowState::fieldMap()} so the allow-list is declared once.
 */
class AmbientTag extends DynamicTag
{
    public function __construct(
        private readonly string $group,
        private readonly string $field,
    ) {}

    /**
     * One tag per mapped {@see NowState} field, plus a ring-goal node form
     * and derived ring percentages.
     *
     * @return list<DynamicTag>
     */
    public static function generate(): array
    {
        $tags = [];

        foreach (NowState::fieldMap() as $group => $fields) {
            foreach ([...array_values($fields), 'updated'] as $field) {
                if (in_array($field, NowState::TAG_EXCLUDED_FIELDS, true)) {
                    continue;
                }

                $tags[] = new self($group, $field);
            }
        }

        foreach (['move', 'exercise', 'stand'] as $ring) {
            $tags[] = new RingPercent($ring);
        }

        return $tags;
    }

    public function name(): string
    {
        if (str_ends_with($this->field, 'Goal')) {
            return 'ambient.rings.'.lcfirst(substr($this->field, 0, -4)).'.goal';
        }

        // `country` is what an author writes; `format` picks code or name.
        $field = $this->field === 'countryCode' ? 'country' : $this->field;

        return "ambient.{$this->group}.{$field}";
    }

    public function label(): string
    {
        return ucfirst($this->group).' '.$this->field;
    }

    public function group(): string
    {
        return 'Ambient';
    }

    /**
     * @return list<TagOption>
     */
    public function options(): array
    {
        return match ($this->field) {
            'timezone' => [new TagOption('format', 'Format', ['identifier', 'offset', 'abbreviation', 'long'], 'identifier')],
            'countryCode' => [new TagOption('format', 'Format', ['code', 'name'], 'name')],
            default => [],
        };
    }

    /**
     * Null when the group has never been written, or the field is absent
     * from the reading that was.
     *
     * @param  array<string, string>  $options
     */
    public function resolve(array $options): mixed
    {
        $group = app(NowState::class)()[$this->group] ?? null;

        if ($group === null) {
            return null;
        }

        return $group[$this->field === 'updated' ? 'observedAt' : $this->field] ?? null;
    }

    /**
     * Every field falls through to the default numeric/string rendering
     * except `timezone` and `countryCode`, which read `$options['format']`.
     *
     * @param  array<string, string>  $options
     */
    public function format(mixed $value, array $options): string
    {
        if ($this->field === 'timezone') {
            return $this->timezone((string) $value, $options['format'] ?? 'identifier');
        }

        if ($this->field === 'countryCode' && ($options['format'] ?? 'name') === 'name') {
            return (string) (app(NowState::class)()['location']['country'] ?? $value);
        }

        return parent::format($value, $options);
    }

    /** An unrecognised stored zone falls back to home before it can throw. */
    private function timezone(string $zone, string $format): string
    {
        $zone = EntryInstant::zone($zone);
        $now = CarbonImmutable::now($zone);

        return match ($format) {
            'offset' => $now->format('P'),
            'abbreviation' => $now->format('T'),
            'long' => IntlTimeZone::createTimeZone($zone)->getDisplayName($now->isDST(), IntlTimeZone::DISPLAY_LONG, 'en'),
            default => $zone,
        };
    }
}
