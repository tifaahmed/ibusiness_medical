import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateProductTypeForm } from '../validation/productTypeValidation';

export const useProductTypeStore = defineStore('productType', {
    state: () => ({
        form: useForm({
            name: {},
        }),
        validationErrors: null,
        productTypes: reactive([]),
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
            });
            this.validationErrors = null;
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
                // Validate with Zod before submitting
                const validation = validateProductTypeForm({
                    name: this.form.name,
                }, false);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.product-type.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Product type created successfully');
                        this.initializeForm();
                        router.visit(route('admin.product-type.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create product type');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateProductType() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateProductTypeForm({
                    name: this.form.name,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                const productTypeSlug = this.form.slug || this.form.id;

                this.form.put(route('admin.product-type.update', productTypeSlug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Product type updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.product-type.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update product type');
                    }
                });
            } catch (error) {
                console.error('Error updating product type:', error);
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
