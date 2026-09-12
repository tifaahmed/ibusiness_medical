import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateProductTypeForm } from '../validation/productTypeValidation';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useProductTypeStore = defineStore('productType', {
    state: () => ({
        form: useForm({
            name: {},
        }),
        validationErrors: null,
        productTypes: reactive([]),
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

        setProductTypes(productTypes) {
            this.productTypes = productTypes;
        },

        setProductType(productType) {
            // Ensure name is always an object
            let nameValue = productType.name || {};
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
                id: productType.id,
                slug: productType.slug || '',
                name: nameValue,
            });

            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const url = route('admin.product-type.store');
                // Validate with Zod before submitting
                const validation = validateProductTypeForm({
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
                        useNotification().success('Product type created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.product-type.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create product type');
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

        async updateProductType() {
            this.isLoading = true;
            try {
                const productTypeSlug = this.form.slug || this.form.id;
                const url = route('admin.product-type.update', productTypeSlug);
                // Validate with Zod before submitting
                const validation = validateProductTypeForm({
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
                        useNotification().success('Product type updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.product-type.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update product type');
                    }
                });
            } catch (error) {
                console.error('Error updating product type:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteProductType(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.product-type.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Product type deleted successfully');
                        router.reload({ only: ['productTypes'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete product type');
                    }
                });
            } catch (error) {
                console.error('Error deleting product type:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this product type? This action cannot be undone.')) {
                await this.deleteProductType(slug);
            }
        }
    }
});
