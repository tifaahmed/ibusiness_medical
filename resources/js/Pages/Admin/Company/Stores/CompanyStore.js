import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateCompanyForm } from '../validation/companyValidation';

export const useCompanyStore = defineStore('company', {
    state: () => ({
        form: useForm({ name: {} }),
        validationErrors: null,
        companies: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({ name: {} });
            this.validationErrors = null;
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
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const validation = validateCompanyForm({ name: this.form.name }, false);
                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    return;
                }
                this.validationErrors = null;
                this.form.post(route('admin.company.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Company created successfully');
                        this.initializeForm();
                        router.visit(route('admin.company.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create company');
                    },
                });
            } catch (error) {
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateCompany() {
            this.isLoading = true;
            try {
                const validation = validateCompanyForm({ name: this.form.name }, true);
                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    return;
                }
                this.validationErrors = null;
                const slug = this.form.slug || this.form.id;
                this.form.put(route('admin.company.update', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Company updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.company.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update company');
                    },
                });
            } catch (error) {
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
