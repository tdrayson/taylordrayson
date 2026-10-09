<?php

namespace App\Presenters\Heads;

use App\Data\ExportData;
use App\Data\Head\HeadData;
use App\Enums\EntryStatus;
use App\Models\Page;
use App\Support\PortableText;
use App\Support\Text;

/** The head of a hand-authored content page. */
final class PageHead
{
    /**
     * The excerpt is the author's own summary and always wins; without one the
     * page's opening prose stands in, which beats the site bio on every untended page.
     *
     * @param  ExportData|null  $export  The page's export, or null when it offers no formats.
     */
    public static function for(Page $page, ?ExportData $export = null): HeadData
    {
        // A private page's description never derives from its body: crawlers
        // and link unfurlers see this whether or not the viewer has unlocked it.
        $description = Text::excerpt($page->excerpt, 200)
            ?: ($page->status === EntryStatus::Private ? null : Text::excerpt(PortableText::plainText($page->resolvedContent()), 200));

        $title = $page->title === '' ? null : $page->title;

        $head = SiteHeads::make(
            title: $title,
            description: $description ?: null,
            heading: $title,
            noindex: $page->status !== EntryStatus::Published,
        );

        return $export === null ? $head : $head->with(links: FormatLinks::for($export, $head->title));
    }
}
