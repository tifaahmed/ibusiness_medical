import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';

export const useNewsTickerStore = defineStore('newsTicker', {
    state: () => ({
        form: useForm({
            title: { ar: '', en: '' },
            description: { ar: '', en: '' },
            category: '',
            image: null,
            mobile_image: null,
            is_active: true,
            sort_order: 0,
        }),
        validationErrors: null,
        newsTickers: reactive([]),
        isLoading: false,
        debugLog: null,
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                title: { ar: '', en: '' },
                description: { ar: '', en: '' },
                category: '',
                image: null,
                mobile_image: null,
                is_active: true,
                sort_order: 0,
            });
            this.validationErrors = null;
            this.debugLog = null;
        },

        setNewsTickers(newsTickers) {
            this.newsTickers = newsTickers;
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

        setNewsTicker(item) {
            this.form = useForm({
                id: item.id,
                title: this.normalizeTranslatable(item.title),
                description: this.normalizeTranslatable(item.description),
                category: item.category || '',
                image: null,
                mobile_image: null,
                is_active: item.is_active ?? true,
                sort_order: item.sort_order ?? 0,
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.news-ticker.store');
                this.debugLog = buildDebugLog({ method: 'POST', url, fields: this.form.data() });

                this.form.post(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('News Ticker created successfully');
                        this.debugLog = null;
                        this.initializeForm();
                        router.visit(route('admin.news-ticker.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = errors;
                        recordResponse(this.debugLog, errors);
                        useNotification().error(errors.error || 'Failed to create news ticker');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                this.isLoading = false;
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            }
        },

        async updateNewsTicker() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const url = route('admin.news-ticker.update', this.form.id);
                this.debugLog = buildDebugLog({ method: 'PUT', url, fields: this.form.data() });

                this.form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('News Ticker updated successfully');
                        this.debugLog = null;
                        router.visit(route('admin.news-ticker.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = errors;
                        recordResponse(this.debugLog, errors);
                        useNotification().error(errors.error || 'Failed to update news ticker');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                this.isLoading = false;
                recordResponse(this.debugLog, {}, `${error.name}: ${error.message}`);
                useNotification().error('An unexpected error occurred');
            }
        },

        confirmDelete(id) {
            if (!confirm('Are you sure you want to delete this news item?')) return;

            router.delete(route('admin.news-ticker.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    useNotification().success('News Ticker deleted successfully');
                },
                onError: (errors) => {
                    useNotification().error(errors.error || 'Failed to delete news ticker');
                },
            });
        },
    },
});
