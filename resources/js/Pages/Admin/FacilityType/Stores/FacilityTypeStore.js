import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateFacilityTypeForm } from '../validation/facilityTypeValidation';

export const useFacilityTypeStore = defineStore('facilityType', {
    state: () => ({
        form: useForm({
            name: {},
        }),
        validationErrors: null,
        facilityTypes: reactive([]),
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
            });
            this.validationErrors = null;
        },

        setFacilityTypes(facilityTypes) {
            this.facilityTypes = facilityTypes;
        },

        setFacilityType(facilityType) {
            // Ensure name is always an object
            let nameValue = facilityType.name || {};
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
                id: facilityType.id,
                slug: facilityType.slug || '',
                name: nameValue,
            });
            
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateFacilityTypeForm({
                    name: this.form.name,
                }, false);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                this.form.post(route('admin.facility-type.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility type created successfully');
                        this.initializeForm();
                        router.visit(route('admin.facility-type.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create facility type');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateFacilityType() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateFacilityTypeForm({
                    name: this.form.name,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                const facilityTypeSlug = this.form.slug || this.form.id;
                
                this.form.put(route('admin.facility-type.update', facilityTypeSlug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility type updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.facility-type.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update facility type');
                    }
                });
            } catch (error) {
                console.error('Error updating facility type:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteFacilityType(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.facility-type.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility type deleted successfully');
                        router.reload({ only: ['facilityTypes'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete facility type');
                    }
                });
            } catch (error) {
                console.error('Error deleting facility type:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this facility type? This action cannot be undone.')) {
                await this.deleteFacilityType(slug);
            }
        }
    }
});

