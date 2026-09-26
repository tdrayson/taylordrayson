import { computed, ref, watch } from 'vue';
import { MAIN_TAB, SOCIAL_TAB, placeFields, tabFor, tabsFor } from '../lib/editor/placement.js';

/**
 * Where the editor draws each field, and which tab is showing.
 *
 * @param {import('vue').Ref<Array<object>>} fields Every serialised FieldData on the entry.
 * @param {import('vue').Ref<Array<object>>} offered The fields currently offered.
 * @param {object} form The editor's Inertia form, read for errors.
 */
export function useEditorTabs(fields, offered, form) {
    /** The offered fields split into the main tab, the other tabs and the sidebar. */
    const placed = computed(() => placeFields(offered.value));

    // A RichText body on the main tab is drawn bare under the switcher, like the page it becomes.
    const mainBody = computed(() => placed.value.main.find((field) => field.isBody) ?? null);

    const mainRest = computed(() => placed.value.main.filter((field) => field !== mainBody.value));

    /**
     * The switcher's options, from every field the type offers rather than those
     * showing now, so a tab never appears or vanishes mid-edit.
     */
    const tabs = computed(() => {
        const shown = fields.value.filter((field) => ! field.hidden);

        return tabsFor(shown).map((tab) => ({
            ...tab,
            hasError: shown.some((field) => tabFor(field) === tab.value && form.errors[field.name]),
        }));
    });

    const activeTab = ref(tabs.value[0].value);

    /** The tabs holding their own fields, i.e. all but the main and Social ones. */
    const fieldTabs = computed(() => tabs.value.filter((tab) => tab.value !== MAIN_TAB && tab.value !== SOCIAL_TAB));

    // A refused save can land on another tab, where the field cannot be fixed out of sight.
    watch(() => Object.keys(form.errors).join(','), () => {
        if (tabs.value.find((tab) => tab.value === activeTab.value)?.hasError) {
            return;
        }

        activeTab.value = tabs.value.find((tab) => tab.hasError)?.value ?? activeTab.value;
    });

    return { placed, mainBody, mainRest, tabs, activeTab, fieldTabs };
}
