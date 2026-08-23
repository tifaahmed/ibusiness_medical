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
    },
    delivery: {
        pending: 'border-amber-500/30 bg-amber-500/10 text-amber-500',
        processing: 'border-blue-500/30 bg-blue-500/10 text-blue-500',
        'on-delivery': 'border-violet-500/30 bg-violet-500/10 text-violet-500',
        completed: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-500',
    },
};

export const paymentTypeClass = {
    cod: 'border-sky-500/30 bg-sky-500/10 text-sky-500',
    'transfer-wallet': 'border-violet-500/30 bg-violet-500/10 text-violet-500',
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
    products_changed: 'border-sky-500/30 bg-sky-500/10 text-sky-500',
    canceled: 'border-zinc-500/30 bg-zinc-500/10 text-zinc-400',
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
}, status, typeof status === 'object' ? status?.label : null);

export const deliveryStatusLabel = (t, status) => labelled(t, {
    pending: 'delivery_pending',
    processing: 'delivery_processing',
    'on-delivery': 'delivery_on_delivery',
    completed: 'delivery_completed',
}, status, typeof status === 'object' ? status?.label : null);

export const paymentTypeLabel = (t, type) => labelled(t, {
    cod: 'type_cod',
    'transfer-wallet': 'type_transfer_wallet',
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
