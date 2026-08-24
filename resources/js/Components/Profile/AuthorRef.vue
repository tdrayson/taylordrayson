<script setup>
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
</script>

<template>
    <a class="p-author h-card" href="/" hidden>Taylor Drayson</a>
</template>
