import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const emptyForm = () => ({
    category_id: '',
    title: '',
    short_subject: '',
    subject: '',
    old_price: null,
    new_price: null,
    discount_percent: null,
    image: null,
    gallery: [],
    deleted_gallery_ids: [],
    governorate_id: '',
    city_id: '',
    iframe_location: '',
    lat: null,
    long: null,
    tag: '',
    tag_ids: [],
});

export const useServiceStore = defineStore('service', {
    state: () => ({
        form: useForm(emptyForm()),
        validationErrors: null,
        services: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm(emptyForm());
            this.validationErrors = null;
        },

        setServices(services) {
            this.services = services;
        },

        setService(service) {
            console.log('[ServiceStore] setService called with', service);
            this.form = useForm({
                id: service.id,
                slug: service.slug || '',
                category_id: service.category_id ?? '',
                title: service.title || '',
                short_subject: service.short_subject || '',
                subject: service.subject || '',
                old_price: service.old_price ?? null,
                new_price: service.new_price ?? null,
                discount_percent: service.discount_percent ?? null,
                image: null,
                gallery: [],
                deleted_gallery_ids: [],
                governorate_id: service.governorate_id ?? '',
                city_id: service.city_id ?? '',
                iframe_location: service.iframe_location || '',
                lat: service.lat ?? null,
                long: service.long ?? null,
                tag: service.tag || '',
                tag_ids: (service.tags || []).map(t => t.id),
            });
            console.log('[ServiceStore] form after assignment', this.form);
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.post(route('admin.service.store'), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Service created successfully');
                        this.initializeForm();
                        router.visit(route('admin.service.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create service');
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

        async updateService() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const serviceSlug = this.form.slug || this.form.id;

                this.form.put(route('admin.service.update', serviceSlug), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Service updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.service.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update service');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating service:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteService(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.service.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Service deleted successfully');
                        router.reload({ only: ['services'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete service');
                    },
                });
            } catch (error) {
                console.error('Error deleting service:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
                await this.deleteService(slug);
            }
        },
    },
});
