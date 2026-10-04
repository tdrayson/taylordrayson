<script setup>
import { usePage } from '@inertiajs/vue3';

/**
 * The site's author as a microformats reference, hidden from view.
 *
 * Inside an h-feed it names the author of every entry in that feed; inside a
 * single h-entry it names that entry's. Without one, a parsed entry has no
 * author at all, which is what readers and webmention receivers use to
 * attribute a post.
 *
 * Hidden rather than dropped, on the same reasoning as ProfileCard's rel-me
 * links: parsers read the DOM and ignore CSS, so it needs no place in the
 * visible design.
 */
const page = usePage();
</script>

<template>
    <span class="p-author h-card" hidden>
        <a class="p-name u-url" href="/">{{ page.props.identity.name }}</a>
        <!-- Receivers stop at this card rather than finding the full one, so it carries the photo; <data> so nothing downloads. -->
        <data class="u-photo" :value="page.props.appUrl + page.props.identity.photo"></data>
    </span>
</template>
