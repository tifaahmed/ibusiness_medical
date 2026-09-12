import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useSalesStore = defineStore('sales', {
    state: () => ({
        form: useForm({
            name: { ar: '', en: '' },
            image: null,
        }),
        validationErrors: null,
        sales: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: { ar: '', en: '' },
                image: null,
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setSales(sales) {
            this.sales = sales;
        },

        normalizeTranslatable(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return { ar: '', en: '' };
            }
            return {
                ar: value.ar?.toString() || '',
                en: value.en?.toString() || '',
            };
        },

        setSale(sale) {
            this.form = useForm({
                id: sale.id,
                name: this.normalizeTranslatable(sale.name),
                image: null,
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.sales.store');
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Sales created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.sales.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create sales');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async updateSale() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.sales.update', this.form.id);
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Sales updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.sales.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update sales');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating sales:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteSale(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.sales.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Sales deleted successfully');
                        router.reload({ only: ['sales'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete sales');
                    },
                });
            } catch (error) {
                console.error('Error deleting sales:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this sales entry? This action cannot be undone.')) {
                await this.deleteSale(id);
            }
        },
    },
});
