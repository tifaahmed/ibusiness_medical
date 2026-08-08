import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

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

                this.form.post(route('admin.news-ticker.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('News Ticker created successfully');
                        this.initializeForm();
                        router.visit(route('admin.news-ticker.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = errors;
                        useNotification().error(errors.error || 'Failed to create news ticker');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                this.isLoading = false;
                useNotification().error('An unexpected error occurred');
            }
        },

        async updateNewsTicker() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.put(route('admin.news-ticker.update', this.form.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('News Ticker updated successfully');
                        router.visit(route('admin.news-ticker.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = errors;
                        useNotification().error(errors.error || 'Failed to update news ticker');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                this.isLoading = false;
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
