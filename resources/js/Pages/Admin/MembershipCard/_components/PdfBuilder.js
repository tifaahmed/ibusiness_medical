import { jsPDF } from 'jspdf';
import JSZip from 'jszip';
import { loadImage, renderCardCanvas } from './cardRenderer.js';

// A4 portrait dimensions in cm.
const PAGE_W = 21.0;
const PAGE_H = 29.7;
// RULE: a sheet holds 10 cards (2 × 5 of 8.5 × 5.5 cm). Everything else gives way to that.
// Every card sits in the middle of its own square (a "cell") and the cut lines are the cells' borders. Across the sheet
// there is plenty of room, so the card keeps CELL_PAD_X clear on each side. Down the sheet A4 has little spare (5 rows
// of 5.5 cm leave 2.2 cm in all), so what is left after the top and bottom reserves is shared by the rows evenly —
// the card is centred vertically in its square too, just with a thinner border of paper.
const CELL_PAD_X = 0.5;
const CELL_PAD_Y_MIN = 0.08; // never tighter than this per side, otherwise a row would not fit and the count would drop
// Room kept free at the top and the bottom of every sheet. The top one holds the pin/staple mark, so it is the bigger.
const TOP_RESERVE = 0.85;
const BOTTOM_RESERVE = 0.4;
const PIN_RADIUS = 0.15; // the pin mark: a small circle with a cross, 0.3 cm wide
const DEFAULT_PRINT = { width: 8.5, height: 5.5, back_width: 8.0, back_height: 5.0 };
const BACK_FALLBACK = '/images/cards/card-backside.png';

/**
 * Sheet geometry for a card size taken from the project settings (CardPrint):
 * as many columns/rows as fit on A4, centred.
 */
export function sheetLayout(print) {
  const w = Number(print?.width) > 0 ? Number(print.width) : DEFAULT_PRINT.width;
  const h = Number(print?.height) > 0 ? Number(print.height) : DEFAULT_PRINT.height;
  const cellW = w + 2 * CELL_PAD_X;
  const cols = Math.max(1, Math.floor(PAGE_W / cellW));
  const avail = PAGE_H - TOP_RESERVE - BOTTOM_RESERVE;
  const rows = Math.max(1, Math.floor(avail / (h + 2 * CELL_PAD_Y_MIN)));
  // The rows share the whole band between the two reserves, so each card is centred in a square of equal height.
  const cellH = avail / rows;
  const gridW = cols * cellW;
  const gridH = rows * cellH;
  const gridLeft = (PAGE_W - gridW) / 2;
  const gridTop = TOP_RESERVE;
  return {
    w,
    h,
    cols,
    rows,
    perPage: cols * rows,
    cellW,
    cellH,
    padX: CELL_PAD_X,
    padY: (cellH - h) / 2,
    gridLeft,
    gridTop,
    gridW,
    gridH,
    // The pin mark sits in the middle of the strip above the grid, clear of every square.
    pinX: PAGE_W / 2,
    pinY: gridTop / 2,
  };
}

function slotXY(L, pos, mirror) {
  let col = pos % L.cols;
  const row = Math.floor(pos / L.cols);
  // Flipped: the columns run right-to-left, so a sheet printed on its back
  // lines every back up behind its own front.
  if (mirror) col = L.cols - 1 - col;
  // The card's own position: centred inside its cell.
  return { x: L.gridLeft + col * L.cellW + L.padX, y: L.gridTop + row * L.cellH + L.padY };
}

/**
 * The pin/staple mark and the centre cutting line. Drawn at the SAME spot on every page — fronts and backs — and it lies on the sheet's centre line,
 * which the mirrored back keeps in place, so a stack of printed sheets can be pinned through one point and cut alike.
 */
function drawPin(pdf, L) {
  pdf.setDrawColor(150);
  pdf.setLineWidth(0.02);
  pdf.circle(L.pinX, L.pinY, PIN_RADIUS, 'S');
  pdf.line(L.pinX - PIN_RADIUS * 0.6, L.pinY, L.pinX + PIN_RADIUS * 0.6, L.pinY);
  pdf.line(L.pinX, L.pinY - PIN_RADIUS * 0.6, L.pinX, L.pinY + PIN_RADIUS * 0.6);

  // Cutting guides: thin, light lines = the borders of the squares, so every card sits in the middle of its own square.
  // Same place on fronts and backs (the mirror only swaps columns; the borders and the centre stay put).
  pdf.setDrawColor(200);
  pdf.setLineWidth(0.012);
  const right = L.gridLeft + L.gridW;
  const bottom = L.gridTop + L.gridH;
  for (let r = 0; r <= L.rows; r++) {
    const y = L.gridTop + r * L.cellH;
    pdf.line(L.gridLeft, y, right, y);
  }
  for (let c = 0; c <= L.cols; c++) {
    const x = L.gridLeft + c * L.cellW;
    if (Math.abs(x - PAGE_W / 2) < 0.01) continue; // the centre line is drawn full height below
    pdf.line(x, L.gridTop, x, bottom);
  }
  // The line down the middle of the sheet runs the full height, to cut the paper in half; it stops short of the pin mark.
  pdf.line(PAGE_W / 2, 0, PAGE_W / 2, L.pinY - PIN_RADIUS - 0.05);
  pdf.line(PAGE_W / 2, L.pinY + PIN_RADIUS + 0.05, PAGE_W / 2, PAGE_H);
}


// The same picture turned left-to-right, as a JPEG data URL.
function flippedDataUrl(source, w, h) {
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  const ctx = c.getContext('2d');
  ctx.translate(w, 0);
  ctx.scale(-1, 1);
  ctx.drawImage(source, 0, 0, w, h);
  return c.toDataURL('image/jpeg', 0.92);
}

async function backDataUrl(template, mirrorImage = false) {
  const img = await loadImage(template?.back_url || BACK_FALLBACK);
  if (!img) throw new Error('The card back image could not be loaded.');
  const c = document.createElement('canvas');
  c.width = img.naturalWidth;
  c.height = img.naturalHeight;
  if (mirrorImage) return flippedDataUrl(img, c.width, c.height);
  c.getContext('2d').drawImage(img, 0, 0);
  return c.toDataURL('image/jpeg', 0.92);
}

/**
 * Backs only, or fronts + backs, for a whole batch.
 *  - mode 'flipped': one sheet of backs per sheet of fronts, columns mirrored.
 *  - mode 'mirror':  like 'both', but every page is the true mirror image of the
 *                    sheet (artwork turned left-to-right too, columns reversed).
 *  - mode 'both':    sheet of fronts, then the matching mirrored sheet of backs
 *                    (print double-sided, flip on the long edge).
 * The back is drawn at its own width/height (settings), centred in
 * the card's slot.
 *
 * @returns {Promise<Blob>}
 */
export async function buildBackPdf(memberships, partner, template, print, mode, onProgress, overridesFor = null, contact = null) {
  if (!memberships.length) throw new Error('No memberships to render');
  const L = sheetLayout(print);
  const bw = Math.min(L.w, Number(print?.back_width) > 0 ? Number(print.back_width) : DEFAULT_PRINT.back_width);
  const bh = Math.min(L.h, Number(print?.back_height) > 0 ? Number(print.back_height) : DEFAULT_PRINT.back_height);
  const mirror = mode === 'mirror';
  // 'fronts': only the fronts of the chosen cards, no back sheets.
  const frontsOnly = mode === 'fronts';
  const withFronts = mode === 'both' || mirror || frontsOnly;
  const back = frontsOnly ? null : await backDataUrl(template, mirror);
  const pdf = new jsPDF({ unit: 'cm', format: 'a4', orientation: 'portrait' });
  const total = memberships.length;
  const steps = frontsOnly ? total : total * 2;
  const pages = Math.ceil(total / L.perPage);
  let first = true;
  // Every sheet gets the pin mark in the same place; the first one exists already, later ones are added below.
  const newPage = () => { if (!first) pdf.addPage(); first = false; drawPin(pdf, L); };

  for (let page = 0; page < pages; page++) {
    const from = page * L.perPage;
    const count = Math.min(L.perPage, total - from);

    if (withFronts) {
      newPage();
      for (let k = 0; k < count; k++) {
        const canvas = await renderCardCanvas({
          membership: memberships[from + k],
          template,
          partner,
          overrides: overridesFor ? overridesFor(from + k) : null,
          contact,
        });
        const { x, y } = slotXY(L, k, mirror);
        const front = mirror ? flippedDataUrl(canvas, canvas.width, canvas.height) : canvas.toDataURL('image/jpeg', 0.92);
        pdf.addImage(front, 'JPEG', x, y, L.w, L.h, undefined, 'FAST');
        if (onProgress) onProgress((from + k + 1) / steps);
      }
    }

    if (frontsOnly) continue;
    newPage();
    for (let k = 0; k < count; k++) {
      const { x, y } = slotXY(L, k, !mirror);
      pdf.addImage(back, 'JPEG', x + (L.w - bw) / 2, y + (L.h - bh) / 2, bw, bh, undefined, 'FAST');
    }
    if (onProgress) onProgress(withFronts ? (from + count + total) / (total * 2) : (from + count) / total);
  }

  return pdf.output('blob');
}

/**
 * Render every membership once and produce BOTH:
 *  - a multi-page A4 PDF with as many cards per page as the card size allows (10 at 8.5×5.5 cm), and
 *  - a zip of single-card PDFs (one PDF per card, sized 8.5×5.5 cm).
 *
 * Each card is rasterised exactly once and shared between the two outputs.
 *
 * @param {Array<{ membership_number: string, slug: string }>} memberships
 * @param {object|null} partner
 * @param {object|null} template card_templates row driving the layout
 * @param {(progress: number) => void} [onProgress]
 * @param {(idx0: number) => object|null} [overridesFor]
 * @param {object|null} [contact] { facebook, website, phone } text overrides
 * @returns {Promise<{ batchPdf: Blob, zip: Blob }>}
 */
export async function buildBatchAndZip(memberships, partner, template, onProgress, overridesFor = null, contact = null, print = null) {
  if (!memberships.length) {
    throw new Error('No memberships to render');
  }

  const L = sheetLayout(print);
  const batchPdf = new jsPDF({ unit: 'cm', format: 'a4', orientation: 'portrait' });
  const zip = new JSZip();

  for (let i = 0; i < memberships.length; i++) {
    const canvas = await renderCardCanvas({
      membership: memberships[i],
      template,
      partner,
      overrides: overridesFor ? overridesFor(i) : null,
      contact,
    });
    // JPEG at 0.92 keeps file size reasonable while staying visually crisp
    // at the print size we land on.
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);

    // ---- Batch PDF placement (10 per A4 page) ----
    const posOnPage = i % L.perPage;
    if (i > 0 && posOnPage === 0) batchPdf.addPage();
    if (posOnPage === 0) drawPin(batchPdf, L);
    const { x, y } = slotXY(L, posOnPage, false);
    batchPdf.addImage(dataUrl, 'JPEG', x, y, L.w, L.h, undefined, 'FAST');

    // ---- Single-card PDF for the zip ----
    const single = new jsPDF({ unit: 'cm', format: [L.w, L.h], orientation: 'landscape' });
    single.addImage(dataUrl, 'JPEG', 0, 0, L.w, L.h, undefined, 'FAST');
    const rawName = String(memberships[i].membership_number ?? `card-${i + 1}`);
    const safeName = rawName.replace(/[^A-Za-z0-9_-]+/g, '_') || `card-${i + 1}`;
    zip.file(`${safeName}.pdf`, single.output('blob'));

    if (onProgress) onProgress((i + 1) / memberships.length);
  }

  const zipBlob = await zip.generateAsync({ type: 'blob' });
  return { batchPdf: batchPdf.output('blob'), zip: zipBlob };
}
