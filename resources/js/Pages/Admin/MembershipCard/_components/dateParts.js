/**
 * Mirrors CardTemplateLayoutDefaults::dateFormat(): drops the day / month / year a design switched off
 * (`show_day`, `show_month`, `show_year`; missing = shown) from a d/m/Y date inside `text`.
 * Switching everything off keeps the full date.
 */
export function applyDateParts(text, box) {
  return String(text ?? '').replace(/(\d{1,2})\/(\d{1,2})\/(\d{4})/, (full, day, month, year) => {
    const kept = [
      box?.show_day !== false && day,
      box?.show_month !== false && month,
      box?.show_year !== false && year,
    ].filter(Boolean);

    return kept.length ? kept.join('/') : full;
  });
}
