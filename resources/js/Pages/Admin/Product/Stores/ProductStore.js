import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateProductForm } from '../validation/productValidation';

export const useProductStore = defineStore('product', {
    state: () => ({
        form: useForm({
            name: {},
            short_subject: {},
            old_price: '',
            new_price: '',
            product_type_id: '',
            tag_ids: [],
            large_image: null,
            small_image: null,
            gallery: [],
        }),
        validationErrors: null,
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
                short_subject: {},
                old_price: '',
                new_price: '',
                product_type_id: '',
                tag_ids: [],
                large_image: null,
                small_image: null,
                gallery: [],
            });
            this.validationErrors = null;
        },

        setProduct(product) {
            let nameValue = product.name || {};
            if (typeof nameValue === 'string') {
                try { nameValue = JSON.parse(nameValue); } catch { nameValue = { ar: nameValue, en: nameValue }; }
            }
            if (!nameValue || typeof nameValue !== 'object' || Array.isArray(nameValue)) nameValue = {};

            let shortSubjectValue = product.short_subject || {};
            if (typeof shortSubjectValue === 'string') {
                try { shortSubjectValue = JSON.parse(shortSubjectValue); } catch { shortSubjectValue = { ar: shortSubjectValue, en: shortSubjectValue }; }
            }
            if (!shortSubjectValue || typeof shortSubjectValue !== 'object' || Array.isArray(shortSubjectValue)) shortSubjectValue = {};

            this.form = useForm({
                name: nameValue,
                short_subject: shortSubjectValue,
                old_price: product.old_price || '',
                new_price: product.new_price || '',
                product_type_id: product.product_type_id || '',
                tag_ids: (product.tags || []).map(t => t.id),
                large_image: null,
                small_image: null,
                gallery: [],
                _existing_large_image: product.large_image || null,
                _existing_small_image: product.small_image || null,
                _existing_gallery: product.gallery || [],
            });

            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const validation = validateProductForm({
                    name: this.form.name,
                    short_subject: this.form.short_subject,
                    old_price: this.form.old_price,
                    new_price: this.form.new_price,
                    product_type_id: this.form.product_type_id,
                }, false);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.product.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Product created successfully');
                        this.initializeForm();
                        router.visit(route('admin.product.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create product');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateForm(slug) {
            this.isLoading = true;
            try {
                const validation = validateProductForm({
                    name: this.form.name,
                    short_subject: this.form.short_subject,
                    old_price: this.form.old_price,
                    new_price: this.form.new_price,
                    product_type_id: this.form.product_type_id,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.put(route('admin.product.update', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Product updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.product.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update product');
                    }
                });
            } catch (error) {
                console.error('Error updating product:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        }
    }
});
