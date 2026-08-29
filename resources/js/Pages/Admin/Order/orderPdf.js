/**
 * The two printable forms of an order: the internal copy and the customer's.
 *
 * They are deliberately not the same document with a flag flipped. The admin
 * copy carries what the shop knows — cost, profit, the buyer's IP, the audit
 * trail; the receipt carries what the customer is entitled to see and nothing
 * else. Keeping the two field lists apart in code is what stops a margin
 * ending up in a customer's hands the next time somebody adds a column.
 *
 * Rendered by rasterising real HTML rather than by drawing text into jsPDF.
 * jsPDF's built-in fonts cannot shape Arabic — names, addresses and product
 * names here are Arabic more often than not, and would come out as reversed
 * unjoined letters. The browser already knows how to lay that out, so it does.
 *
 * The printable node styles itself INLINE, in hex, and borrows nothing from
 * the app's stylesheet: html2canvas cannot parse the `oklch()` colours the
 * theme is built from, and would throw on the first one it met.
 */

/* A line's name is read through the same helper the screen uses, so the
   printed order and the order on screen can never call a line two things. */
import { translatedName } from './orderDisplay.js';

/** A4 at 96dpi, which is the coordinate system the printable node is built in. */
const PAGE_W_PX = 794;
const PAGE_H_PX = 1123;
/** A4 in points, which is what jsPDF measures in. */
const PAGE_W_PT = 595.28;

const INK = '#111827';
const MUTED = '#6b7280';
const LINE = '#d1d5db';
const ACCENT = '#0f766e';

const esc = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const money = (value, currency) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '—';
    const formatted = new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(parsed);
    return `${formatted} ${currency}`;
};

const dash = (value) => {
    const text = String(value ?? '').trim();
    return text === '' ? '—' : esc(text);
};

/**
 * The address as one readable line, in the order a courier reads it.
 * Empty parts drop out rather than leaving a trail of commas.
 */
const addressLine = (order) => [
    order.customer_street,
    order.customer_building_number && `Bldg ${order.customer_building_number}`,
    order.customer_floor_number && `Floor ${order.customer_floor_number}`,
    order.customer_apartment_number && `Apt ${order.customer_apartment_number}`,
    order.customer_city,
    order.customer_governorate,
].filter(part => String(part ?? '').trim() !== '').join('، ');

/* ------------------------------------------------------------------ *
 * Markup helpers. Every block that must not be sliced across a page
 * boundary carries `data-atom` — see `cutPoints()`.
 * ------------------------------------------------------------------ */

const section = (title, body) => `
  <div style="margin:0 0 18px">
    <div style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:${ACCENT};border-bottom:1px solid ${LINE};padding-bottom:5px;margin-bottom:9px">${esc(title)}</div>
    ${body}
  </div>`;

const pairs = (rows) => `
  <table style="width:100%;border-collapse:collapse;font-size:12px">
    ${rows.filter(Boolean).map(([label, value]) => `
      <tr data-atom>
        <td style="padding:3px 0;color:${MUTED};width:34%;vertical-align:top">${esc(label)}</td>
        <td style="padding:3px 0;color:${INK};font-weight:600;vertical-align:top">${value}</td>
      </tr>`).join('')}
  </table>`;

const linesTable = (order, t, locale, currency, { withMargin }) => {
    const head = [
        t.order?.product || 'Product',
        t.order?.quantity || 'Qty',
        t.order?.unit_price || 'Unit price',
        t.order?.line_total || 'Line total',
        ...(withMargin ? [t.order?.cost_price || 'Cost', t.order?.profit_price || 'Profit'] : []),
    ];

    const th = (label, align = 'start') =>
        `<th style="text-align:${align};padding:6px 8px;font-size:11px;color:${MUTED};font-weight:700;border-bottom:1px solid ${LINE}">${esc(label)}</th>`;
    const td = (html, align = 'start') =>
        `<td style="text-align:${align};padding:7px 8px;font-size:12px;color:${INK};border-bottom:1px solid #f3f4f6">${html}</td>`;

    const rows = (order.products || []).map((line) => {
        const name = translatedName(line.name, locale) || line.slug || '—';
        /* The struck-through old price is the discount made visible on the
           line it applied to, which is the only place it means anything. */
        const unit = line.old_price != null && Number(line.old_price) > Number(line.new_price)
            ? `<span style="color:${MUTED};text-decoration:line-through;margin-inline-end:6px">${money(line.old_price, currency)}</span>${money(line.new_price, currency)}`
            : money(line.new_price, currency);

        return `<tr data-atom>
          ${td(esc(name))}
          ${td(String(line.quantity ?? 1), 'center')}
          ${td(unit, 'end')}
          ${td(`<strong>${money(line.line_total, currency)}</strong>`, 'end')}
          ${withMargin ? td(money(line.cost_price, currency), 'end') : ''}
          ${withMargin ? td(money(line.profit_price, currency), 'end') : ''}
        </tr>`;
    }).join('');

    if (!rows) {
        return `<p style="font-size:12px;color:${MUTED}">${esc(t.order?.no_products || 'This order has no lines.')}</p>`;
    }

    return `<table style="width:100%;border-collapse:collapse">
      <thead><tr>${head.map((label, i) => th(label, i === 0 ? 'start' : (i === 1 ? 'center' : 'end'))).join('')}</tr></thead>
      <tbody>${rows}</tbody>
    </table>`;
};

const totals = (order, t, currency, { withMargin }) => {
    const before = Number(order.total_amount_before_discount);
    const after = Number(order.total_amount);
    const saved = Number.isFinite(before) && Number.isFinite(after)
        ? Math.round((before - after) * 100) / 100
        : 0;
    const paid = Number(order.total_paid) || 0;
    const owed = Math.round(((Number(order.total_amount) || 0) - paid) * 100) / 100;

    const row = (label, value, style = '') =>
        `<tr data-atom><td style="padding:4px 0;font-size:12px;color:${MUTED}">${esc(label)}</td>
         <td style="padding:4px 0;font-size:12px;text-align:end;color:${INK};${style}">${value}</td></tr>`;

    return `<table style="width:100%;max-width:340px;margin-inline-start:auto;border-collapse:collapse">
      ${Number.isFinite(before) ? row(t.order?.total_amount_before_discount || 'Before discount', `<span style="text-decoration:line-through;color:${MUTED}">${money(before, currency)}</span>`) : ''}
      ${saved > 0 ? row(t.order?.membership_discount || 'Membership discount', `<strong style="color:${ACCENT}">− ${money(saved, currency)}</strong>`) : ''}
      ${row(t.order?.delivery_price || 'Delivery price', money(order.delivery_price, currency))}
      ${withMargin ? row(t.order?.delivery_cost || 'Delivery cost', money(order.delivery_cost, currency)) : ''}
      ${withMargin ? row(t.order?.delivery_profit || 'Delivery profit', money(order.delivery_profit, currency)) : ''}
      <tr data-atom>
        <td style="padding:8px 0 4px;font-size:14px;font-weight:700;color:${INK};border-top:2px solid ${INK}">${esc(t.order?.total_amount || 'Total Amount')}</td>
        <td style="padding:8px 0 4px;font-size:14px;font-weight:700;text-align:end;color:${INK};border-top:2px solid ${INK}">${money(order.total_amount, currency)}</td>
      </tr>
      ${row(t.order?.total_paid || 'Total Paid', `<strong>${money(paid, currency)}</strong>`)}
      ${Math.abs(owed) >= 0.005 ? row(
        owed > 0 ? (t.order?.outstanding || 'Outstanding') : (t.order?.overpaid_label || 'Overpaid'),
        `<strong style="color:${owed > 0 ? '#b45309' : '#0369a1'}">${money(Math.abs(owed), currency)}</strong>`,
    ) : ''}
    </table>`;
};

const header = (order, t, { appName, logoUrl, title, subtitle }) => `
  <div data-atom style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;border-bottom:2px solid ${INK};padding-bottom:12px;margin-bottom:18px">
    <div>
      ${logoUrl ? `<img src="${esc(logoUrl)}" alt="" style="height:38px;width:auto;display:block;margin-bottom:8px" onerror="this.style.display='none'" />` : ''}
      <div style="font-size:17px;font-weight:800;color:${INK}">${esc(appName || '')}</div>
      <div style="font-size:13px;font-weight:600;color:${ACCENT};margin-top:2px">${esc(title)}</div>
      ${subtitle ? `<div style="font-size:11px;color:${MUTED};margin-top:2px">${esc(subtitle)}</div>` : ''}
    </div>
    <div style="text-align:end">
      <div style="font-size:11px;color:${MUTED}">${esc(t.order?.order_code || 'Order Code')}</div>
      <div style="font-size:16px;font-weight:800;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:${INK};direction:ltr">${esc(order.order_code)}</div>
      <div style="font-size:11px;color:${MUTED};margin-top:6px;direction:ltr">${esc(order.created_at || '')}</div>
    </div>
  </div>`;

/* ------------------------------------------------------------------ *
 * The two documents.
 * ------------------------------------------------------------------ */

const adminDocument = (order, t, locale, currency, meta) => `
  ${header(order, t, { ...meta, title: t.order?.pdf_admin_title || 'Order — full details', subtitle: t.order?.pdf_internal_note || 'Internal copy. Contains cost and margin — not for the customer.' })}

  ${section(t.order?.status_section || 'Status & Payment', pairs([
      [t.order?.payment_status || 'Payment Status', dash(order.payment_status?.label)],
      [t.order?.delivery_status || 'Delivery Status', dash(order.delivery_status?.label)],
      [t.order?.order_status || 'Order Status', dash(order.order_status?.label)],
      [t.order?.payment_type || 'Payment Type', dash(order.payment_type?.label)],
      [t.order?.source || 'Source', dash(order.source)],
      order.cancel_reason ? [t.order?.cancel_reason || 'Cancel Reason', dash(order.cancel_reason)] : null,
  ]))}

  ${section(t.order?.customer || 'Customer', pairs([
      [t.order?.customer_name || 'Full Name', dash(order.customer_full_name)],
      [t.order?.customer_phone || 'Phone', `<span style="direction:ltr;display:inline-block">${dash(order.customer_phone)}</span>`],
      [t.order?.membership_number || 'Membership No.', `<span style="direction:ltr;display:inline-block">${dash(order.membership_number)}</span>`],
      [t.order?.address_type || 'Address Type', dash(order.customer_address_type?.label)],
      [t.order?.customer_address || 'Address', dash(order.customer_address)],
      [t.order?.address_details || 'Address Details', dash(addressLine(order))],
      [t.order?.customer_special_mark || 'Landmark', dash(order.customer_special_mark)],
      [t.order?.notes || 'Notes', dash(order.notes)],
  ]))}

  ${section(t.order?.products || 'Products', linesTable(order, t, locale, currency, { withMargin: true }))}

  ${section(t.order?.amounts || 'Amounts', totals(order, t, currency, { withMargin: true }))}

  ${section(t.order?.origin || 'Origin', pairs([
      [t.order?.visitor_ip || 'Visitor IP', `<span style="direction:ltr;display:inline-block">${dash(order.ip_address)}</span>`],
      [t.order?.user_agent || 'User agent', `<span style="direction:ltr;display:inline-block;word-break:break-all;font-size:10px">${dash(order.user_agent)}</span>`],
      [t.order?.created_at || 'Created At', `<span style="direction:ltr;display:inline-block">${dash(order.created_at)}</span>`],
      [t.order?.updated_at || 'Updated at', `<span style="direction:ltr;display:inline-block">${dash(order.updated_at)}</span>`],
      [t.order?.receipts || 'Receipts', String((order.receipts || []).length)],
  ]))}

  ${(order.logs || []).length ? section(t.order?.activity_log || 'Activity Log', `
    <table style="width:100%;border-collapse:collapse">
      ${order.logs.map(log => `
        <tr data-atom>
          <td style="padding:4px 8px 4px 0;font-size:11px;color:${MUTED};white-space:nowrap;direction:ltr">${esc(log.created_at || '')}</td>
          <td style="padding:4px 8px;font-size:11px;color:${INK};font-weight:600">${esc(log.action || '')}</td>
          <td style="padding:4px 0;font-size:11px;color:${MUTED}">${esc(log.admin?.name || '—')}</td>
        </tr>`).join('')}
    </table>`) : ''}
`;

const receiptDocument = (order, t, locale, currency, meta) => `
  ${header(order, t, { ...meta, title: t.order?.pdf_receipt_title || 'Receipt', subtitle: '' })}

  ${section(t.order?.customer || 'Customer', pairs([
      [t.order?.customer_name || 'Full Name', dash(order.customer_full_name)],
      [t.order?.customer_phone || 'Phone', `<span style="direction:ltr;display:inline-block">${dash(order.customer_phone)}</span>`],
      order.membership_number ? [t.order?.membership_number || 'Membership No.', `<span style="direction:ltr;display:inline-block">${dash(order.membership_number)}</span>`] : null,
      [t.order?.address_details || 'Address Details', dash(addressLine(order) || order.customer_address)],
  ]))}

  ${section(t.order?.products || 'Products', linesTable(order, t, locale, currency, { withMargin: false }))}

  ${section(t.order?.amounts || 'Amounts', totals(order, t, currency, { withMargin: false }))}

  ${section(t.order?.payment_status || 'Payment', pairs([
      [t.order?.payment_type || 'Payment Type', dash(order.payment_type?.label)],
      [t.order?.payment_status || 'Payment Status', dash(order.payment_status?.label)],
      [t.order?.delivery_status || 'Delivery Status', dash(order.delivery_status?.label)],
  ]))}

  <div data-atom style="margin-top:26px;padding-top:12px;border-top:1px solid ${LINE};text-align:center">
    <div style="font-size:12px;font-weight:600;color:${INK}">${esc(t.order?.pdf_thanks || 'Thank you for your order.')}</div>
    <div style="font-size:10px;color:${MUTED};margin-top:4px">${esc((t.order?.pdf_receipt_footer || 'Keep this receipt — :code is how you track this order.').replace(':code', order.order_code))}</div>
  </div>
`;

/* ------------------------------------------------------------------ *
 * Rasterise and paginate.
 * ------------------------------------------------------------------ */

/**
 * Where to break the rendered node into pages.
 *
 * A page cut that lands inside a table row leaves half a product name at the
 * bottom of one page and the other half at the top of the next. Blocks that
 * must survive intact are marked `data-atom`, and a cut that would split one
 * is pulled back to that block's top instead — the ordinary widow/orphan rule,
 * done by hand because a rasterised page has no idea what a row is.
 */
const cutPoints = (container) => {
    const containerTop = container.getBoundingClientRect().top;
    const atoms = Array.from(container.querySelectorAll('[data-atom]')).map((el) => {
        const box = el.getBoundingClientRect();
        return { top: box.top - containerTop, bottom: box.bottom - containerTop };
    });

    const total = container.scrollHeight;
    const cuts = [0];
    let y = 0;

    while (total - y > PAGE_H_PX) {
        const limit = y + PAGE_H_PX;
        const split = atoms.find(atom => atom.top > y && atom.top < limit && atom.bottom > limit);
        /* A block taller than a whole page has to be split somewhere; the
           straight cut is the only option left. */
        const cut = split && split.top > y ? split.top : limit;
        cuts.push(cut);
        y = cut;
    }

    cuts.push(total);
    return cuts;
};

/**
 * Lay the printable node out inside a blank, same-origin iframe and hand it to
 * `render`.
 *
 * It cannot be laid out in this page. html2canvas reads computed styles, and
 * before it touches the node it is pointed at it reads `html`/`body`'s
 * background colour — the app's theme is built in `oklch()`, which
 * html2canvas 1.4.1 cannot parse ("Attempting to parse an unsupported color
 * function \"oklch\""). Styling the node inline does not help: the colours it
 * chokes on are inherited from the page's own stylesheet, on the document
 * around it. So the document it is rasterised from is one with no stylesheet
 * at all — an empty iframe, where the only styles are ours and the browser's.
 *
 * Off-screen rather than hidden: html2canvas measures what it is given, and
 * `display:none` (or `visibility:hidden`) measures as nothing.
 */
const withPrintableFrame = async (html, { rtl }, render) => {
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.setAttribute('tabindex', '-1');
    /* `srcdoc` rather than `document.write`: it is same-origin with this page,
       so the node stays reachable, and its `load` says when the document is
       really there to write into. */
    frame.srcdoc = `<!doctype html><html dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"></head><body style="margin:0;background:#ffffff"></body></html>`;
    frame.style.cssText = [
        'position:fixed',
        'top:0',
        'left:-10000px',
        `width:${PAGE_W_PX}px`,
        `height:${PAGE_H_PX}px`,
        'border:0',
        'background:#ffffff',
        'z-index:-1',
    ].join(';');
    document.body.appendChild(frame);

    try {
        await new Promise((resolve, reject) => {
            frame.addEventListener('load', resolve, { once: true });
            frame.addEventListener('error', () => reject(new Error('The printable frame failed to load.')), { once: true });
        });

        const doc = frame.contentDocument;
        if (!doc || !doc.body) throw new Error('The printable frame has no document.');

        const host = doc.createElement('div');
        host.setAttribute('dir', rtl ? 'rtl' : 'ltr');
        host.style.cssText = [
            `width:${PAGE_W_PX}px`,
            'padding:38px 40px',
            'box-sizing:border-box',
            'background:#ffffff',
            `color:${INK}`,
            'font-family:"Segoe UI",Tahoma,Arial,"Helvetica Neue",sans-serif',
            'font-size:12px',
            'line-height:1.5',
        ].join(';');
        host.innerHTML = html;
        doc.body.appendChild(host);

        /* An image still loading measures as nothing, and the page cuts are
           taken from measurements. A broken one resolves too — a receipt with
           no logo still pays. */
        await Promise.all(Array.from(host.querySelectorAll('img')).map(img => (
            img.complete ? Promise.resolve() : new Promise((resolve) => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            })
        )));

        /* Give the frame a viewport as tall as what it holds, so nothing is
           laid out below the fold of a window html2canvas would clone. */
        frame.style.height = `${Math.max(PAGE_H_PX, Math.ceil(doc.documentElement.scrollHeight))}px`;

        return await render(host);
    } finally {
        frame.remove();
    }
};


/**
 * Rasterise the printable node and paginate it into a jsPDF document.
 *
 * It stops at the document rather than saving it: the same pages are what the
 * preview shows and what the download writes, so neither can drift from the
 * other — the preview IS the file, not a second rendering of it.
 */
const buildPdf = async (html, { rtl }) => {
    const [{ jsPDF }, { default: html2canvas }] = await Promise.all([
        import('jspdf'),
        import('html2canvas'),
    ]);

    return withPrintableFrame(html, { rtl }, async (host) => {
        const scale = 2;
        const source = await html2canvas(host, {
            scale,
            backgroundColor: '#ffffff',
            /* A logo served from another host loads only with CORS, and
               without it html2canvas skips the image rather than failing —
               which is the right trade: a receipt with no logo still pays. */
            useCORS: true,
            logging: false,
        });

        const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'portrait' });
        const ptPerPx = PAGE_W_PT / PAGE_W_PX;
        const cuts = cutPoints(host);

        for (let page = 0; page < cuts.length - 1; page++) {
            const fromPx = cuts[page];
            const heightPx = cuts[page + 1] - fromPx;
            if (heightPx <= 0) continue;

            const slice = document.createElement('canvas');
            slice.width = source.width;
            slice.height = Math.round(heightPx * scale);
            const ctx = slice.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, slice.width, slice.height);
            ctx.drawImage(source, 0, Math.round(fromPx * scale), source.width, slice.height, 0, 0, slice.width, slice.height);

            if (page > 0) doc.addPage();
            doc.addImage(
                slice.toDataURL('image/jpeg', 0.92),
                'JPEG',
                0,
                0,
                PAGE_W_PT,
                heightPx * ptPerPx,
                undefined,
                'FAST',
            );
        }

        return doc;
    });
};

const safeCode = (order) => String(order?.order_code || 'order').replace(/[^A-Za-z0-9_-]+/g, '_');

/**
 * The two documents, named by the variant the page asks for.
 *
 * `admin` is the internal copy: everything the shop knows about the order.
 * `receipt` is the customer's: no cost, no margin, no provenance, no audit
 * trail. They stay two entries here rather than one entry with a flag, for
 * the reason at the top of this file.
 */
const DOCUMENTS = {
    admin: {
        build: adminDocument,
        filename: order => `order-${safeCode(order)}-full.pdf`,
    },
    receipt: {
        build: receiptDocument,
        filename: order => `receipt-${safeCode(order)}.pdf`,
    },
};

const render = async (variant, { order, t = {}, locale = 'ar', appName = '', logoUrl = null, currency = 'EGP' }) => {
    const spec = DOCUMENTS[variant];
    if (!spec) throw new Error(`Unknown order document "${variant}".`);

    const doc = await buildPdf(spec.build(order, t, locale, currency, { appName, logoUrl }), {
        rtl: locale === 'ar',
    });

    return { doc, filename: spec.filename(order) };
};

/** Build the document and hand it straight to the browser's downloader. */
export const exportOrderPdf = async (variant, options) => {
    const { doc, filename } = await render(variant, options);
    doc.save(filename);
};

/**
 * Build the document and hand back a blob URL for it, for showing in the
 * browser's own PDF viewer before anything is written to disk.
 *
 * The URL owns memory until it is revoked, which is the caller's job — hence
 * `revoke()` alongside it rather than a bare string.
 */
export const previewOrderPdf = async (variant, options) => {
    const { doc, filename } = await render(variant, options);
    const url = URL.createObjectURL(doc.output('blob'));

    return { url, filename, revoke: () => URL.revokeObjectURL(url) };
};
