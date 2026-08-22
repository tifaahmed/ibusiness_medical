import { usePage } from '@inertiajs/vue3';

/**
 * Helpers for the translatable fields the admin resources hand down.
 *
 * A field arrives either as a locale map ({ ar, en }) or — for rows written
 * before the field became translatable — as a bare string. Both shapes have to
 * render, so every helper here accepts either.
 */
export function useTranslatedField() {
  const page = usePage();
  const locale = page.props.locale || 'ar';

  /** The single best string for the current locale. */
  const getTranslated = (value) => {
    if (typeof value === 'string') return value;
    if (typeof value === 'object' && value !== null) {
      return value[locale] || value.ar || value.en || Object.values(value)[0] || '';
    }
    return '';
  };

  /**
   * Both locales side by side. Admins open a view screen to proofread
   * translations, so collapsing to the active locale hides the very thing they
   * came to check. A legacy plain string has no second locale to show.
   */
  const translationPairs = (value) => {
    if (typeof value === 'string') {
      return value.trim() !== '' ? [{ lang: 'AR', text: value }] : [];
    }
    if (typeof value === 'object' && value !== null) {
      return [
        { lang: 'AR', text: (value.ar || '').trim() },
        { lang: 'EN', text: (value.en || '').trim() },
      ].filter((pair) => pair.text !== '');
    }
    return [];
  };

  return { locale, getTranslated, translationPairs };
}
