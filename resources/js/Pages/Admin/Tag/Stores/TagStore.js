import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const emptyForm = () => ({
    // A map of locale to text — the name is translatable.
    name: {},
    icon: '',
    color: '',
});

/**
 * The name as an object, whatever the server sent.
 *
 * A row saved before the name became translatable comes back as a plain
 * string; it is written to both languages rather than dropped, so editing it
 * starts from the name that is already on the tag.
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

export const useTagStore = defineStore('tag', {
    state: () => ({
        form: useForm(emptyForm()),
        validationErrors: null,
        tags: reactive([]),
        isLoading: false,
    }),

    actions: {
        initializeForm() {
            this.form = useForm(emptyForm());
            this.validationErrors = null;
        },

        setTags(tags) {
            this.tags = tags;
        },

        setTag(tag) {
            this.form = useForm({
                id: tag.id,
                name: nameObject(tag.name),
                icon: tag.icon || '',
                color: tag.color || '',
            });
            this.validationErrors = null;
        },

        async submitForm() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                this.form.post(route('admin.tag.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Tag created successfully');
                        this.initializeForm();
                        router.visit(route('admin.tag.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create tag');
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

        async updateTag() {
            this.isLoading = true;
            try {
                this.validationErrors = null;

                const tagId = this.form.id;

                this.form.put(route('admin.tag.update', tagId), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Tag updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.tag.list'));
                    },
                    onError: (errors) => {
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update tag');
                    },
                    onFinish: () => {
                        this.isLoading = false;
                    },
                });
            } catch (error) {
                console.error('Error updating tag:', error);
                useNotification().error('An unexpected error occurred');
                this.isLoading = false;
            }
        },

        async deleteTag(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.tag.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Tag deleted successfully');
                        router.reload({ only: ['tags'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete tag');
                    },
                });
            } catch (error) {
                console.error('Error deleting tag:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this tag? This action cannot be undone.')) {
                await this.deleteTag(id);
            }
        },
    },
});
