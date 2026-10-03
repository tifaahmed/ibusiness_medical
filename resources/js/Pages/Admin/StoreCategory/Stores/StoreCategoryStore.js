import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateStoreCategoryForm } from '../validation/storeCategoryValidation';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useStoreCategoryStore = defineStore('storeCategory', {
    state: () => ({
        form: useForm({
            name: {},
        }),
        validationErrors: null,
        storeCategories: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setStoreCategories(storeCategories) {
            this.storeCategories = storeCategories;
        },

        setStoreCategory(storeCategory) {
            // Ensure name is always an object
            let nameValue = storeCategory.name || {};
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
                id: storeCategory.id,
                slug: storeCategory.slug || '',
                name: nameValue,
            });

            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const url = route('admin.store-category.store');
                // Validate with Zod before submitting
                const validation = validateStoreCategoryForm({
                    name: this.form.name,
                }, false);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    this.debugLog = buildDebugLog({
                        method: 'POST', url, fields: this.form.data(),
                        note: 'Not actually sent — blocked by client-side validation below.',
                        responseErrors: validation.errors,
                        responseNote: 'Client-side validation failure. The server was never reached.',
                    });
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Store category created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.store-category.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create store category');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateStoreCategory() {
            this.isLoading = true;
            try {
                const storeCategorySlug = this.form.slug || this.form.id;
                const url = route('admin.store-category.update', storeCategorySlug);
                // Validate with Zod before submitting
                const validation = validateStoreCategoryForm({
                    name: this.form.name,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    this.debugLog = buildDebugLog({
                        method: 'PUT', url, fields: this.form.data(),
                        note: 'Not actually sent — blocked by client-side validation below.',
                        responseErrors: validation.errors,
                        responseNote: 'Client-side validation failure. The server was never reached.',
                    });
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Store category updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.store-category.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update store category');
                    }
                });
            } catch (error) {
                console.error('Error updating store category:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteStoreCategory(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.store-category.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Store category deleted successfully');
                        router.reload({ only: ['storeCategories'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete store category');
                    }
                });
            } catch (error) {
                console.error('Error deleting store category:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this store category? This action cannot be undone.')) {
                await this.deleteStoreCategory(slug);
            }
        }
    }
});
