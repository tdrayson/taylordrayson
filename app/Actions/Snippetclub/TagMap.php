<?php

namespace App\Actions\Snippetclub;

/**
 * Fold the old site's 68 article tags into a set worth keeping.
 *
 * A snippet site tags by plugin, so two thirds of those tags named a product
 * mentioned in one post. A tag matching one article is a filter nobody can use,
 * so the narrow ones fold into the topic they belong to and the dead ones go.
 *
 * One-off, for the SnippetClub migration. Delete it once the content has moved.
 */
final class TagMap
{
    /**
     * Source tag name, lowercased, to the tag it becomes. A null value drops it.
     *
     * @var array<string, string|null>
     */
    private const MAP = [
        // Broad enough already.
        'php' => 'PHP',
        'javascript' => 'JavaScript',
        'css' => 'CSS',
        'accessibility' => 'Accessibility',
        'api' => 'API',
        'gutenberg' => 'Gutenberg',
        'acf' => 'ACF',
        'fluent forms' => 'Fluent Forms',
        'fluentcrm' => 'FluentCRM',
        'blockstudio' => 'Blockstudio',
        'snippets' => 'Snippets',
        'tutorials' => 'Tutorials',

        // A Pro tier is the same subject as the thing it extends.
        'generatepress' => 'GeneratePress',
        'gp premium' => 'GeneratePress',
        'generateblocks' => 'GenerateBlocks',
        'generateblocks pro' => 'GenerateBlocks',

        // The rest of the Fluent suite, one post each.
        'fluentboards' => 'WordPress',
        'fluent support' => 'WordPress',
        'fluent smtp' => 'WordPress',
        'fluent community' => 'WordPress',
        'smartcodes' => 'Fluent Forms',
        'conversational form' => 'Fluent Forms',

        // WordPress techniques. None is a filter worth having on its own, and
        // `hooks & filters` least of all: it sat on 68 of 127 articles, so it
        // separated nothing, and it says nothing about writing after WordPress.
        'hooks & filters' => 'WordPress',
        'custom function' => 'WordPress',
        'shortcode' => 'WordPress',
        'conditions' => 'WordPress',
        'php return value' => 'PHP',
        'template_redirect' => 'WordPress',
        'repeater' => 'ACF',
        'dynamic' => 'WordPress',
        'dynamic data' => 'WordPress',
        'options page' => 'WordPress',
        'author archives' => 'WordPress',
        'advanced query' => 'WordPress',
        'url params' => 'WordPress',
        'media library' => 'WordPress',
        'maintenance mode' => 'WordPress',
        'open graph' => 'WordPress',
        'mega menu' => 'WordPress',

        // Third-party plugins named in one or two posts.
        'oxygen builder' => 'WordPress',
        'metabox' => 'WordPress',
        'slim seo' => 'WordPress',
        'wpgridbuilder' => 'WordPress',
        'searchwp' => 'WordPress',
        'wpcodebox' => 'WordPress',
        'kadence' => 'WordPress',
        'gravity forms' => 'WordPress',
        'plugins' => 'WordPress',
        'localwp' => 'WordPress',

        // Libraries used once, which is the language they are written in.
        'lityjs' => 'JavaScript',
        'flatpickr' => 'JavaScript',
        'carousel' => 'JavaScript',
        'lightbox' => 'JavaScript',
        'ajax' => 'JavaScript',

        // Services.
        'mapbox' => 'Maps',
        'polyline' => 'Maps',
        'openweathermap' => 'API',
        'onesimpleapi' => 'API',
        'pushover' => 'API',
        'alttext.ai' => 'AI',

        // Said nothing about the article they were on.
        'textexpander' => null,
        'conversion tracking' => null,
        'demo' => null,
        'cheatsheet' => null,
    ];

    /**
     * What a source tag becomes, or null to drop it. An unmapped name is kept
     * as it arrived, so a tag added to the old site after this was written
     * survives rather than vanishing.
     */
    public function for(string $name): ?string
    {
        $key = mb_strtolower(trim($name));

        return array_key_exists($key, self::MAP) ? self::MAP[$key] : trim($name);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    public function apply(array $names): array
    {
        $mapped = [];

        foreach ($names as $name) {
            $tag = $this->for($name);

            if ($tag !== null && $tag !== '') {
                $mapped[] = $tag;
            }
        }

        return array_values(array_unique($mapped));
    }
}
