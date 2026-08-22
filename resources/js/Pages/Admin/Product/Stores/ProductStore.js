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
            description: {},
            old_price: '',
            new_price: '',
            cost_price: '',
            profit_price: '',
            product_type_id: '',
            admin_note: '',
            banner_config: null,
            tag_ids: [],
            // Marks the tag selection as authoritative: an empty selection is dropped
            // from multipart bodies, so this flag tells the backend to still sync.
            sync_tags: true,
            large_image: null,
            small_image: null,
            remove_large_image: false,
            remove_small_image: false,
            gallery: [],
            removed_gallery_ids: [],
        }),
        validationErrors: null,
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
                short_subject: {},
                description: {},
                old_price: '',
                new_price: '',
                cost_price: '',
                profit_price: '',
                product_type_id: '',
                admin_note: '',
                banner_config: null,
                tag_ids: [],
                sync_tags: true,
                large_image: null,
                small_image: null,
                remove_large_image: false,
                remove_small_image: false,
                gallery: [],
                removed_gallery_ids: [],
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

            let descriptionValue = product.description || {};
            if (typeof descriptionValue === 'string') {
                try { descriptionValue = JSON.parse(descriptionValue); } catch { descriptionValue = { ar: descriptionValue, en: descriptionValue }; }
            }
            if (!descriptionValue || typeof descriptionValue !== 'object' || Array.isArray(descriptionValue)) descriptionValue = {};

            this.form = useForm({
                name: nameValue,
                short_subject: shortSubjectValue,
                description: descriptionValue,
                old_price: product.old_price || '',
                new_price: product.new_price || '',
                cost_price: product.cost_price || '',
                profit_price: product.profit_price || '',
                product_type_id: product.product_type_id || '',
                admin_note: product.admin_note || '',
                banner_config: product.banner_config ?? null,
                tag_ids: (product.tags || []).map(t => t.id),
                sync_tags: true,
                large_image: null,
                small_image: null,
                remove_large_image: false,
                remove_small_image: false,
                gallery: [],
                removed_gallery_ids: [],
            });

            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const validation = validateProductForm({
                    name: this.form.name,
                    short_subject: this.form.short_subject,
                    description: this.form.description,
                    old_price: this.form.old_price,
                    new_price: this.form.new_price,
                    cost_price: this.form.cost_price,
                    profit_price: this.form.profit_price,
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
                    description: this.form.description,
                    old_price: this.form.old_price,
                    new_price: this.form.new_price,
                    cost_price: this.form.cost_price,
                    profit_price: this.form.profit_price,
                    product_type_id: this.form.product_type_id,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                // POST + method spoofing: a real PUT body is not parsed by PHP,
                // so uploaded files would be dropped.
                this.form.transform((data) => ({ ...data, _method: 'PUT' }))
                    .post(route('admin.product.update', slug), {
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
