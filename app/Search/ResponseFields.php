<?php

namespace App\Search;

use App\Enums\ReactionType;
use App\Enums\ResponseSource;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use BackedEnum;

/**
 * The response and reaction fields every search type shares. Each carries a
 * scope so the compiler hands the whole set to ResponseFilter together.
 */
final class ResponseFields
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $response = ['category' => 'Responses', 'column' => null, 'scope' => 'response'];
        $reaction = ['category' => 'Responses', 'column' => null, 'scope' => 'reaction'];
        $text = ['contains', 'not_contains', 'equals', 'starts_with', 'ends_with'];

        return [
            'response_source' => [...$response, 'label' => 'From', 'dataType' => 'enum', 'operators' => ['is', 'is_not'], 'enum' => ResponseSource::class, 'options' => self::values(ResponseSource::cases())],
            'response_platform' => [...$response, 'label' => 'Platform', 'dataType' => 'enum', 'operators' => ['is', 'is_not'], 'enum' => Source::class, 'options' => self::values(self::platforms())],
            'response_kind' => [...$response, 'label' => 'Kind', 'dataType' => 'enum', 'operators' => ['is', 'is_not'], 'enum' => WebmentionKind::class, 'options' => self::values(WebmentionKind::cases())],
            'response_text' => [...$response, 'label' => 'Text', 'dataType' => 'text', 'operators' => ['contains']],
            'response_author' => [...$response, 'label' => 'Author', 'dataType' => 'text', 'operators' => $text],
            'response_site' => [...$response, 'label' => 'Site', 'dataType' => 'text', 'operators' => $text],
            'responses' => [...$response, 'label' => 'Count', 'dataType' => 'number', 'suffix' => 'responses'],
            'reaction' => [...$reaction, 'label' => 'Reaction', 'dataType' => 'enum', 'operators' => ['is', 'is_not'], 'enum' => ReactionType::class, 'options' => self::values(ReactionType::cases())],
            'reactions' => [...$reaction, 'label' => 'Reactions', 'dataType' => 'number', 'suffix' => 'reactions'],
        ];
    }

    /**
     * Every platform responses are synced from: the sources with a site to respond on.
     *
     * @return list<Source>
     */
    private static function platforms(): array
    {
        return array_values(array_filter(Source::cases(), fn (Source $source): bool => $source->host() !== null));
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(fn (BackedEnum $case): string => (string) $case->value, $cases);
    }
}
