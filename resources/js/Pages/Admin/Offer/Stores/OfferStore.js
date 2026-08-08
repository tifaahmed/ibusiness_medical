import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateOfferForm } from '../validation/offerValidation';

const getT = () => usePage().props.translations?.admin?.offer || {};

export const useOfferStore = defineStore('offer', {
    state: () => ({
        form: useForm({
            title: { ar: '', en: '' },
            short_description: { ar: '', en: '' },
            full_description: { ar: '', en: '' },
            phone: '',
            price: null,
            old_price: null,
            offerable_type: '',
            offerable_id: '',
            image: null,
            mobile_image: null,
            thumbnail: null,
            mobile_thumbnail: null,
        }),
        validationErrors: null,
        offers: reactive([]),
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                title: { ar: '', en: '' },
                short_description: { ar: '', en: '' },
                full_description: { ar: '', en: '' },
                phone: '',
                price: null,
                old_price: null,
                offerable_type: '',
                offerable_id: '',
                image: null,
                mobile_image: null,
                thumbnail: null,
                mobile_thumbnail: null,
            });
            this.validationErrors = null;
        },

        setOffers(offers) {
            this.offers = offers;
        },

        normalizeTranslatable(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return { ar: '', en: '' };
            }
            return {
                ar: value.ar?.toString() || '',
                en: value.en?.toString() || ''
            };
        },

        setOffer(offer) {
            // Ensure translatable fields are always objects
            const titleValue = this.normalizeTranslatable(offer.title);
            const shortDescValue = this.normalizeTranslatable(offer.short_description);
            const fullDescValue = this.normalizeTranslatable(offer.full_description);

            // Always create a new form instance to ensure reactivity
            this.form = useForm({
                id: offer.id,
                slug: offer.slug || '',
                title: titleValue,
                short_description: shortDescValue,
                full_description: fullDescValue,
                phone: offer.phone?.toString() || '',
                price: offer.price || null,
                old_price: offer.old_price || null,
                offerable_type: offer.offerable_type || '',
                offerable_id: offer.offerable_id?.toString() || '',
                image: null,
                mobile_image: null,
                thumbnail: null,
                mobile_thumbnail: null,
            });

            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateOfferForm({
                    title: this.form.title,
                    short_description: this.form.short_description,
                    full_description: this.form.full_description,
                    phone: this.form.phone,
                    price: this.form.price,
                    old_price: this.form.old_price,
                    offerable_type: this.form.offerable_type,
                    offerable_id: this.form.offerable_id,
                }, false, getT().validation || {});

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error(getT().validation_error || 'Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.offer.store'), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success(getT().created || 'Offer created successfully');
                        this.initializeForm();
                        router.visit(route('admin.offer.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error(getT().create_failed || 'Failed to create offer');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateOffer() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateOfferForm({
                    title: this.form.title,
                    short_description: this.form.short_description,
                    full_description: this.form.full_description,
                    phone: this.form.phone,
                    price: this.form.price,
                    old_price: this.form.old_price,
                    offerable_type: this.form.offerable_type,
                    offerable_id: this.form.offerable_id,
                }, true, getT().validation || {});

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error(getT().validation_error || 'Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                const offerSlug = this.form.slug || this.form.id;

                this.form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('admin.offer.update', offerSlug), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success(getT().updated || 'Offer updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.offer.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error(getT().update_failed || 'Failed to update offer');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    }
                });
            } catch (error) {
                console.error('Error updating offer:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteOffer(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.offer.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success(getT().deleted || 'Offer deleted successfully');
                        router.reload({ only: ['offers'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error(getT().delete_failed || 'Failed to delete offer');
                    }
                });
            } catch (error) {
                console.error('Error deleting offer:', error);
                useNotification().error(getT().unexpected_error || 'An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm(getT().confirm_delete || 'Are you sure you want to delete this offer? This action cannot be undone.')) {
                await this.deleteOffer(slug);
            }
        }
    }
});
