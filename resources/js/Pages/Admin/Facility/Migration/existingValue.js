/* What this site holds today for a field an import is about to overwrite.
 *
 * The preview screen edits the package, not the database, so a row that already
 * exists here reads as if it were new unless the old value is put next to the
 * new one. Every helper below answers with null when there is nothing worth
 * saying — the row is new, or the import would leave the value as it is — and
 * with the current value when the import would change it.
 */

export const EMPTY_LABEL = '— empty —';

/* One comparable string, whatever the field is spelled as: a phone list and the
 * textarea that edits it have to compare as the same thing. */
export const asText = (value) => {
  if (value === null || value === undefined) return '';
  if (Array.isArray(value)) {
    return value
      .map((entry) => (entry === null || entry === undefined ? '' : String(entry).trim()))
      .filter((entry) => entry !== '')
      .join(', ');
  }

  return String(value).trim();
};

export const at = (source, path) =>
  String(path)
    .split('.')
    .reduce((value, key) => (value === null || value === undefined ? undefined : value[key]), source);

/* Latitude and longitude are stored to seven decimals, so the column says
 * 30.0444200 where the package says 30.04442 — the same spot, and marking it as
 * a change would cry wolf on every branch. Opt-in, because "0123" and "123" are
 * one number but two different phone numbers. */
const sameNumber = (a, b) => {
  const left = Number(a);
  const right = Number(b);

  return a !== '' && b !== '' && Number.isFinite(left) && Number.isFinite(right) && left === right;
};

/* Two strings that print identically are not always equal: a pasted address
 * carries non-breaking spaces, doubled spaces, line breaks, zero-width and
 * right-to-left marks. None of that is a change an operator can see or would
 * want flagged, so the comparison ignores it — the shown values are untouched. */
const looksSame = (a, b) => {
  const fold = (text) => text
    .normalize('NFC')
    .replace(/[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

  return fold(a) === fold(b);
};

export const oldText = (existing, path, current, numeric = false) => {
  if (!existing) return null;

  const before = asText(at(existing, path));
  const now = asText(current);
  if (before === now || looksSame(before, now)) return null;
  if (numeric && sameNumber(before, now)) return null;

  return before === '' ? EMPTY_LABEL : before;
};

/* A picker is compared by the row it points at, never by spelling: the package
 * may write "القاهرة" where the site says "القاهره" and mean the same
 * governorate — what matters is whether the branch would end up pointing
 * somewhere else. */
export const oldChoice = (existing, path, choice) => {
  if (!existing) return null;

  const before = at(existing, path);
  if (String(before?.id ?? '') === String(choice ?? '')) return null;

  return before ? asText(before.label) || EMPTY_LABEL : EMPTY_LABEL;
};

export const oldValue = (existing, path, current, isChoice = false, numeric = false) =>
  (isChoice ? oldChoice(existing, path, current) : oldText(existing, path, current, numeric));
