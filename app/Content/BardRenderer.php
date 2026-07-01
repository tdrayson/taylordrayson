<?php

namespace App\Content;

use Statamic\Fields\Value;

class BardRenderer
{
    /**
     * Convert a Bard field value to an HTML string.
     *
     * Statamic augments a Bard field (with no sets configured) by calling
     * Augmentor::convertToHtml(), which returns an HTML string via Tiptap.
     * The augmentedValue() call returns a Value object; calling ->value()
     * on it returns the augmented HTML string.
     *
     * When tests seed content as a plain string (no Bard structure), the
     * augmentor returns it as-is, so we also accept strings directly.
     */
    public function toHtml(mixed $bardValue): string
    {
        if ($bardValue instanceof Value) {
            return (string) $bardValue->value();
        }

        return (string) $bardValue;
    }
}
