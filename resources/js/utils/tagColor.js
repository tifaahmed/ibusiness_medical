/**
 * Style for a tag pill that stays readable on the purple dashboard background.
 *
 * A translucent tint of the tag's own colour (the old look) disappears when the
 * tag is blue/purple on a purple page. So the pill is SOLID in the tag colour,
 * with black or white text picked by the colour's luminance and a light ring
 * that separates it from the background.
 */
export const readableTextOn = (hex) => {
  const m = /^#?([0-9a-f]{6})$/i.exec((hex || '').trim());
  if (!m) return '#FFFFFF';
  const n = parseInt(m[1], 16);
  const lin = (v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  };
  const L = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
  // Pick whichever of black / white contrasts more.
  return (L + 0.05) / 0.05 > 1.05 / (L + 0.05) ? '#111827' : '#FFFFFF';
};

export const tagPillStyle = (color) => {
  const c = color || '#6B7280';
  return {
    backgroundColor: c,
    color: readableTextOn(c),
    borderColor: 'rgba(255,255,255,0.55)',
    boxShadow: '0 0 0 1px rgba(255,255,255,0.55) inset',
  };
};
