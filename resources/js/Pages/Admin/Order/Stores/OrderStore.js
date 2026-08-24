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
    order_status: '',
    payment_type: '',
    cancel_reason: '',
    total_paid: 0,
    total_amount: 0,
    total_amount_before_discount: '',
    /* Delivery as it was archived on this order. The profit is not here: it is
       `price - cost`, shown as it is typed and worked out again server-side. */
    delivery_cost: 0,
    delivery_price: 0,
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
           endpoint: the files the admin has picked but not yet saved, and the
           ones already on the order. There is no removal list — the collection
           is append-only on this side exactly as it is on the buyer's. */
        newReceipts: [],
        orderReceipts: [],
    }),

    getters: {
        /** Receipts already stored on the order as it was last loaded. */
        existingReceiptCount: (state) => state.orderReceipts.length,

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

        /**
         * What this order makes on the delivery, as the two inputs stand.
         *
         * Shown, never posted: the server stores `price - cost` whatever a form
         * sends, so the figure here is the same arithmetic done early enough
         * for an admin to see what they are about to save.
         */
        deliveryProfit: (state) => Math.round(
            ((num(state.form.delivery_price) ?? 0) - (num(state.form.delivery_cost) ?? 0)) * 100,
        ) / 100,

        /**
         * What is still owed on the order as the two amount fields stand.
         *
         * Positive is owed by the customer, negative is overpaid — both are
         * states an admin needs to see, so this is not clamped at zero.
         */
        outstanding: (state) => Math.round(
            ((num(state.form.total_amount) ?? 0) - (num(state.form.total_paid) ?? 0)) * 100,
        ) / 100,

        /**
         * What the membership took off this order: the gap between the price
         * before the discount and the price charged.
         *
         * The same arithmetic as `savedAmount()` on the read pages, but over
         * the FORM rather than the saved order, so the badge answers for the
         * figures about to be saved. Null when there is nothing to compare —
         * an order with no before-discount figure is not a discount of zero,
         * it is an order that was never priced against one.
         */
        membershipDiscount: (state) => {
            const before = num(state.form.total_amount_before_discount);
            const after = num(state.form.total_amount);
            if (before === null || after === null) return null;
            return Math.round((before - after) * 100) / 100;
        },
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
                order_status: order.order_status ?? 'pending',
                payment_type: order.payment_type ?? '',
                cancel_reason: order.cancel_reason ?? '',
                total_paid: order.total_paid ?? 0,
                total_amount: order.total_amount ?? 0,
                total_amount_before_discount: order.total_amount_before_discount ?? '',
                delivery_cost: order.delivery_cost ?? 0,
                delivery_price: order.delivery_price ?? 0,
                source: order.source ?? '',
                products: (order.products || []).map(lineFromOrder),
            });
            this.validationErrors = null;
            this.newReceipts = [];
            this.orderReceipts = order.receipts || [];
        },

        /**
         * Queue files picked for upload.
         *
         * Uncapped: an order takes as much evidence as the transfer needed.
         * The picker stays open however many the order already holds.
         */
        queueReceipts(fileList) {
            const incoming = Array.from(fileList || []);
            if (!incoming.length) return 0;

            this.newReceipts.push(...incoming);
            return incoming.length;
        },

        /**
         * Drop a file from the queue before it is saved.
         *
         * This is not a removal: nothing has been stored yet. Once a receipt
         * IS on the order it stays — see `Order::RECEIPT_COLLECTION`.
         */
        unqueueReceipt(index) {
            this.newReceipts.splice(index, 1);
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

        /**
         * Copy what the lines add up to into the amount charged.
         *
         * Delivery goes in with them: it is charged on top of the lines, so a
         * "use lines total" that left it out would quietly refund it.
         */
        applyLinesTotal() {
            const delivery = num(this.form.delivery_price) ?? 0;

            this.form.total_amount = Math.round((this.linesTotal + delivery) * 100) / 100;
        },

        /**
         * Settle the order: the customer paid all of what they were charged.
         *
         * Copies the amount charged rather than adding to what is already
         * there — "paid in full" is a statement about the total, and an admin
         * clicking it twice must not bill the customer twice. It does not
         * touch the payment status: whether the money actually arrived is a
         * decision with an audit row behind it, not a side effect of a button.
         */
        applyFullPayment() {
            this.form.total_paid = num(this.form.total_amount) ?? 0;
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
                    delivery_cost: num(data.delivery_cost) ?? 0,
                    delivery_price: num(data.delivery_price) ?? 0,
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
                }))
                .put(route('admin.order.update', this.orderCode), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Order updated successfully');
                        this.newReceipts = [];
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
