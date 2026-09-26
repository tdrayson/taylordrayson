/** Tab labels, mirroring App\Enums\EditorTab::label(). Key order is the tab order. */
export const TAB_LABELS = {
    summary: 'Summary',
    response: 'Response',
    details: 'Details',
};

export const MAIN_TAB = 'main';

export const SOCIAL_TAB = 'social';

/**
 * The entry's words: the RichText body an article uses, or the Prose one a note uses.
 *
 * @param {object} field One serialised FieldData.
 * @returns {boolean}
 */
export function isWords(field) {
    return field.isBody || field.type === 'prose';
}

/**
 * Split the offered fields into where they are drawn. Status and title are
 * drawn by the publish block and header, so they appear in none of these.
 *
 * @param {Array<object>} fields The fields currently offered, in declaration order.
 * @returns {{main: Array<object>, tabs: Record<string, Array<object>>, sidebar: Array<object>}}
 */
export function placeFields(fields) {
    const placed = { main: [], tabs: {}, sidebar: [] };

    fields
        .filter((field) => field.type !== 'status' && ! field.isTitle)
        .forEach((field) => {
            if (field.sidebar) {
                placed.sidebar.push(field);
            } else if (field.tab) {
                (placed.tabs[field.tab] ??= []).push(field);
            } else {
                placed.main.push(field);
            }
        });

    return placed;
}

/**
 * The switcher's options: the main tab, each placed tab in enum order, then Social.
 * The main tab is "Content" beside the entry's words, else "Details", or
 * "General" when a Details tab of its own already takes that name.
 *
 * @param {Array<object>} fields Every serialised FieldData on the entry.
 * @returns {Array<{value: string, label: string}>}
 */
export function tabsFor(fields) {
    const placed = placeFields(fields);
    const used = Object.keys(TAB_LABELS).filter((tab) => placed.tabs[tab]?.length);
    const tabs = [];

    if (placed.main.length) {
        const label = placed.main.some(isWords) ? 'Content' : 'Details';

        tabs.push({ value: MAIN_TAB, label: label === 'Details' && used.includes('details') ? 'General' : label });
    }

    used.forEach((tab) => tabs.push({ value: tab, label: TAB_LABELS[tab] }));

    tabs.push({ value: SOCIAL_TAB, label: 'Social' });

    return tabs;
}
