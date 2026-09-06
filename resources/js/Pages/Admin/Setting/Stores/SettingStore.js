import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const emptyForm = () => ({
    // The key the application reads the row by — SiteSettings::get(slug).
    slug: '',
    // A map of locale to text — the label is translatable.
    name: {},
    value: '',
    value_type: 'string',
    // Only used by an `image` setting: the upload, and the flag that clears
    // the stored file without putting a new one up.
    value_image: null,
    value_image_delete: false,
});

/**
 * The name as an object, whatever the server sent — mirrors the tag store.
 */
const nameObject = (name) => {
    if (typeof name === 'string') {
        try {
            const parsed = JSON.parse(name);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                return parsed;
            }
        } catch {
            // Not json — one name in both languages.
        }
        return name ? { ar: name, en: name } : {};
    }

    if (!name || typeof name !== 'object' || Array.isArray(name)) {
        return {};
    }

    return name;
};

export const useSettingStore = defineStore('setting', {
    state: () => ({
        form: useForm(emptyForm()),
        validationErrors: null,
        settings: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm(emptyForm());
            this.validationErrors = null;
        },

        setSettings(settings) {
            this.settings = settings;
        },

        setSetting(setting) {
            this.form = useForm({
                id: setting.id,
                slug: setting.slug || '',
                name: nameObject(setting.name),
                // An image row keeps its path in `value`; the file input shows
                // the stored image and only replaces it when one is picked.
                value: setting.value_type === 'image' ? '' : (setting.value ?? ''),
                value_type: setting.value_type || 'string',
                value_image: null,
                value_image_delete: false,
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.post(route('admin.setting.store'), {
                    preserveScroll: true,
                    // An image setting posts a file, so the payload has to go
                    // as multipart rather than JSON.
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Setting created successfully');
                        this.initializeForm();
                        router.visit(route('admin.setting.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create setting');
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

        async updateSetting() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const settingId = this.form.id;

                // PUT cannot carry a file upload, so the update is posted with
                // a method override — the same trick the product form uses.
                this.form
                    .transform((data) => ({ ...data, _method: 'PUT' }))
                    .post(route('admin.setting.update', settingId), {
                        preserveScroll: true,
                        forceFormData: true,
                        onSuccess: () => {
                            useNotification().success('Setting updated successfully');
                            this.initializeForm();
                            router.visit(route('admin.setting.list'));
                        },
                        onError: (errors) => {
                            this.validationErrors = { ...this.validationErrors, ...errors };
                            useNotification().error('Failed to update setting');
                        },
                        onFinish: () => {
                            this.isLoading = false;
                        },
                    });
            } catch (error) {
                console.error('Error updating setting:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteSetting(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.setting.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Setting deleted successfully');
                        router.reload({ only: ['settings'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete setting');
                    },
                });
            } catch (error) {
                console.error('Error deleting setting:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            // Deleting a setting is not like deleting a row of content: whatever
            // reads the key falls back to its default, so say so before doing it.
            if (confirm('Delete this setting? Anything reading this key will fall back to its default. This cannot be undone.')) {
                await this.deleteSetting(id);
            }
        },
    },
});
