import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateGovernorateForm } from '../validation/governorateValidation';

export const useGovernorateStore = defineStore('governorate', {
    state: () => ({
        form: useForm({
            name: {},
        }),
        validationErrors: null,
        governorates: reactive([]),
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
            });
            this.validationErrors = null;
        },

        setGovernorates(governorates) {
            this.governorates = governorates;
        },

        setGovernorate(governorate) {
            // Ensure name is always an object
            let nameValue = governorate.name || {};
            if (typeof nameValue === 'string') {
                try {
                    nameValue = JSON.parse(nameValue);
                } catch {
                    // If parsing fails, create object with default locale
                    nameValue = { ar: nameValue, en: nameValue };
                }
            }
            if (!nameValue || typeof nameValue !== 'object' || Array.isArray(nameValue)) {
                nameValue = {};
            }
            
            // Always create a new form instance to ensure reactivity
            // This is safe because Inertia's useForm is reactive
            this.form = useForm({
                id: governorate.id,
                slug: governorate.slug || '',
                name: nameValue,
                cities: Array.isArray(governorate.cities) ? governorate.cities : [],
            });

            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateGovernorateForm({
                    name: this.form.name,
                }, false);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.governorate.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Governorate created successfully');
                        this.initializeForm();
                        router.visit(route('admin.governorate.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create governorate');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateGovernorate() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateGovernorateForm({
                    name: this.form.name,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                const governorateSlug = this.form.slug || this.form.id;

                const formData = {
                    ...this.form.data(),
                    cities: (this.form.cities || []).map(c => ({
                        id: c.id || null,
                        name: c.name || {},
                    })),
                };

                this.form.transform(() => formData).put(route('admin.governorate.update', governorateSlug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Governorate updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.governorate.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update governorate');
                    }
                });
            } catch (error) {
                console.error('Error updating governorate:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteGovernorate(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.governorate.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Governorate deleted successfully');
                        router.reload({ only: ['governorates'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete governorate');
                    }
                });
            } catch (error) {
                console.error('Error deleting governorate:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this governorate? This action cannot be undone.')) {
                await this.deleteGovernorate(slug);
            }
        }
    }
});

