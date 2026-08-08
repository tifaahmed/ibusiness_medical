import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateMemberPaymentForm } from '../validation/memberPaymentValidation';

const getT = () => usePage().props.translations?.admin?.member_payment || {};

export const useMemberPaymentStore = defineStore('memberPayment', {
    state: () => ({
        form: useForm({
            membership_id: '',
            amount: '',
            type: 'commission',
            months_paid: '',
            from_date: '',
            to_date: '',
            notes: '',
        }),
        validationErrors: null,
        payments: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                membership_id: '',
                amount: '',
                type: 'commission',
                months_paid: '',
                from_date: '',
                to_date: '',
                notes: '',
            });
            this.validationErrors = null;
        },

        setPayments(payments) {
            this.payments = payments;
        },

        setPayment(payment) {
            this.form = useForm({
                id: payment.id,
                membership_id: payment.membership_id || '',
                amount: payment.amount || '',
                type: payment.type || 'commission',
                months_paid: payment.months_paid || '',
                from_date: payment.from_date || '',
                to_date: payment.to_date || '',
                notes: payment.notes || '',
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const validation = validateMemberPaymentForm({
                    membership_id: this.form.membership_id,
                    amount: this.form.amount,
                    type: this.form.type,
                    months_paid: this.form.months_paid,
                    from_date: this.form.from_date,
                    to_date: this.form.to_date,
                    notes: this.form.notes || null,
                });

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error(getT().validation_error || 'Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.member-payment.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().created || 'Payment created successfully');
                        this.initializeForm();
                        router.visit(route('admin.member-payment.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error(getT().create_failed || 'Failed to create payment');
                    },
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updatePayment() {
            this.isLoading = true;
            try {
                const validation = validateMemberPaymentForm({
                    membership_id: this.form.membership_id,
                    amount: this.form.amount,
                    type: this.form.type,
                    months_paid: this.form.months_paid,
                    from_date: this.form.from_date,
                    to_date: this.form.to_date,
                    notes: this.form.notes || null,
                });

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error(getT().validation_error || 'Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.put(route('admin.member-payment.update', this.form.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().updated || 'Payment updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.member-payment.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error(getT().update_failed || 'Failed to update payment');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating payment:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deletePayment(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.member-payment.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().deleted || 'Payment deleted successfully');
                        router.reload({ only: ['payments'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error(getT().delete_failed || 'Failed to delete payment');
                    },
                });
            } catch (error) {
                console.error('Error deleting payment:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm(getT().confirm_delete || 'Are you sure you want to delete this payment? This action cannot be undone.')) {
                await this.deletePayment(id);
            }
        },
    },
});
