import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const emptyForm = () => ({
    name: {},
    description: '',
    icon: '',
    color: '',
    is_active: true,
    image: null,
});

export const useServiceTypeStore = defineStore('serviceType', {
    state: () => ({
        form: useForm(emptyForm()),
        validationErrors: null,
        serviceTypes: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm(emptyForm());
            this.validationErrors = null;
        },

        setServiceTypes(serviceTypes) {
            this.serviceTypes = serviceTypes;
        },

        setServiceType(serviceType) {
            let nameValue = serviceType.name || {};
            if (!nameValue || typeof nameValue !== 'object' || Array.isArray(nameValue)) {
                nameValue = {};
            }

            this.form = useForm({
                id: serviceType.id,
                name: nameValue,
                description: serviceType.description || '',
                icon: serviceType.icon || '',
                color: serviceType.color || '',
                is_active: serviceType.is_active ?? true,
                image: null,
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.post(route('admin.service-type.store'), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Service category created successfully');
                        this.initializeForm();
                        router.visit(route('admin.service-type.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create service category');
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

        async updateServiceType() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const serviceTypeId = this.form.id;

                this.form.put(route('admin.service-type.update', serviceTypeId), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Service category updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.service-type.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update service category');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating service category:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteServiceType(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.service-type.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Service category deleted successfully');
                        router.reload({ only: ['serviceTypes'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete service category');
                    },
                });
            } catch (error) {
                console.error('Error deleting service category:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this service category? This action cannot be undone.')) {
                await this.deleteServiceType(id);
            }
        },
    },
});
