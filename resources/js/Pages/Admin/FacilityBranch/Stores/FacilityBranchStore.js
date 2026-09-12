import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateFacilityBranchForm } from '../validation/facilityBranchValidation';
import { normalizePhoneEntries } from '@/lib/branchPhones';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useFacilityBranchStore = defineStore('facilityBranch', {
    state: () => ({
        form: useForm({
            name: {},
            address: {},
            phone: [],
            facility_id: '',
            governorate_id: '',
            city_id: '',
            latitude: '',
            longitude: '',
            google_location_url: '',
        }),
        validationErrors: null,
        facilityBranches: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: {},
                address: {},
                phone: [],
                facility_id: '',
                governorate_id: '',
                city_id: '',
                latitude: '',
                longitude: '',
                google_location_url: '',
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setFacilityBranches(facilityBranches) {
            this.facilityBranches = facilityBranches;
        },

        setFacilityBranch(facilityBranch) {
            // Ensure translatable fields are always objects
            let nameValue = facilityBranch.name || {};
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

            let addressValue = facilityBranch.address || {};
            if (typeof addressValue === 'string') {
                try {
                    addressValue = JSON.parse(addressValue);
                } catch {
                    addressValue = { ar: addressValue, en: addressValue };
                }
            }
            if (!addressValue || typeof addressValue !== 'object' || Array.isArray(addressValue)) {
                addressValue = {};
            }
            
            /* Phones are typed entries — { number, type } — not bare strings.
               Stringifying one gives "[object Object]" in the number box, so the
               shared normaliser is what reads them: it takes the typed shape,
               the flat list a row saved before the types existed, and a single
               string, and answers with entries either way. */
            const phoneValue = normalizePhoneEntries(facilityBranch.phone);

            
            // Always create a new form instance to ensure reactivity
            this.form = useForm({
                id: facilityBranch.id,
                slug: facilityBranch.slug || '',
                name: nameValue,
                address: addressValue,
                phone: phoneValue,
                facility_id: facilityBranch.facility_id || '',
                governorate_id: facilityBranch.governorate_id || '',
                city_id: facilityBranch.city_id || '',
                latitude: facilityBranch.latitude ?? '',
                longitude: facilityBranch.longitude ?? '',
                google_location_url: facilityBranch.google_location_url ?? '',
            });

            this.validationErrors = null;
            this.debugLog = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                const url = route('admin.facility-branch.store');
                // Validate with Zod before submitting
                const validation = validateFacilityBranchForm({
                    name: this.form.name,
                    address: this.form.address,
                    phone: this.form.phone,
                    facility_id: this.form.facility_id,
                    governorate_id: this.form.governorate_id,
                    city_id: this.form.city_id,
                    latitude: this.form.latitude,
                    longitude: this.form.longitude,
                    google_location_url: this.form.google_location_url,
                }, false);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    this.debugLog = buildDebugLog({
                        method: 'POST', url, fields: this.form.data(),
                        note: 'Not actually sent — blocked by client-side validation below.',
                        responseErrors: validation.errors,
                        responseNote: 'Client-side validation failure. The server was never reached.',
                    });
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility branch created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.facility-branch.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create facility branch');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateFacilityBranch() {
            this.isLoading = true;
            try {
                const facilityBranchSlug = this.form.slug || this.form.id;
                const url = route('admin.facility-branch.update', facilityBranchSlug);
                // Validate with Zod before submitting
                const validation = validateFacilityBranchForm({
                    name: this.form.name,
                    address: this.form.address,
                    phone: this.form.phone,
                    facility_id: this.form.facility_id,
                    governorate_id: this.form.governorate_id,
                    city_id: this.form.city_id,
                    latitude: this.form.latitude,
                    longitude: this.form.longitude,
                    google_location_url: this.form.google_location_url,
                }, true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    this.debugLog = buildDebugLog({
                        method: 'PUT', url, fields: this.form.data(),
                        note: 'Not actually sent — blocked by client-side validation below.',
                        responseErrors: validation.errors,
                        responseNote: 'Client-side validation failure. The server was never reached.',
                    });
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility branch updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.facility-branch.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update facility branch');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    }
                });
            } catch (error) {
                console.error('Error updating facility branch:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteFacilityBranch(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.facility-branch.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Facility branch deleted successfully');
                        router.reload({ only: ['facilityBranches'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete facility branch');
                    }
                });
            } catch (error) {
                console.error('Error deleting facility branch:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this facility branch? This action cannot be undone.')) {
                await this.deleteFacilityBranch(slug);
            }
        }
    }
});

