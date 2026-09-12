import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useFaqStore = defineStore('faq', {
    state: () => ({
        form: useForm({
            question: { ar: '', en: '' },
            answer: { ar: '', en: '' },
            is_active: true,
            sort_order: 0,
        }),
        validationErrors: null,
        faqs: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                question: { ar: '', en: '' },
                answer: { ar: '', en: '' },
                is_active: true,
                sort_order: 0,
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setFaqs(faqs) {
            this.faqs = faqs;
        },

        normalizeTranslatable(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                return { ar: '', en: '' };
            }
            return {
                ar: value.ar?.toString() || '',
                en: value.en?.toString() || '',
            };
        },

        setFaq(faq) {
            this.form = useForm({
                id: faq.id,
                question: this.normalizeTranslatable(faq.question),
                answer: this.normalizeTranslatable(faq.answer),
                is_active: faq.is_active ?? true,
                sort_order: faq.sort_order ?? 0,
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.faq.store');
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('FAQ created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.faq.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to create FAQ');
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

        async updateFaq() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.faq.update', this.form.id);
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('FAQ updated successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.faq.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        recordResponse(this.debugLog, errors);
                        useNotification().error('Failed to update FAQ');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating FAQ:', error);
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteFaq(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.faq.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('FAQ deleted successfully');
                        router.reload({ only: ['faqs'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete FAQ');
                    },
                });
            } catch (error) {
                console.error('Error deleting FAQ:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this FAQ? This action cannot be undone.')) {
                await this.deleteFaq(id);
            }
        },
    },
});
