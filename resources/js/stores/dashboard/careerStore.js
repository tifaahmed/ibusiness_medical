import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateCareerForm } from '@/Pages/Admin/User/Member/validation/careerValidation';

export const useCareerStore = defineStore('career', {
    state: () => ({
        form: useForm({
            name: '',
            description: '',
            application_end_date: '',
            is_active: false,
        }),
        validationErrors: null,
        careers: reactive([]),
        isLoading: false
    }),

    actions: {
        initializeForm() {
            this.form = useForm({
                name: '',
                description: '',
                application_end_date: '',
                is_active: false,
            });
            this.validationErrors = null;
        },

        setCareers(careers) {
            this.careers = careers;
        },

        setCareer(career) {
            this.form = useForm({
                id: career.id,
                name: career.name || '',
                description: career.description || '',
                application_end_date: career.application_end_date || '',
                is_active: career.is_active ?? false,
            });
        },

        async submitForm() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateCareerForm({
                    name: this.form.name,
                    description: this.form.description,
                    application_end_date: this.form.application_end_date,
                    is_active: this.form.is_active,
                });

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                // Note: Update the route name when you have the actual career routes
                this.form.post(route('admin.career.store'), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Career created successfully');
                        this.initializeForm();
                        router.visit(route('admin.career.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create career');
                    }
                });
            } catch (error) {
                console.error('Error submitting form:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateCareer() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateCareerForm({
                    name: this.form.name,
                    description: this.form.description,
                    application_end_date: this.form.application_end_date,
                    is_active: this.form.is_active,
                });

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                // Note: Update the route name when you have the actual career routes
                this.form.put(route('admin.career.update', this.form.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Career updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.career.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update career');
                    }
                });
            } catch (error) {
                console.error('Error updating career:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteCareer(id) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.career.delete', id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Career deleted successfully');
                        this.careers.data = this.careers.data.filter(career => career.id !== id);
                    },
                    onError: () => {
                        useNotification().error('Failed to delete career');
                    }
                });
            } catch (error) {
                console.error('Error deleting career:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(id) {
            if (confirm('Are you sure you want to delete this career?')) {
                await this.deleteCareer(id);
            }
        }
    }
});


