/**
 * How an order renders: badge palettes, status wording and money formatting.
 *
 * Kept in one place because the list, the order page and the edit form all
 * have to agree — a "pending" that is amber in the table and grey on the order
 * reads as two different states to the person looking at both.
 */

/** Badge palettes per AGENTS.md: a matching color for every state. */
export const statusClass = {
    payment: {
        pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
        accepted: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
        rejected: 'border-red-500/30 bg-red-500/10 text-red-500',
        canceled: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400',
        refunded: 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-500',
    },
    delivery: {
        pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
        processing: 'border-blue-500/30 bg-blue-500/10 text-blue-500',
        'on-delivery': 'border-violet-500/30 bg-violet-500/10 text-violet-500',
        completed: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    },
    /* The order's own outcome. Success and failure read the same green/red as
       an accepted and a rejected payment on purpose — an admin scanning the
       table is reading colour first and words second. */
    order: {
        pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
        success: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
        failed: 'border-red-500/30 bg-red-500/10 text-red-500',
    },
};

export const paymentTypeClass = {
    cod: 'border-sky-500/30 bg-sky-500/10 text-sky-500',
    'transfer-wallet': 'border-violet-500/30 bg-violet-500/10 text-violet-500',
    paymob: 'border-indigo-500/30 bg-indigo-500/10 text-indigo-500',
};

/**
 * The colour an audit action is drawn in. Reads matter less than writes, so
 * views and edit-form opens stay muted and the changes carry the colour.
 */
export const logActionClass = {
    created: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    updated: 'border-blue-500/30 bg-blue-500/10 text-blue-500',
    deleted: 'border-red-500/30 bg-red-500/10 text-red-500',
    payment_status_changed: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
    delivery_status_changed: 'border-violet-500/30 bg-violet-500/10 text-violet-500',
    order_status_changed: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    products_changed: 'border-sky-500/30 bg-sky-500/10 text-sky-500',
    canceled: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400',
    payment_initiated: 'border-blue-500/30 bg-blue-500/10 text-blue-500',
    payment_callback: 'border-violet-500/30 bg-violet-500/10 text-violet-500',
    payment_checked: 'border-sky-500/30 bg-sky-500/10 text-sky-500',
    payment_accepted: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    payment_rejected: 'border-red-500/30 bg-red-500/10 text-red-500',
    payment_refunded: 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-500',
    refund_failed: 'border-red-500/30 bg-red-500/10 text-red-500',
    receipt_emailed: 'border-teal-500/30 bg-teal-500/10 text-teal-500',
    viewed: 'border-border bg-muted/50 text-muted-foreground',
    edit_viewed: 'border-border bg-muted/50 text-muted-foreground',
};

/**
 * A status label, preferring the admin's own translations and falling back to
 * whatever the API labelled it — a status added to the enum before its
 * translation lands still has to read as something.
 *
 * `status` accepts either the `{value,label}` pair the read endpoints send or a
 * bare value string, which is what the edit form binds to.
 */
const labelled = (t, keys, status, fallbackLabel) => {
    const value = typeof status === 'string' ? status : status?.value;
    if (!value) return '—';
    return t?.order?.[keys[value]] || fallbackLabel || (typeof status === 'object' ? status?.label : null) || value;
};

export const paymentStatusLabel = (t, status) => labelled(t, {
    pending: 'status_pending',
    accepted: 'status_accepted',
    rejected: 'status_rejected',
    canceled: 'status_canceled',
    refunded: 'status_refunded',
}, status, typeof status === 'object' ? status?.label : null);

export const deliveryStatusLabel = (t, status) => labelled(t, {
    pending: 'delivery_pending',
    processing: 'delivery_processing',
    'on-delivery': 'delivery_on_delivery',
    completed: 'delivery_completed',
}, status, typeof status === 'object' ? status?.label : null);

export const orderStatusLabel = (t, status) => labelled(t, {
    pending: 'order_status_pending',
    success: 'order_status_success',
    failed: 'order_status_failed',
}, status, typeof status === 'object' ? status?.label : null);

export const paymentTypeLabel = (t, type) => labelled(t, {
    cod: 'type_cod',
    'transfer-wallet': 'type_transfer_wallet',
    paymob: 'type_paymob',
}, type, typeof type === 'object' ? type?.label : null);

export const logActionLabel = (t, action) => t?.order?.[`log_${action}`] || action?.replace(/_/g, ' ') || '';

export const formatPrice = (price) => {
    if (price === null || price === undefined || price === '') return '—';
    const parsed = Number(price);
    if (!Number.isFinite(parsed)) return '—';
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parsed);
};

/**
 * A translated name out of a translation map, a plain string, or a json string
 * that was never decoded — archived lines have been written all three ways.
 */
export const translatedName = (name, locale = 'ar') => {
    if (typeof name === 'string') {
        try {
            const parsed = JSON.parse(name);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                return parsed[locale] || parsed.ar || parsed.en || Object.values(parsed)[0] || '';
            }
        } catch {
            // A plain name, not json.
        }
        return name;
    }
    if (name && typeof name === 'object') {
        return name[locale] || name.ar || name.en || Object.values(name)[0] || '';
    }
    return '';
};

/** What the membership saved the customer: before-discount minus payable. */
export const savedAmount = (order) => {
    const before = Number(order?.total_amount_before_discount);
    const after = Number(order?.total_amount);
    if (!Number.isFinite(before) || !Number.isFinite(after)) return null;
    const diff = Math.round((before - after) * 100) / 100;
    return diff > 0 ? diff : null;
};

/** `Y-m-d H:i:s` (the server's clock) as a Date, or null when it is not one. */
const parseStamp = (stamp) => {
    if (!stamp) return null;
    const date = new Date(String(stamp).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
};

/** `2026-10-19 03:45:12 PM` — a 12-hour clock with AM/PM, seconds kept for gateway timing. */
export const formatDateTime = (stamp) => {
    const date = parseStamp(stamp);
    if (!date) return stamp || '—';
    const day = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const time = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    return `${day} ${time}`;
};

/** "5 minutes ago" / "منذ 5 دقائق" — the difference from now, for humans. */
export const timeAgo = (stamp, locale = 'en') => {
    const date = parseStamp(stamp);
    if (!date) return '';
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
    const [unit, size] = units.find(([, size]) => Math.abs(seconds) >= size) || units[units.length - 1];
    return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(Math.round(seconds / size), unit);
};
