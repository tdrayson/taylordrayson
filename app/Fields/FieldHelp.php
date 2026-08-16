<?php

namespace App\Fields;

/**
 * Help text shared by fields that play the same role across types, so the same
 * field cannot end up explained two different ways on two different forms.
 */
final class FieldHelp
{
    /**
     * The short lead paragraph an entry opens with, which also feeds its card,
     * search result and social preview. Stored as `excerpt` on the types that
     * have a body of their own and `description` on the ones that do not.
     */
    public const SUMMARY = 'Opens the entry, and stands in for it on cards, in search and in social previews.';
}
