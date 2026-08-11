import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';
import { validateMemberForm } from '../validation/memberValidation';

export const useMemberStore = defineStore('member', {
    state: () => ({
        form: useForm({
            id: null,
            slug: '',
            name: '',
            email: '',
            phone: '',
            password: '',
            password_confirmation: '',
            avatar: null,
            avatar_url: null,
            membership_number: '',
            national_id: '',
            registration_date: '',
            expiration_date: '',
            is_active: false,  // Default to false - will be set properly in setMember
            is_visible: true,  // Default to true
            is_paid: false,  // Default to false
            payment_type: '',
            membership_completed_at: null,  // read-only, from server
            has_member_payments: false,     // read-only, from server — gates the edit-mode Payment card
            initial_payment_amount: '',
            initial_payment_type: 'commission',
            initial_payment_months_paid: '',
            initial_payment_from_date: '',
            initial_payment_to_date: '',
            initial_payment_notes: '',
            job_title: { ar: '', en: '' },
            company_id: null,
            partner_id: null,
            sales_id: null,
            governorate_id: null,
            city_id: null,
            contract_image: null,           // new File pending upload
            contract_image_url: null,       // existing URL (edit mode)
            contract_image_remove: false,   // ask the server to drop the existing one
            gallery_images: [],             // new Files pending upload
            gallery_existing: [],           // [{ id, url, name }, ...] for edit mode
            gallery_remove_ids: [],         // IDs of existing media to delete
        }),
        validationErrors: null,
        members: reactive([]),
        isLoading: false
    }),

    actions: {
        async initializeForm() {
            // Reset all form fields for new member creation
            this.form.id = null;
            this.form.slug = '';
            this.form.name = '';
            this.form.email = '';
            this.form.phone = '';
            this.form.password = '';
            this.form.password_confirmation = '';
            this.form.avatar = null;
            this.form.avatar_url = null;

            this.form.national_id = '';

            // Generate unique 4-digit membership number from API
            try {
                const response = await axios.get('/api/membership-number/generate');
                this.form.membership_number = response.data.membership_number;
                console.log('Generated unique membership number:', response.data.membership_number);
            } catch (error) {
                console.error('Failed to generate membership number:', error);
                // Fallback to client-side generation if API fails
                const randomFourDigit = Math.floor(1000 + Math.random() * 9000);
                this.form.membership_number = randomFourDigit.toString();
                console.log('Using fallback membership number:', this.form.membership_number);
            }

            // Set registration_date to today's date
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            this.form.registration_date = `${year}-${month}-${day}`;
            this.form.expiration_date = '';
            this.form.is_active = true;  // Default to true for new members
            this.form.is_visible = true;  // Default to true for new members
            this.form.is_paid = false;  // Default to false for new members
            this.form.payment_type = '';
            this.form.membership_completed_at = null;
            this.form.has_member_payments = false;
            this.form.initial_payment_amount = '';
            this.form.initial_payment_type = 'commission';
            this.form.initial_payment_months_paid = '';
            this.form.initial_payment_from_date = '';
            this.form.initial_payment_to_date = '';
            this.form.initial_payment_notes = '';
            this.form.job_title = { ar: '', en: '' };
            this.form.company_id = null;
            this.form.partner_id = null;
            this.form.sales_id = null;
            this.form.governorate_id = null;
            this.form.city_id = null;
            this.form.contract_image = null;
            this.form.contract_image_url = null;
            this.form.contract_image_remove = false;
            this.form.gallery_images = [];
            this.form.gallery_existing = [];
            this.form.gallery_remove_ids = [];
            this.validationErrors = null;
        },

        setMembers(members) {
            this.members = members;
        },

        setMember(member) {
            // Reset form first, then set values to ensure proper reactivity
            this.form.id = member.id;
            this.form.slug = member.slug || '';
            this.form.name = member.name || '';
            this.form.email = member.email || '';
            this.form.phone = member.phone || '';
            this.form.password = '';
            this.form.password_confirmation = '';
            this.form.avatar = null;
            this.form.avatar_url = member.avatar_url || null;
            this.form.membership_number = member.membership?.membership_number || '';
            this.form.national_id = member.membership?.national_id || '';
            this.form.registration_date = member.membership?.registration_date_formatted || member.membership?.registration_date || '';
            this.form.expiration_date = member.membership?.expiration_date_formatted || member.membership?.expiration_date || '';
            // Explicitly set is_active based on membership data - use Boolean() to ensure proper type
            this.form.is_active = Boolean(member.membership?.is_active);
            this.form.is_visible = Boolean(member.membership?.is_visible ?? true);
            this.form.is_paid = Boolean(member.membership?.is_paid);
            this.form.payment_type = member.membership?.payment_type || '';
            this.form.membership_completed_at = member.membership?.completed_at || null;
            this.form.has_member_payments = Boolean(member.membership?.has_member_payments);
            this.form.initial_payment_amount = '';
            this.form.initial_payment_type = 'commission';
            this.form.initial_payment_months_paid = '';
            this.form.initial_payment_from_date = '';
            this.form.initial_payment_to_date = '';
            this.form.initial_payment_notes = '';
            this.form.job_title = member.membership?.job_title || { ar: '', en: '' };
            this.form.company_id = member.membership?.company_id ?? null;
            this.form.partner_id = member.membership?.partner_id ?? null;
            this.form.sales_id = member.membership?.sales_id ?? null;
            this.form.governorate_id = member.membership?.governorate_id ?? null;
            this.form.city_id = member.membership?.city_id ?? null;
            this.form.contract_image = null;
            this.form.contract_image_url = member.membership?.contract_image_url || null;
            this.form.contract_image_remove = false;
            this.form.gallery_images = [];
            this.form.gallery_existing = Array.isArray(member.membership?.gallery_images)
                ? member.membership.gallery_images.map((img) => ({ id: img.id, url: img.url, name: img.name }))
                : [];
            this.form.gallery_remove_ids = [];
        },

        async submitForm(familyMembers = []) {
            this.isLoading = true;
            try {
                console.log('=== Form Submission Started ===');
                console.log('Form data:', {
                    name: this.form.name,
                    email: this.form.email,
                    membership_number: this.form.membership_number,
                    registration_date: this.form.registration_date,
                    expiration_date: this.form.expiration_date,
                    is_active: this.form.is_active,
                    has_avatar: !!this.form.avatar,
                });
                console.log('Family members:', familyMembers);

                // Validate with Zod before submitting
                const validation = validateMemberForm({
                    name: this.form.name,
                    email: this.form.email,
                    phone: this.form.phone,
                    password: '',
                    password_confirmation: '',
                    membership_number: this.form.membership_number,
                    national_id: this.form.national_id,
                    registration_date: this.form.registration_date,
                    expiration_date: this.form.expiration_date,
                    is_active: this.form.is_active,
                    is_visible: this.form.is_visible,
                    is_paid: this.form.is_paid,
                    payment_type: this.form.payment_type,
                    company_id: this.form.company_id,
                    sales_id: this.form.sales_id,
                    governorate_id: this.form.governorate_id,
                    initial_payment_amount: this.form.initial_payment_amount,
                    initial_payment_type: this.form.initial_payment_type,
                    initial_payment_months_paid: this.form.initial_payment_months_paid,
                    initial_payment_from_date: this.form.initial_payment_from_date,
                    initial_payment_to_date: this.form.initial_payment_to_date,
                    initial_payment_notes: this.form.initial_payment_notes,
                },false);

                if (!validation.isValid) {
                    console.error('Validation errors:', validation.errors);
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                // Prepare form data with family members
                const formData = new FormData();
                formData.append('name', this.form.name);
                formData.append('email', this.form.email);
                if (this.form.phone) formData.append('phone', this.form.phone);
                if (this.form.avatar) {
                    formData.append('avatar', this.form.avatar);
                }
                formData.append('membership_number', this.form.membership_number || '');
                formData.append('national_id', this.form.national_id || '');
                formData.append('registration_date', this.form.registration_date || '');
                formData.append('expiration_date', this.form.expiration_date || '');
                formData.append('is_active', this.form.is_active ? '1' : '0');
                formData.append('is_visible', this.form.is_visible ? '1' : '0');
                formData.append('is_paid', this.form.is_paid ? '1' : '0');
                if (this.form.payment_type) formData.append('payment_type', this.form.payment_type);
                if (this.form.is_paid) {
                    formData.append('initial_payment_amount', this.form.initial_payment_amount ?? '');
                    formData.append('initial_payment_type', this.form.initial_payment_type || 'commission');
                    formData.append('initial_payment_months_paid', this.form.initial_payment_months_paid ?? '');
                    formData.append('initial_payment_from_date', this.form.initial_payment_from_date || '');
                    formData.append('initial_payment_to_date', this.form.initial_payment_to_date || '');
                    if (this.form.initial_payment_notes) formData.append('initial_payment_notes', this.form.initial_payment_notes);
                }
                if (this.form.job_title?.ar) formData.append('job_title[ar]', this.form.job_title.ar);
                if (this.form.job_title?.en) formData.append('job_title[en]', this.form.job_title.en);
                if (this.form.company_id) formData.append('company_id', this.form.company_id);
                if (this.form.partner_id) formData.append('partner_id', this.form.partner_id);
                if (this.form.sales_id) formData.append('sales_id', this.form.sales_id);
                if (this.form.governorate_id) formData.append('governorate_id', this.form.governorate_id);
                if (this.form.city_id) formData.append('city_id', this.form.city_id);

                // Contract & gallery images
                if (this.form.contract_image) {
                    formData.append('contract_image', this.form.contract_image);
                }
                if (Array.isArray(this.form.gallery_images)) {
                    this.form.gallery_images.forEach((file) => {
                        formData.append('gallery_images[]', file);
                    });
                }

                // Append family members data
                familyMembers.forEach((member, index) => {
                    formData.append(`family_members[${index}][name]`, member.name);
                    formData.append(`family_members[${index}][relationship]`, member.relationship);
                    if (member.date_of_birth) {
                        formData.append(`family_members[${index}][date_of_birth]`, member.date_of_birth);
                    }
                    if (member.phone) {
                        formData.append(`family_members[${index}][phone]`, member.phone);
                    }
                    if (member.email) {
                        formData.append(`family_members[${index}][email]`, member.email);
                    }
                    formData.append(`family_members[${index}][is_active]`, member.is_active ? '1' : '0');
                    if (member.photo_file) {
                        console.log(`Appending photo file for family member ${index}:`, member.photo_file.name, member.photo_file.type);
                        formData.append(`family_members[${index}][photo]`, member.photo_file);
                    }
                });

                console.log('FormData prepared, submitting...');
                console.log('Route:', route('admin.user.membership.store'));
                console.log('FormData entries count:', Array.from(formData.entries()).length);

                const storeRoute = route('admin.user.membership.store');
                console.log('Posting to:', storeRoute);

                router.post(storeRoute, formData, {
                    preserveScroll: true,
                    forceFormData: true,
                    onBefore: (visit) => {
                        console.log('=== Request starting ===');
                        console.log('Visit details:', visit);
                        return true;
                    },
                    onStart: (visit) => {
                        console.log('=== Request started ===');
                        console.log('Visit:', visit);
                    },
                    onProgress: (progress) => {
                        console.log('=== Request progress ===', progress);
                    },
                    onSuccess: (page) => {
                        console.log('=== Form Submission Success ===');
                        console.log('Page object:', page);
                        console.log('Page URL:', page.url);
                        console.log('Page props:', page.props);
                        console.log('Page component:', page.component);
                        useNotification().success('Member created successfully');
                        this.initializeForm();
                        router.visit(route('admin.user.membership.list'));
                    },
                    onError: (errors) => {
                        console.error('=== Form Submission Error ===');
                        console.error('Error object:', errors);
                        console.error('Error keys:', Object.keys(errors));
                        console.error('Full error details:', JSON.stringify(errors, null, 2));
                        
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to create member');
                    },
                    onFinish: () => {
                        console.log('=== Form Submission Finished ===');
                    }
                });
            } catch (error) {
                console.error('=== Unexpected Error in submitForm ===');
                console.error('Error:', error);
                console.error('Error message:', error.message);
                console.error('Error stack:', error.stack);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async updateMember() {
            this.isLoading = true;
            try {
                // Validate with Zod before submitting
                const validation = validateMemberForm({
                    name: this.form.name,
                    email: this.form.email,
                    phone: this.form.phone,
                    password: this.form.password,
                    password_confirmation: this.form.password_confirmation,
                    membership_number: this.form.membership_number,
                    national_id: this.form.national_id,
                    registration_date: this.form.registration_date,
                    expiration_date: this.form.expiration_date,
                    is_active: this.form.is_active,
                    is_visible: this.form.is_visible,
                    is_paid: this.form.is_paid,
                    payment_type: this.form.payment_type,
                    company_id: this.form.company_id,
                    sales_id: this.form.sales_id,
                    governorate_id: this.form.governorate_id,
                    membership_completed_at: this.form.membership_completed_at,
                    has_member_payments: this.form.has_member_payments,
                    initial_payment_amount: this.form.initial_payment_amount,
                    initial_payment_type: this.form.initial_payment_type,
                    initial_payment_months_paid: this.form.initial_payment_months_paid,
                    initial_payment_from_date: this.form.initial_payment_from_date,
                    initial_payment_to_date: this.form.initial_payment_to_date,
                    initial_payment_notes: this.form.initial_payment_notes,
                },true);

                if (!validation.isValid) {
                    this.validationErrors = validation.errors;
                    useNotification().error('Please fix the validation errors');
                    this.isLoading = false;
                    return;
                }

                this.validationErrors = null;

                // Use PUT method for update
                // The route expects {user} parameter which is the slug
                const userSlug = this.form.slug || this.form.id;
                
                this.form.put(route('admin.user.membership.update', userSlug), {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        useNotification().success('Member updated successfully');
                        this.initializeForm();
                        router.visit(route('admin.user.membership.list'));
                    },
                    onError: (errors) => {
                        // Merge server errors with client validation errors
                        this.validationErrors = { ...this.validationErrors, ...errors };
                        useNotification().error('Failed to update member');
                    }
                });
            } catch (error) {
                console.error('Error updating member:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async deleteMember(slug) {
            this.isLoading = true;
            try {
                await router.delete(route('admin.user.membership.destroy', slug), {
                    preserveScroll: true,
                    onSuccess: () => {
                        useNotification().success('Member deleted successfully');
                        router.reload({ only: ['users'] });
                    },
                    onError: (errors) => {
                        console.error('Delete error:', errors);
                        useNotification().error('Failed to delete member');
                    }
                });
            } catch (error) {
                console.error('Error deleting member:', error);
                useNotification().error('An unexpected error occurred');
            } finally {
                this.isLoading = false;
            }
        },

        async confirmDelete(slug) {
            if (confirm('Are you sure you want to delete this member? They will be moved to trash.')) {
                await this.deleteMember(slug);
            }
        }
    }
});
