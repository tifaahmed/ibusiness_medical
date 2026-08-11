import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

export const useFacilityStore = defineStore('facility', {
    state: () => ({
        form: useForm({
            name: {},
            description: {},
            meta_title: {},
            meta_description: {},
            meta_keywords: {},
            canonical_url: '',
            og_image: null,
            og_image_delete: false,
            facility_type_id: '',
            sales_id: '',
            discount_percent: null,
            logo: null,
            mobile_logo: null,
            image: null,
            mobile_image: null,
            tag_ids: [],
            gallery: [],
            gallery_delete: [],
        }),
        validationErrors: null,
        facilities: reactive([]),
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
                description: {},
                meta_title: {},
                meta_description: {},
                meta_keywords: {},
                canonical_url: '',
                og_image: null,
                og_image_delete: false,
                facility_type_id: '',
                sales_id: '',
                discount_percent: null,
                logo: null,
                mobile_logo: null,
                image: null,
                mobile_image: null,
            tag_ids: [],
            gallery: [],
            gallery_delete: [],
        });
        this.validationErrors = null;
    },

        setFacilities(facilities) {
            this.facilities = facilities;
        },

        setFacility(facility) {
            // Ensure translatable fields are always objects
            let nameValue = facility.name || {};
            if (typeof nameValue === 'string') {
                try {
                    nameValue = JSON.parse(nameValue);
                } catch {
                    nameValue = { ar: nameValue, en: nameValue };
                }
            }
            if (!nameValue || typeof nameValue !== 'object' || Array.isArray(nameValue)) {
                nameValue = {};
            }

            // Always create a new form instance to ensure reactivity
            this.form = useForm({
                id: facility.id,
                slug: facility.slug || '',
                name: nameValue,
                description: facility.description || {},
                meta_title: facility.meta_title || {},
                meta_description: facility.meta_description || {},
                meta_keywords: facility.meta_keywords || {},
                canonical_url: facility.canonical_url || '',
                og_image: null,
                og_image_delete: false,
                facility_type_id: facility.facility_type_id || '',
                sales_id: facility.sales_id ?? '',
                discount_percent: facility.discount_percent ?? null,
                tag_ids: (facility.tags || []).map(t => t.id),
                logo: null,
                mobile_logo: null,
                image: null,
                mobile_image: null,
                gallery: [],
                gallery_delete: [],
            });

            this.validationErrors = null;
        },

        async submitForm(branches = []) {
            this.isLoading = true;
            try {
                const errors = {};
                const nameAr = this.form.name?.ar?.toString().trim() || '';
                const nameEn = this.form.name?.en?.toString().trim() || '';
                if (!nameAr) errors['name.ar'] = 'Name (Arabic) is required';
                if (!nameEn) errors['name.en'] = 'Name (English) is required';
                if (!this.form.facility_type_id) errors['facility_type_id'] = 'Facility type is required';

                if (Object.keys(errors).length > 0) {
                    this.validationErrors = errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                // Prepare form data with branches
                const formData = {
                    ...this.form.data(),
                    tag_ids: this.form.tag_ids || [],
                    branches: branches.map(branch => ({
                        id: branch.id || null,
                        name: branch.name || {},
                        address: branch.address || {},
                        phone: branch.phone || null,
                        governorate_id: branch.governorate_id || null,
                        city_id: branch.city_id || null,
                        latitude: branch.latitude ?? null,
                        longitude: branch.longitude ?? null,
                    }))
                };

                this.form.transform(() => formData).post(route('admin.facility.store'), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Facility created successfully');
                        this.initializeForm();
                        router.visit(route('admin.facility.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create facility');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateFacility(branches = []) {
            this.isLoading = true;
            try {
                const errors = {};
                const nameAr = this.form.name?.ar?.toString().trim() || '';
                const nameEn = this.form.name?.en?.toString().trim() || '';
                if (!nameAr) errors['name.ar'] = 'Name (Arabic) is required';
                if (!nameEn) errors['name.en'] = 'Name (English) is required';
                if (!this.form.facility_type_id) errors['facility_type_id'] = 'Facility type is required';

                if (Object.keys(errors).length > 0) {
                    this.validationErrors = errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                const facilitySlug = this.form.slug || this.form.id;
                
                // Prepare form data with branches
                const formData = {
                    ...this.form.data(),
                    tag_ids: this.form.tag_ids || [],
                    branches: branches.map(branch => ({
                        id: branch.id || null,
                        name: branch.name || {},
                        address: branch.address || {},
                        phone: branch.phone || null,
                        governorate_id: branch.governorate_id || null,
                        city_id: branch.city_id || null,
                        latitude: branch.latitude ?? null,
                        longitude: branch.longitude ?? null,
                    }))
                };
                
                this.form.transform(() => ({ ...formData, _method: 'PUT' })).post(route('admin.facility.update', facilitySlug), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Facility updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.facility.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update facility');
                    }
                });
            } catch (error) {
                console.error('Error updating facility:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteFacility(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.facility.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility deleted successfully');
                        router.reload({ only: ['facilities'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete facility');
                    }
                });
            } catch (error) {
                console.error('Error deleting facility:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this facility? This action cannot be undone.')) {
                await this.deleteFacility(slug);
            }
        }
    }
});

