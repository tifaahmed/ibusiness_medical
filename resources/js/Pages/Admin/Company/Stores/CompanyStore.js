import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateCompanyForm } from '../validation/companyValidation';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useCompanyStore = defineStore('company', {
    state: () => ({
        form: useForm({ name: {} }),
        validationErrors: null,
        companies: reactive([]),
        isLoading: false,
        // What the last submit sent and what came back — the "Advanced Error
        // Track" tab behind the error badge next to the submit button.
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({ name: {} });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setCompanies(companies) {
            this.companies = companies;
        },

        setCompany(company) {
            let nameValue = company.name || {};
            if (typeof nameValue === 'string') {
                try { nameValue = JSON.parse(nameValue); } catch { nameValue = { ar: nameValue, en: nameValue }; }
            }
            this.form = useForm({ id: company.id, slug: company.slug || '', name: nameValue });
            this.validationErrors = null;
            this.debugLog = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const validation = validateCompanyForm({ name: this.form.name }, false);
                const url = route('admin.company.store');
                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    this.debugLog = buildDebugLog({
                        method: 'POST', url, fields: this.form.data(),
                        note: 'Not actually sent — blocked by client-side validation below.',
                        responseErrors: validation.errors,
                        responseNote: 'Client-side validation failure. The server was never reached.',
                    });
                    useNotification().error('Please fix the validation errors');
                    return;
                }
                this.validationErrors = null;
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });
                this.form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Company created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.company.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create company');
                    },
                });
            } catch (error) {
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateCompany() {
            this.isLoading = true;
            try {
                const validation = validateCompanyForm({ name: this.form.name }, true);
                const slug = this.form.slug || this.form.id;
                const url = route('admin.company.update', slug);
                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    this.debugLog = buildDebugLog({
                        method: 'PUT', url, fields: this.form.data(),
                        note: 'Not actually sent — blocked by client-side validation below.',
                        responseErrors: validation.errors,
                        responseNote: 'Client-side validation failure. The server was never reached.',
                    });
                    useNotification().error('Please fix the validation errors');
                    return;
                }
                this.validationErrors = null;
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });
                this.form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Company updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.company.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update company');
                    },
                });
            } catch (error) {
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteCompany(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.company.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Company deleted successfully');
                        router.reload({ only: ['companies'] });
                    },
                    onError: (errors) => {
                        const message = errors?.error || 'Failed to delete company';
                        useNotification().error(message);
                    },
                });
            } catch (error) {
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this company? This action cannot be undone.')) {
                await this.deleteCompany(slug);
            }
        },
    },
});
