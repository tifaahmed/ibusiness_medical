import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

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
        isLoading: false
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
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.post(route('admin.contract.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Contract created successfully');
                        this.initializeForm();
                        router.visit(route('admin.contract.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create contract');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
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

                this.form.put(route('admin.contract.update', contractSlug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Contract updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.contract.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update contract');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    }
                });
            } catch (error) {
                console.error('Error updating contract:', error);
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
