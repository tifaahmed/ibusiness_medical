// Minimal Code 128 encoder — enough for membership numbers (any printable
// ASCII 32..126). Returns the raw module pattern so the caller can decide how
// wide a module is and draw the bars onto a canvas itself.
//
// Code B covers digits, letters and the punctuation our prefixes use. Purely
// numeric payloads switch to Code C, which packs two digits per code word:
// the barcode only gets ~20mm of card width, so halving the module count is
// what keeps the printed symbol inside a scannable X-dimension.

// Bar/space widths for values 0..106 (106 = stop, 7 elements; the rest 6).
const PATTERNS = [
  '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
  '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
  '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
  '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
  '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
  '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
  '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
  '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
  '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
  '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
  '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
  '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
  '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
  '211214', '211232', '2331112',
];

const START_B = 104;
const START_C = 105;
const CODE_C = 99;
const STOP = 106;

/** Code-B value for a character; anything unrepresentable becomes a space. */
function codeBValue(ch) {
  const code = ch.charCodeAt(0);
  return code >= 32 && code <= 126 ? code - 32 : 0;
}

/** Code words for `text`, excluding the start code, checksum and stop. */
function dataCodes(text) {
  if (!/^\d+$/.test(text)) {
    return { start: START_B, codes: [...text].map(codeBValue) };
  }

  // Even-length digit strings go straight into Code C; an odd one emits its
  // first digit in Code B and then switches.
  if (text.length % 2 === 0) {
    const codes = [];
    for (let i = 0; i < text.length; i += 2) codes.push(Number(text.slice(i, i + 2)));
    return { start: START_C, codes };
  }

  const codes = [codeBValue(text[0]), CODE_C];
  for (let i = 1; i < text.length; i += 2) codes.push(Number(text.slice(i, i + 2)));
  return { start: START_B, codes };
}

/**
 * Encode a string as Code 128 (subset B, or C for numeric payloads).
 *
 * @param {string} value
 * @returns {{ bars: Array<{ offset: number, width: number }>, modules: number }}
 *   `bars` lists the dark runs in module units, `modules` is the total symbol
 *   width in modules (quiet zone excluded).
 */
export function encodeCode128(value) {
  const text = String(value ?? '');
  const { start, codes: data } = dataCodes(text);

  let checksum = start;
  data.forEach((v, i) => {
    checksum += v * (i + 1);
  });

  const codes = [start, ...data, checksum % 103, STOP];

  const bars = [];
  let offset = 0;
  for (const code of codes) {
    const pattern = PATTERNS[code];
    for (let i = 0; i < pattern.length; i++) {
      const width = Number(pattern[i]);
      // Even indices are bars, odd are spaces.
      if (i % 2 === 0) bars.push({ offset, width });
      offset += width;
    }
  }

  return { bars, modules: offset };
}

/**
 * Draw a Code 128 symbol stretched to exactly `width` × `height` pixels.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {string} value
 * @param {{ x: number, y: number, width: number, height: number, color?: string }} box
 */
export function drawBarcode(ctx, value, { x, y, width, height, color = '#000000' }) {
  const { bars, modules } = encodeCode128(value);
  if (!modules) return;

  const unit = width / modules;
  ctx.fillStyle = color;
  for (const bar of bars) {
    ctx.fillRect(x + bar.offset * unit, y, bar.width * unit, height);
  }
}
