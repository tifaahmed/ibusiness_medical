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
            // The three storefront switches. A new product is live everywhere.
            is_visible: true,
            is_accessible: true,
            is_purchasable: true,
            admin_note: '',
            banner_config: null,
            meta_title: {},
            meta_description: {},
            meta_keywords: {},
            canonical_url: '',
            tag_ids: [],
            // Marks the tag selection as authoritative: an empty selection is dropped
            // from multipart bodies, so this flag tells the backend to still sync.
            sync_tags: true,
            large_image: null,
            small_image: null,
            og_image: null,
            og_image_delete: false,
            remove_large_image: false,
            remove_small_image: false,
            gallery: [],
            removed_gallery_ids: [],
            // Disk paths of images uploaded from inside the description editor;
            // they become gallery rows when the form is saved.
            editor_gallery_paths: [],
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
                // The three storefront switches. A new product is live everywhere.
                is_visible: true,
                is_accessible: true,
                is_purchasable: true,
                admin_note: '',
                banner_config: null,
                meta_title: {},
                meta_description: {},
                meta_keywords: {},
                canonical_url: '',
                tag_ids: [],
                sync_tags: true,
                large_image: null,
                small_image: null,
                remove_large_image: false,
                remove_small_image: false,
                gallery: [],
                removed_gallery_ids: [],
                editor_gallery_paths: [],
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

            const seoMap = (value) => {
                let parsed = value || {};
                if (typeof parsed === 'string') {
                    try { parsed = JSON.parse(parsed); } catch { parsed = {}; }
                }
                return (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) ? {} : parsed;
            };

            this.form = useForm({
                name: nameValue,
                short_subject: shortSubjectValue,
                description: descriptionValue,
                old_price: product.old_price || '',
                new_price: product.new_price || '',
                cost_price: product.cost_price || '',
                profit_price: product.profit_price || '',
                product_type_id: product.product_type_id || '',
                /*
                 * `?? true` and not `||`: a switched-off flag arrives as
                 * false, and `false || true` would turn every hidden product
                 * back on the moment somebody opened its edit form.
                 */
                is_visible: product.is_visible ?? true,
                is_accessible: product.is_accessible ?? true,
                is_purchasable: product.is_purchasable ?? true,
                admin_note: product.admin_note || '',
                banner_config: product.banner_config ?? null,
                meta_title: seoMap(product.meta_title),
                meta_description: seoMap(product.meta_description),
                meta_keywords: seoMap(product.meta_keywords),
                canonical_url: product.canonical_url || '',
                tag_ids: (product.tags || []).map(t => t.id),
                sync_tags: true,
                large_image: null,
                small_image: null,
                remove_large_image: false,
                remove_small_image: false,
                gallery: [],
                removed_gallery_ids: [],
                editor_gallery_paths: [],
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
                    },
                    onFinish: () => { this.isLoading = false; }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        // afterSave: 'return' (back to the list), 'stay' (reload the edit form)
        // or 'show' (open the product page). The backend owns the redirect.
        async updateForm(slug, afterSave = 'return') {
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
                this.form.transform((data) => ({ ...data, _method: 'PUT', after_save: afterSave }))
                    .post(route('admin.product.update', slug), {
                        preserveScroll: true,
                        onSuccess: () => {
                            useNotification().success('Product updated successfully');
                        },
                        onError: (errors) => {
                            this.validationErrors = { ...this.validationErrors, ...errors };
                            useNotification().error('Failed to update product');
                        },
                        onFinish: () => { this.isLoading = false; }
                    });
            } catch (error) {
                console.error('Error updating product:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        }
    }
});
