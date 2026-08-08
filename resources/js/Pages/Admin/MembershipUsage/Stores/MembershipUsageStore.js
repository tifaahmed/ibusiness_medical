import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateMembershipUsageForm } from '../validation/membershipUsageValidation';

const getT = () => usePage().props.translations?.admin?.membership_usage || {};

export const useMembershipUsageStore = defineStore('membershipUsage', {
    state: () => ({
        form: useForm({
            membership_id: '',
            facility_id: '',
            facility_branch_id: '',
            facility_type_id: '',
            amount: '',
            description: '',
            gallery: [],
            gallery_delete: [],
        }),
        validationErrors: null,
        usages: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                membership_id: '',
                facility_id: '',
                facility_branch_id: '',
                facility_type_id: '',
                amount: '',
                description: '',
                gallery: [],
                gallery_delete: [],
            });
            this.validationErrors = null;
        },

        setUsages(usages) {
            this.usages = usages;
        },

        setUsage(usage) {
            this.form = useForm({
                id: usage.id,
                membership_id: usage.membership_id || '',
                facility_id: usage.facility_id || '',
                facility_branch_id: usage.facility_branch_id || '',
                facility_type_id: usage.facility_type_id || '',
                amount: usage.amount || '',
                description: usage.description || '',
                gallery: [],
                gallery_delete: [],
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const validation = validateMembershipUsageForm({
                    membership_id: this.form.membership_id,
                    facility_id: this.form.facility_id,
                    facility_branch_id: this.form.facility_branch_id || null,
                    facility_type_id: this.form.facility_type_id,
                    amount: this.form.amount,
                    description: this.form.description || null,
                });

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error(getT().validation_error || 'Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.membership-usage.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().created || 'Membership usage created successfully');
                        this.initializeForm();
                        router.visit(route('admin.membership-usage.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error(getT().create_failed || 'Failed to create membership usage');
                    },
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateUsage() {
            this.isLoading = true;
            try {
                const validation = validateMembershipUsageForm({
                    membership_id: this.form.membership_id,
                    facility_id: this.form.facility_id,
                    facility_branch_id: this.form.facility_branch_id || null,
                    facility_type_id: this.form.facility_type_id,
                    amount: this.form.amount,
                    description: this.form.description || null,
                });

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error(getT().validation_error || 'Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.put(route('admin.membership-usage.update', this.form.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().updated || 'Membership usage updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.membership-usage.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error(getT().update_failed || 'Failed to update membership usage');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating usage:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteUsage(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.membership-usage.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().deleted || 'Membership usage deleted successfully');
                        router.reload({ only: ['usages'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error(getT().delete_failed || 'Failed to delete membership usage');
                    },
                });
            } catch (error) {
                console.error('Error deleting usage:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm(getT().confirm_delete || 'Are you sure you want to delete this membership usage? This action cannot be undone.')) {
                await this.deleteUsage(id);
            }
        },
    },
});
