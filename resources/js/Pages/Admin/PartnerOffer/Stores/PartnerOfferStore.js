import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

export const usePartnerOfferStore = defineStore('partnerOffer', {
    state: () => ({
        form: useForm({
            partner_id: '',
            title: '',
            short_description: '',
            description: '',
            old_price: '',
            new_price: '',
            phone_number: '',
            operator: '',
            header_image: null,
            mobile_header_image: null,
            small_image: null,
            mobile_small_image: null,
            gallery: [],
            deleted_gallery_ids: [],
        }),
        validationErrors: null,
        offers: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                partner_id: '',
                title: '',
                short_description: '',
                description: '',
                old_price: '',
                new_price: '',
                phone_number: '',
                operator: '',
                header_image: null,
                mobile_header_image: null,
                small_image: null,
                mobile_small_image: null,
                gallery: [],
                deleted_gallery_ids: [],
            });
            this.validationErrors = null;
        },

        setOffers(offers) {
            this.offers = offers;
        },

        setPartnerOffer(offer) {
            this.form = useForm({
                id: offer.id,
                partner_id: offer.partner_id || '',
                title: offer.title || '',
                short_description: offer.short_description || '',
                description: offer.description || '',
                old_price: offer.old_price || '',
                new_price: offer.new_price || '',
                phone_number: offer.phone_number || '',
                operator: offer.operator || '',
                header_image: null,
                mobile_header_image: null,
                small_image: null,
                mobile_small_image: null,
                gallery: [],
                deleted_gallery_ids: [],
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.post(route('admin.partner-offer.store'), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Partner offer created successfully');
                        this.initializeForm();
                        router.visit(route('admin.partner-offer.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create partner offer');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async updatePartnerOffer() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.transform((data) => ({ ...data, _method: 'PUT' })).post(route('admin.partner-offer.update', this.form.id), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Partner offer updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.partner-offer.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update partner offer');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating partner offer:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deletePartnerOffer(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.partner-offer.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Partner offer deleted successfully');
                        router.reload({ only: ['offers'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete partner offer');
                    },
                });
            } catch (error) {
                console.error('Error deleting partner offer:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this partner offer?')) {
                await this.deletePartnerOffer(id);
            }
        },
    },
});
