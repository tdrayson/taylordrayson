import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { noteSlug, responseSlug, slugify } from '../lib/editor/defaults.js';
import { isWords } from '../lib/editor/placement.js';
import { DEFAULT_TIMEZONE } from '../lib/time.js';

/**
 * The slug field's behaviour: following the title, settling at publish, and
 * previewing the URL it will produce.
 *
 * @param {object} props The editor's props (fields, values, method).
 * @param {object} form The editor's Inertia form.
 * @param {import('vue').Ref<object|null>} titleField
 * @param {import('vue').Ref<object|null>} statusField
 * @param {import('vue').Ref<object|null>} responsePreview The response context CitationField last loaded.
 */
export function useSlugField(props, form, titleField, statusField, responsePreview) {
    const page = usePage();

    const slugField = computed(() => props.fields.find((field) => field.type === 'slug') ?? null);

    /**
     * Leaving draft settles the URL: it can be linked, bookmarked or in a feed from
     * then on, so the slug stops following the title. A draft keeps tracking it
     * however often it is saved; a type with no status settles at its first save.
     */
    const slugLocked = computed(() => {
        if (props.method === 'post') {
            return false;
        }

        return statusField.value ? props.values[statusField.value.name] !== 'draft' : true;
    });

    /**
     * Until then it follows the title, unless it has been typed by hand: writing a
     * slug yourself is the way to say you want that one.
     *
     * A reloaded draft has to work that out from the values alone. A slug matching
     * its title is one this generated, so it carries on generating; anything else
     * was chosen, and is left alone.
     */
    const slugEdited = ref((() => {
        const slug = props.values[slugField.value?.name];

        return Boolean(slug) && slug !== slugify(props.values[titleField.value?.name]);
    })());

    watch(() => (titleField.value ? form[titleField.value.name] : null), (title) => {
        if (! slugField.value || slugEdited.value || slugLocked.value) {
            return;
        }

        form[slugField.value.name] = slugify(title);
    });

    /**
     * What the slug will be if the field is left empty. Only types that declare a
     * fallback derive one; elsewhere the slug follows the title and is never blank.
     */
    const derivedSlug = computed(() => {
        if (! slugField.value?.fallback) {
            return '';
        }

        // A response's slug is stored when it is first posted, so an edit keeps
        // whatever it got then, and only a new one previews it.
        const response = props.method === 'post'
            ? responseSlug({
                kind: form.response_kind,
                url: form.response_url,
                rsvp: form.rsvp_value,
                preview: responsePreview.value,
            })
            : null;

        if (response) {
            return response;
        }

        const words = props.fields.find(isWords);

        return noteSlug(words ? form[words.name] : null, slugField.value.fallback);
    });

    /**
     * The day the entry will sit under. A date field left empty is stamped at save
     * rather than on load (see defaultValueFor), so preview today rather than
     * dropping the date and showing a URL the entry will never have.
     */
    const previewDay = computed(() => {
        const chosen = String(form.occurred_at ?? '').slice(0, 10);

        if (chosen) {
            return chosen;
        }

        const field = props.fields.find((one) => one.name === 'occurred_at');

        if (! field?.defaultsToNow) {
            return '';
        }

        const timezone = page.props.ambient?.location?.timezone ?? DEFAULT_TIMEZONE;

        // en-CA renders as YYYY-MM-DD, which is the shape the URL wants.
        return new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
        }).format(new Date());
    });

    /**
     * The URL this entry will answer on, as the slug is typed. Dated types live
     * under their day; anything else sits at the site root.
     */
    const slugPreview = computed(() => {
        const slug = form[slugField.value?.name] || derivedSlug.value;

        if (! slug) {
            return null;
        }

        const day = previewDay.value;

        return day ? `/${day.replaceAll('-', '/')}/${slug}` : `/${slug}`;
    });

    return { slugField, slugLocked, slugEdited, derivedSlug, slugPreview };
}
