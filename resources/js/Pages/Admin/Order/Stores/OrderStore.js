import { defineStore } from 'pinia';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

/**
 * A number the form can hold without fighting the user.
 *
 * Inputs hand back strings, and a half-typed "12." is a legitimate state to be
 * in — so the form keeps whatever was typed and only the totals it derives are
 * coerced. An empty field stays empty rather than becoming 0: "not set" and
 * "zero" are different answers for a price.
 */
const num = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const emptyForm = () => ({
    customer_full_name: '',
    customer_phone: '',
    customer_address: '',
    customer_address_type: '',
    customer_street: '',
    customer_governorate: '',
    customer_city: '',
    customer_building_number: '',
    customer_apartment_number: '',
    customer_floor_number: '',
    customer_special_mark: '',
    notes: '',
    membership_number: '',
    payment_status: '',
    delivery_status: '',
    payment_type: '',
    cancel_reason: '',
    total_paid: 0,
    total_amount: 0,
    total_amount_before_discount: '',
    source: '',
    products: [],
});

/**
 * A name as a translation map, whatever shape the server sent.
 *
 * Lines archived before the name became translatable come back as a plain
 * string; it is written to both languages rather than dropped, so editing the
 * line starts from the name it was actually sold under.
 */
const nameObject = (name) => {
    if (typeof name === 'string') {
        try {
            const parsed = JSON.parse(name);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed;
        } catch {
            // Not json — one name in both languages.
        }
        return name ? { ar: name, en: name } : {};
    }
    if (!name || typeof name !== 'object' || Array.isArray(name)) return {};
    return { ...name };
};

const lineFromOrder = (line) => ({
    id: line.id ?? null,
    product_id: line.product_id ?? null,
    name: nameObject(line.name),
    slug: line.slug ?? '',
    image: line.image ?? '',
    quantity: line.quantity ?? 1,
    old_price: line.old_price ?? '',
    new_price: line.new_price ?? '',
    cost_price: line.cost_price ?? '',
    profit_price: line.profit_price ?? '',
});

export const useOrderStore = defineStore('order', {
    state: () => ({
        form: useForm(emptyForm()),
        validationErrors: null,
        isLoading: false,
        orderCode: null,
        /* Receipts ride along with the save rather than going to their own
           endpoint: files the admin picked but not yet saved, and receipts
           already on the order that the next save should delete. */
        newReceipts: [],
        removedReceiptIds: [],
        orderReceipts: [],
    }),

    getters: {
        /** Receipts already stored on the order as it was last loaded. */
        existingReceiptCount: (state) => state.orderReceipts.length,
    },

    getters: {
        /**
         * What the lines add up to right now — the figure the amounts card
         * offers to copy into `total_amount`. Derived, never posted: the server
         * recomputes every `line_total` from quantity and price anyway.
         */
        linesTotal: (state) => state.form.products.reduce((sum, line) => {
            const quantity = num(line.quantity) ?? 0;
            const price = num(line.new_price) ?? 0;
            return sum + Math.round(price * quantity * 100) / 100;
        }, 0),
    },

    actions: {
        setOrder(order) {
            this.orderCode = order.order_code;
            this.form = useForm({
                customer_full_name: order.customer_full_name ?? '',
                customer_phone: order.customer_phone ?? '',
                customer_address: order.customer_address ?? '',
                customer_address_type: order.customer_address_type ?? '',
                customer_street: order.customer_street ?? '',
                customer_governorate: order.customer_governorate ?? '',
                customer_city: order.customer_city ?? '',
                customer_building_number: order.customer_building_number ?? '',
                customer_apartment_number: order.customer_apartment_number ?? '',
                customer_floor_number: order.customer_floor_number ?? '',
                customer_special_mark: order.customer_special_mark ?? '',
                notes: order.notes ?? '',
                membership_number: order.membership_number ?? '',
                payment_status: order.payment_status ?? '',
                delivery_status: order.delivery_status ?? '',
                payment_type: order.payment_type ?? '',
                cancel_reason: order.cancel_reason ?? '',
                total_paid: order.total_paid ?? 0,
                total_amount: order.total_amount ?? 0,
                total_amount_before_discount: order.total_amount_before_discount ?? '',
                source: order.source ?? '',
                products: (order.products || []).map(lineFromOrder),
            });
            this.validationErrors = null;
            this.newReceipts = [];
            this.removedReceiptIds = [];
            this.orderReceipts = order.receipts || [];
        },

        /**
         * Queue files picked for upload. The cap is the order's, not the
         * picker's: existing receipts minus the ones marked for removal still
         * take up room.
         */
        queueReceipts(fileList, maxReceipts) {
            const incoming = Array.from(fileList || []);
            if (!incoming.length) return 0;

            const slots = maxReceipts
                - (this.existingReceiptCount - this.removedReceiptIds.length)
                - this.newReceipts.length;

            if (slots <= 0) return -1;

            const accepted = incoming.slice(0, slots);
            this.newReceipts.push(...accepted);
            return accepted.length === incoming.length ? accepted.length : -accepted.length;
        },

        unqueueReceipt(index) {
            this.newReceipts.splice(index, 1);
        },

        /** Mark a receipt already on the order for deletion on save. */
        markReceiptRemoved(mediaId) {
            if (!this.removedReceiptIds.includes(mediaId)) {
                this.removedReceiptIds.push(mediaId);
            }
        },

        undoRemoveReceipt(mediaId) {
            this.removedReceiptIds = this.removedReceiptIds.filter(id => id !== mediaId);
        },

        /**
         * Seed a line from the catalogue at today's price.
         *
         * `id` stays null so the server files it as a new archived line rather
         * than rewriting one that was really sold. Every figure is editable
         * afterwards — a line added by hand is usually a correction, not a sale.
         */
        addLineFromProduct(product) {
            if (!product) return;
            this.form.products.push({
                id: null,
                product_id: product.id ?? null,
                name: nameObject(product.name),
                slug: product.slug ?? '',
                image: product.image ?? '',
                quantity: 1,
                old_price: product.old_price ?? '',
                new_price: product.new_price ?? product.old_price ?? '',
                cost_price: product.cost_price ?? '',
                profit_price: product.profit_price ?? '',
            });
        },

        addBlankLine() {
            this.form.products.push({
                id: null,
                product_id: null,
                name: {},
                slug: '',
                image: '',
                quantity: 1,
                old_price: '',
                new_price: '',
                cost_price: '',
                profit_price: '',
            });
        },

        removeLine(index) {
            this.form.products.splice(index, 1);
        },

        /** Copy what the lines add up to into the amount charged. */
        applyLinesTotal() {
            this.form.total_amount = Math.round(this.linesTotal * 100) / 100;
        },

        updateOrder() {
            if (!this.orderCode) return;

            this.isLoading = true;
            this.validationErrors = null;

            this.form
                .transform((data) => ({
                    ...data,
                    total_paid: num(data.total_paid) ?? 0,
                    total_amount: num(data.total_amount) ?? 0,
                    total_amount_before_discount: num(data.total_amount_before_discount),
                    products: data.products.map((line) => ({
                        ...line,
                        quantity: num(line.quantity) ?? 1,
                        old_price: num(line.old_price),
                        new_price: num(line.new_price),
                        cost_price: num(line.cost_price),
                        profit_price: num(line.profit_price),
                    })),
                    /* Sent only when touched, so an edit that never went near
                       the receipts cannot be read by the server as "receipts
                       changed" — the same sometimes-array contract as lines. */
                    ...(this.newReceipts.length ? { receipts: this.newReceipts } : {}),
                    ...(this.removedReceiptIds.length ? { remove_receipt_ids: [...this.removedReceiptIds] } : {}),
                }))
                .put(route('admin.order.update', this.orderCode), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Order updated successfully');
                        this.newReceipts = [];
                        this.removedReceiptIds = [];
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...errors };
                        useNotification().error('Failed to update order');
                        // AGENTS.md: front-end failures are reported, not swallowed.
                        reportClientError('Order update rejected', errors, this.orderCode);
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
        },
    },
});

/**
 * Ship a form failure to the client-error endpoint so a validation rule nobody
 * can satisfy shows up in `/admin/client-error-logs` instead of only in the
 * admin's face. Best effort — a failed report must never mask the failure it
 * is reporting.
 */
function reportClientError(message, errors, orderCode) {
    try {
        fetch('/api/v1/client-errors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                message,
                route: window.location.pathname,
                fatal: false,
                extra: { order_code: orderCode, errors },
            }),
        }).catch(() => {});
    } catch {
        // Reporting is never worth a second error.
    }
}
