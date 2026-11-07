/**
 * The printed sheet's geometry — pure numbers, no DOM and no PDF library, so the PDF builder AND the sheet editor
 * page use exactly the same maths (what the editor shows is what the PDF prints) and it can be tested in plain Node.
 *
 * RULE: a sheet holds 10 cards (2 × 5 of 8.5 × 5.5 cm). Down the sheet A4 has little spare (5 rows of 5.5 cm leave
 * 2.2 cm), so what is left after the top strip is shared by the rows evenly; the cut lines run on the band borders.
 * Across the sheet the two columns hug the paper's left and right edges, all the free width sits in the middle around
 * the cut line.
 *
 * Saved layout (setting `card_sheet_layout`, see App\Support\CardPrint::sheet), all lengths in cm:
 *   { top_reserve, side_margin, pin_y, pin_dx, guides, h_lines: [dy…], v_lines: [dx…], cards: [ { dx, dy, w, h } … ] }
 * h_lines[i] / v_lines[i] move the i-th cut line (row border / column divider) from where the grid puts it; pin_dx moves the pin mark sideways.
 * `cards[i]` belongs to slot i (row by row, left to right). dx/dy move that card from where the grid puts it; w/h give it
 * its own size (null = the printed card size). A back always lands exactly behind its front: x' = PAGE_W − x − w.
 */

export const PAGE_W = 21.0; // A4 portrait, cm
export const PAGE_H = 29.7;
export const PIN_RADIUS = 0.15; // the pin mark: a small circle with a cross, 0.3 cm wide

// Defaults — what the sheet looks like when nothing has been saved.
export const SHEET_DEFAULTS = Object.freeze({
  top_reserve: 1.5, // the strip above the first row (it holds the pin mark); every extra mm is taken from the gaps between rows
  side_margin: 0.25, // what is left between a column and the paper's edge
  pin_y: 0.4, // the pin mark's centre, measured from the TOP EDGE of the sheet
  guides: true, // thin light cut lines
});

// Floors/limits (cm). The row floor is kept low on purpose: the count is a RULE, and a row is only dropped when
// top_reserve + 5 × (5.5 + 2 × CELL_PAD_Y_MIN) would not fit, i.e. top_reserve above ~1.8.
export const CELL_PAD_Y_MIN = 0.04;
export const MIN_COLUMN_GAP = 1.0;
const BOTTOM_RESERVE = 0; // the grid runs to the foot of the sheet (a printer that cannot print to the edge will clip the last row)
const MAX_CARDS = 40;
const MAX_H_LINES = 12;
const MAX_V_LINES = 4;

export const DEFAULT_PRINT = Object.freeze({ width: 8.5, height: 5.5, back_width: 8.0, back_height: 5.0 });

const num = (v, fallback) => {
  const n = typeof v === 'string' && v.trim() === '' ? NaN : Number(v);
  return Number.isFinite(n) ? n : fallback;
};
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const r3 = (v) => Math.round(v * 1000) / 1000;

/** A saved/posted layout → a clean, clamped one (never throws; junk falls back to the defaults). */
export function normalizeSheet(raw) {
  const s = raw && typeof raw === 'object' ? raw : {};
  const cards = (Array.isArray(s.cards) ? s.cards : []).slice(0, MAX_CARDS).map((c) => {
    const o = c && typeof c === 'object' ? c : {};
    const w = num(o.w, null);
    const h = num(o.h, null);
    return {
      dx: r3(clamp(num(o.dx, 0), -PAGE_W, PAGE_W)),
      dy: r3(clamp(num(o.dy, 0), -PAGE_H, PAGE_H)),
      w: w == null ? null : r3(clamp(w, 1, PAGE_W)),
      h: h == null ? null : r3(clamp(h, 1, PAGE_H)),
    };
  });
  const lines = (arr, max) => (Array.isArray(arr) ? arr : []).slice(0, max).map((v) => r3(clamp(num(v, 0), -15, 15)));
  return {
    h_lines: lines(s.h_lines, MAX_H_LINES),
    v_lines: lines(s.v_lines, MAX_V_LINES),
    pin_dx: r3(clamp(num(s.pin_dx, 0), -10, 10)),
    top_reserve: r3(clamp(num(s.top_reserve, SHEET_DEFAULTS.top_reserve), 0.2, 6)),
    side_margin: r3(clamp(num(s.side_margin, SHEET_DEFAULTS.side_margin), 0, 5)),
    pin_y: r3(clamp(num(s.pin_y, SHEET_DEFAULTS.pin_y), 0.1, 5)),
    guides: s.guides === undefined ? SHEET_DEFAULTS.guides : Boolean(s.guides),
    cards,
  };
}

/**
 * Sheet geometry for the printed card size (settings CardPrint) and the saved layout (`print.sheet`):
 * as many columns/rows as fit on A4.
 */
export function sheetLayout(print) {
  const w = Number(print?.width) > 0 ? Number(print.width) : DEFAULT_PRINT.width;
  const h = Number(print?.height) > 0 ? Number(print.height) : DEFAULT_PRINT.height;
  const sheet = normalizeSheet(print?.sheet);
  const cols = Math.max(1, Math.floor((PAGE_W - 2 * sheet.side_margin + MIN_COLUMN_GAP) / (w + MIN_COLUMN_GAP)));
  const avail = PAGE_H - sheet.top_reserve - BOTTOM_RESERVE;
  const rows = Math.max(1, Math.floor(avail / (h + 2 * CELL_PAD_Y_MIN)));
  // The rows share the whole band below the strip, so each card is centred vertically in a band of equal height.
  const cellH = avail / rows;
  const gridTop = sheet.top_reserve;
  // Columns spread from the left edge to the right edge; one column alone sits in the middle.
  const span = PAGE_W - 2 * sheet.side_margin - w;
  const colX = (c) => (cols === 1 ? (PAGE_W - w) / 2 : sheet.side_margin + (c * span) / (cols - 1));
  return {
    w,
    h,
    cols,
    rows,
    perPage: cols * rows,
    cellH,
    padY: (cellH - h) / 2,
    colX,
    gridTop,
    gridH: rows * cellH,
    sheet,
    cards: sheet.cards,
    guides: sheet.guides,
    pinX: PAGE_W / 2 + sheet.pin_dx,
    pinY: sheet.pin_y,
  };
}

/**
 * Where slot `pos` is on the sheet: { x, y, w, h } in cm. `mirror` gives the spot the BACK of that card prints at (the same
 * rectangle turned left-to-right across the sheet's centre line), so a flipped sheet lines every back up behind its front.
 */
export function slotRect(L, pos, mirror = false) {
  const col = pos % L.cols;
  const row = Math.floor(pos / L.cols);
  const o = L.cards[pos] || {};
  const w = o.w ?? L.w;
  const h = o.h ?? L.h;
  const x = L.colX(col) + (o.dx || 0);
  const y = L.gridTop + row * L.cellH + L.padY + (o.dy || 0);
  return mirror ? { x: PAGE_W - x - w, y, w, h } : { x, y, w, h };
}

/**
 * Where the BACK of slot `pos` prints: the back picture at its own (smaller) printed size, CENTRED in the mirrored rectangle of
 * its front — so moving or resizing a card moves its back with it, and the small back always sits in the middle of the big card.
 * Returns { x, y, w, h } of the back picture and `outer`, the full rectangle (the card's footprint behind its front).
 */
export function backRect(L, pos, print) {
  const outer = slotRect(L, pos, true);
  const backW = Number(print?.back_width) > 0 ? Number(print.back_width) : DEFAULT_PRINT.back_width;
  const backH = Number(print?.back_height) > 0 ? Number(print.back_height) : DEFAULT_PRINT.back_height;
  const w = Math.min(outer.w, backW);
  const h = Math.min(outer.h, backH);
  return { x: outer.x + (outer.w - w) / 2, y: outer.y + (outer.h - h) / 2, w, h, outer };
}

/** Kept for callers that only want the corner. */
export function slotXY(L, pos, mirror = false) {
  const { x, y } = slotRect(L, pos, mirror);
  return { x, y };
}

/**
 * The pin mark and the cut lines, in cm, for drawing. `mirror` gives them as they print on the BACK of the sheet: the sheet is
 * turned over, so everything across the sheet flips about the centre line (x' = PAGE_W − x) while heights stay put.
 */
export function guideShapes(L, mirror = false) {
  const flip = (x) => (mirror ? PAGE_W - x : x);
  const hLines = [];
  for (let r = 0; r <= L.rows; r++) hLines.push(L.gridTop + r * L.cellH + (L.sheet.h_lines[r] || 0));
  const vLines = [];
  for (let c = 0; c < L.cols - 1; c++) vLines.push(flip((L.colX(c) + L.w + L.colX(c + 1)) / 2 + (L.sheet.v_lines[c] || 0)));
  return { pin: { x: flip(L.pinX), y: L.pinY, r: PIN_RADIUS }, hLines, vLines };
}
