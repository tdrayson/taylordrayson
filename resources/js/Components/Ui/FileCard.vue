<script setup>
import { computed } from 'vue';
import Button from './Button.vue';
import Icon from './Icon.vue';
import { fileKind, formatBytes, githubDownloadUrl, githubReleaseUrl, versionLabel, versionedFilename } from '../../lib/files';

/**
 * A downloadable file in a document.
 *
 * Two kinds, and the difference is where the truth lives. An upload carries its
 * own name, size and URL, fixed at the moment it was attached. A release
 * carries only the repository and asset name, and the version and size beside
 * it are looked up server-side, so publishing a new release updates the card
 * without the article being touched.
 *
 * @param {'upload'|'github'} source
 * @param {object|null} release Resolved release data, for a github file.
 */
const props = defineProps({
    source: { type: String, default: 'upload' },
    url: { type: String, default: null },
    name: { type: String, default: null },
    mime: { type: String, default: null },
    size: { type: Number, default: null },
    repo: { type: String, default: null },
    asset: { type: String, default: null },
    poster: { type: String, default: null },
    release: { type: Object, default: null },
    // Overrides the name shown, for a file whose own name reads badly.
    title: { type: String, default: null },
});

const isRelease = computed(() => props.source === 'github');

// A release's own values win over the node's, since the node holds no copy of
// them and the lookup is the only thing that knows what the latest release is.
const bytes = computed(() => (isRelease.value ? props.release?.size ?? null : props.size));

const version = computed(() => (isRelease.value ? versionLabel(props.release?.version) : null));

/**
 * The name on the card. A release earns its version here, that being the only
 * place it can appear: the asset GitHub holds has to keep one constant name.
 * An explicit title wins over both, having been chosen rather than derived.
 */
const filename = computed(() => {
    if (props.title) {
        return props.title;
    }

    return isRelease.value
        ? versionedFilename(props.release?.name ?? props.asset, version.value)
        : props.name;
});

const kind = computed(() => fileKind(filename.value, props.mime));

const href = computed(() => (isRelease.value
    ? githubDownloadUrl(props.repo, props.asset)
    : props.url));

/**
 * The facts under the name, in one format whatever the source: what it is, how
 * big, and for a release when it shipped. Anything unresolved is simply absent
 * rather than shown empty, so a cold cache reads as an upload with no size yet
 * rather than as blanks.
 */
const facts = computed(() => [
    kind.value.label,
    formatBytes(bytes.value),
    isRelease.value && props.release?.releasedAt ? props.release.releasedAt : null,
].filter(Boolean).join(', '));

const releaseUrl = computed(() => (isRelease.value ? githubReleaseUrl(props.repo) : null));

// The name the browser saves under, which is the real file rather than the
// label: a title carries no extension, and saving without one is unopenable.
// Cross-origin downloads ignore this, so it is upload-only by nature.
const downloadName = computed(() => (isRelease.value ? null : props.name));

const label = computed(() => `Download ${filename.value ?? 'file'}`);
</script>

<template>
    <div v-if="href" class="not-prose my-8 max-w-media">
        <!-- Stacked until there is room for a row: side by side on a phone
             leaves the name a column too narrow to read. -->
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-100 bg-neutral-25 p-4 sm:flex-row sm:items-center">
            <!-- Top-aligned while stacked: the icon centred against three lines
                 of wrapped name and facts floats away from the name it labels. -->
            <div class="flex min-w-0 flex-1 items-start gap-4 sm:items-center">
                <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-neutral-50 bg-neutral-0">
                    <img v-if="poster" :src="poster" alt="" class="size-full object-cover">
                    <Icon v-else :name="kind.icon" class="size-6 text-neutral-500" />
                </div>

                <div class="min-w-0 flex-1">
                    <!-- Wraps rather than truncating: a clipped filename loses
                         its extension, which is half of what it tells you. -->
                    <p class="break-words text-body font-semibold text-neutral-900">{{ filename }}</p>

                    <p class="mt-0.5 text-meta text-neutral-500">{{ facts }}</p>

                    <!-- On its own line rather than in front of the facts, which
                         it is long enough to push out of the card. Doubles as the
                         mark saying this download leaves the site and as the way
                         to the release notes. -->
                    <a
                        v-if="releaseUrl"
                        :href="releaseUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="`${repo} release on GitHub, opens in a new tab`"
                        class="mt-1 inline-flex max-w-full items-center gap-1 rounded-sm text-meta text-neutral-700 underline underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                    >
                        <Icon name="GithubIcon" class="size-3.5 shrink-0" />
                        <span class="truncate">{{ repo }}</span>
                    </a>
                </div>
            </div>

            <Button
                :href="href"
                external
                :download="downloadName"
                :target="isRelease ? '_blank' : null"
                :rel="isRelease ? 'noopener noreferrer' : null"
                variant="primary"
                class="w-full shrink-0 sm:w-auto"
                :aria-label="isRelease ? `${label}, opens in a new tab` : label"
            >
                <Icon name="Download01Icon" class="size-4" />
                Download
            </Button>
        </div>
    </div>
</template>
