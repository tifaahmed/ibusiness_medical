/**
 * Reading a lookup row's name when both languages matter.
 *
 * Facility types, governorates, cities, sales reps and tags all keep their name
 * as a { ar, en } map. A picker that renders only the reader's locale is fine
 * until the two lists have to be reconciled — choosing the right "الإسكندرية"
 * out of several similar English spellings, or checking that a branch name
 * carries its city in both languages — so the option label carries both.
 *
 * Every reader here tolerates the older shape too: a bare string, which is what
 * a translatable column collapses to when it is serialized through the model's
 * accessor rather than getTranslations().
 */

/** The name in one language, or '' when the row does not carry it. */
export const nameIn = (name, lang) => {
    if (!name) return '';
    if (typeof name === 'string') return name.trim();
    if (typeof name === 'object') return String(name[lang] || '').trim();

    return '';
};

/** The name to lead with: the reader's language, then whatever else is there. */
export const primaryName = (name, locale) => {
    if (!name) return '';
    if (typeof name === 'string') return name.trim();

    return nameIn(name, locale)
        || nameIn(name, 'ar')
        || nameIn(name, 'en')
        || String(Object.values(name)[0] || '').trim();
};

/**
 * The other language's spelling, when there is one and it says something the
 * primary does not. A row named identically in both adds nothing by repeating.
 */
export const secondaryName = (name, locale) => {
    const other = nameIn(name, locale === 'ar' ? 'en' : 'ar');

    return other && other !== primaryName(name, locale) ? other : '';
};

/**
 * Both spellings in one string, for the pickers whose option label is plain
 * text. The reader's language leads and the other trails behind a dash — which
 * also means a search over the label matches either language.
 */
export const bilingualLabel = (name, locale) => {
    const primary = primaryName(name, locale);
    const other = secondaryName(name, locale);

    return other ? `${primary} — ${other}` : primary;
};
