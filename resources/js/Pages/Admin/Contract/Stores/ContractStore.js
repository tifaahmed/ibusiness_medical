import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useContractStore = defineStore('contract', {
    state: () => ({
        form: useForm({
            name: { ar: '', en: '' },
            description: { ar: '', en: '' },
            phones: [],
            is_active: true,
            sort_order: 0,
            image: null,
        }),
        validationErrors: null,
        contracts: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: { ar: '', en: '' },
                description: { ar: '', en: '' },
                phones: [],
                is_active: true,
                sort_order: 0,
                image: null,
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setContracts(contracts) {
            this.contracts = contracts;
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

        setContract(contract) {
            const nameValue = this.normalizeTranslatable(contract.name);
            const descriptionValue = this.normalizeTranslatable(contract.description);

            this.form = useForm({
                id: contract.id,
                slug: contract.slug || '',
                name: nameValue,
                description: descriptionValue,
                phones: Array.isArray(contract.phones) ? contract.phones : [],
                is_active: contract.is_active ?? true,
                sort_order: contract.sort_order ?? 0,
                image: null,
            });

            this.validationErrors = null;
            this.debugLog = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.contract.store');
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Contract created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.contract.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create contract');
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

        async updateContract() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const contractSlug = this.form.slug || this.form.id;
                const url = route('admin.contract.update', contractSlug);
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Contract updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.contract.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update contract');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    }
                });
            } catch (error) {
                console.error('Error updating contract:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteContract(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.contract.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Contract deleted successfully');
                        router.reload({ only: ['contracts'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete contract');
                    }
                });
            } catch (error) {
                console.error('Error deleting contract:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this contract? This action cannot be undone.')) {
                await this.deleteContract(slug);
            }
        }
    }
});
