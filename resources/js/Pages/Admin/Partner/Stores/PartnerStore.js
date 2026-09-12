import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const usePartnerStore = defineStore('partner', {
    state: () => ({
        form: useForm({
            title: '',
            short_description: '',
            description: '',
            image: null,
            header_image: null,
            gallery: [],
            deleted_gallery_ids: [],
        }),
        validationErrors: null,
        partners: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                title: '',
                short_description: '',
                description: '',
                image: null,
                header_image: null,
                gallery: [],
                deleted_gallery_ids: [],
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setPartners(partners) {
            this.partners = partners;
        },

        setPartner(partner) {
            this.form = useForm({
                id: partner.id,
                title: partner.title || '',
                short_description: partner.short_description || '',
                description: partner.description || '',
                image: null,
                header_image: null,
                gallery: [],
                deleted_gallery_ids: [],
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.partner.store');
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Partner created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.partner.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create partner');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async updatePartner() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.partner.update', this.form.id);
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Partner updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.partner.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update partner');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating partner:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deletePartner(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.partner.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Partner deleted successfully');
                        router.reload({ only: ['partners'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete partner');
                    },
                });
            } catch (error) {
                console.error('Error deleting partner:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this partner? This action cannot be undone.')) {
                await this.deletePartner(id);
            }
        },
    },
});
