import { z } from "zod";

export const memberSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Name must be less than 255 characters')
        .transform(val => String(val).trim()),
    email: z.string()
        .email('Please enter a valid email address')
        .max(255, 'Email must be less than 255 characters')
        .transform(val => String(val).trim().toLowerCase())
        .optional()
        .or(z.literal('')),
    phone: z.string()
        .min(1, 'Phone is required')
        .max(20, 'Phone must be less than 20 characters'),
    password: z.string()
        .optional()
        .refine((val) => {
            // If password is provided, validate it; otherwise allow empty/undefined
            if (!val || val.length === 0) return true;
            if (val.length < 8) return false;
            if (!/[A-Z]/.test(val)) return false;
            if (!/[a-z]/.test(val)) return false;
            if (!/[0-9]/.test(val)) return false;
            return true;
        }, {
            message: 'Password must be at least 8 characters with uppercase, lowercase, and number'
        })
        .or(z.literal('')),
    password_confirmation: z.string()
        .optional()
        .or(z.literal('')),
    membership_number: z.string()
        .max(255, 'Membership number must be less than 255 characters')
        .optional()
        .or(z.literal(''))
        .transform(val => val ? String(val).trim() : ''),
    national_id: z.string()
        .min(1, 'National ID number is required')
        .regex(/^\d{14}$/, 'National ID number must be exactly 14 digits'),
    registration_date: z.string()
        .min(1, 'Registration date is required')
        .refine((date) => {
            if (!date) return false;
            const selectedDate = new Date(date);
            return !isNaN(selectedDate.getTime());
        }, 'Please enter a valid registration date'),
    expiration_date: z.string()
        .min(1, 'Expiration date is required')
        .refine((date) => {
            if (!date) return false;
            const selectedDate = new Date(date);
            return !isNaN(selectedDate.getTime());
        }, 'Please enter a valid expiration date'),
    is_active: z.boolean()
        .optional()
        .default(true),
    is_visible: z.boolean()
        .optional()
        .default(true),
    is_paid: z.boolean()
        .optional()
        .default(false),
    payment_type: z.string()
        .optional()
        .or(z.literal('')),
    company_id: z.union([z.string(), z.number()]).nullable().optional(),
    sales_id: z.union([z.string(), z.number()]).nullable().optional(),
    governorate_id: z.union([z.string(), z.number()]).nullable().optional(),
    initial_payment_amount: z.union([z.string(), z.number()]).optional().or(z.literal('')),
    initial_payment_type: z.string().optional().or(z.literal('')),
    initial_payment_months_paid: z.union([z.string(), z.number()]).optional().or(z.literal('')),
    initial_payment_from_date: z.string().optional().or(z.literal('')),
    initial_payment_to_date: z.string().optional().or(z.literal('')),
    initial_payment_notes: z.string().max(1000).nullable().optional(),
}).refine((data) => {
    // If password is provided, password_confirmation must match
    if (data.password && data.password.length > 0) {
        return data.password === data.password_confirmation;
    }
    return true;
}, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
}).refine((data) => {
    // Expiration date must be after registration date
    if (data.expiration_date && data.registration_date) {
        const expiration = new Date(data.expiration_date);
        const registration = new Date(data.registration_date);
        return expiration >= registration;
    }
    return true;
}, {
    message: 'Expiration date must be after registration date',
    path: ['expiration_date'],
}).superRefine((data, ctx) => {
    // Company, Sales, and Governorate are required when the
    // membership is paid with a monthly payment type.
    if (!(data.is_paid && data.payment_type === 'monthly')) return;
    if (!data.company_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Company is required for a paid monthly membership', path: ['company_id'] });
    }
    if (!data.sales_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Sales is required for a paid monthly membership', path: ['sales_id'] });
    }
    if (!data.governorate_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Governorate is required for a paid monthly membership', path: ['governorate_id'] });
    }
}).superRefine((data, ctx) => {
    // A paid membership requires its initial payment card to be filled in,
    // mirroring the standalone member-payment create form's requirements.
    if (!data.is_paid) return;
    if (data.initial_payment_type !== 'free') {
        const amount = parseFloat(data.initial_payment_amount);
        if (data.initial_payment_amount === '' || isNaN(amount) || amount < 0) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Payment amount is required', path: ['initial_payment_amount'] });
        }
    }
    const months = parseInt(data.initial_payment_months_paid);
    if (isNaN(months) || months < 1) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Months paid must be at least 1', path: ['initial_payment_months_paid'] });
    }
    if (!data.initial_payment_from_date) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'From date is required', path: ['initial_payment_from_date'] });
    }
    if (!data.initial_payment_to_date) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'To date is required', path: ['initial_payment_to_date'] });
    }
});

// Schema for update (password optional) - created independently to avoid extend() on refined schema
export const memberUpdateSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Name must be less than 255 characters')
        .transform(val => String(val).trim()),
    email: z.string()
        .email('Please enter a valid email address')
        .max(255, 'Email must be less than 255 characters')
        .transform(val => String(val).trim().toLowerCase())
        .optional()
        .or(z.literal('')),
    phone: z.string()
        .min(1, 'Phone is required')
        .max(20, 'Phone must be less than 20 characters'),
    password: z.string()
        .min(8, 'Password must be at least 8 characters')
        .regex(/[A-Z]/, 'Password must contain at least one uppercase letter')
        .regex(/[a-z]/, 'Password must contain at least one lowercase letter')
        .regex(/[0-9]/, 'Password must contain at least one number')
        .optional()
        .or(z.literal('')),
    password_confirmation: z.string()
        .optional()
        .or(z.literal('')),
    membership_number: z.string()
        .max(255, 'Membership number must be less than 255 characters')
        .optional()
        .or(z.literal(''))
        .transform(val => val ? String(val).trim() : ''),
    national_id: z.string()
        .min(1, 'National ID number is required')
        .regex(/^\d{14}$/, 'National ID number must be exactly 14 digits'),
    registration_date: z.string()
        .optional()
        .or(z.literal(''))
        .refine((date) => {
            if (!date) return true;
            const selectedDate = new Date(date);
            return !isNaN(selectedDate.getTime());
        }, 'Please enter a valid registration date'),
    expiration_date: z.string()
        .optional()
        .or(z.literal(''))
        .refine((date) => {
            if (!date) return true;
            const selectedDate = new Date(date);
            return !isNaN(selectedDate.getTime());
        }, 'Please enter a valid expiration date'),
    is_active: z.boolean()
        .optional()
        .default(true),
    is_visible: z.boolean()
        .optional()
        .default(true),
    is_paid: z.boolean()
        .optional()
        .default(false),
    payment_type: z.string()
        .optional()
        .or(z.literal('')),
    company_id: z.union([z.string(), z.number()]).nullable().optional(),
    sales_id: z.union([z.string(), z.number()]).nullable().optional(),
    governorate_id: z.union([z.string(), z.number()]).nullable().optional(),
    membership_completed_at: z.string().nullable().optional(),
    has_member_payments: z.boolean().optional().default(false),
    initial_payment_amount: z.union([z.string(), z.number()]).optional().or(z.literal('')),
    initial_payment_type: z.string().optional().or(z.literal('')),
    initial_payment_months_paid: z.union([z.string(), z.number()]).optional().or(z.literal('')),
    initial_payment_from_date: z.string().optional().or(z.literal('')),
    initial_payment_to_date: z.string().optional().or(z.literal('')),
    initial_payment_notes: z.string().max(1000).nullable().optional(),
}).refine((data) => {
    // If password is provided, password_confirmation must match
    if (data.password && data.password.length > 0) {
        return data.password === data.password_confirmation;
    }
    return true;
}, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
}).refine((data) => {
    // Expiration date must be after registration date
    if (data.expiration_date && data.registration_date) {
        const expiration = new Date(data.expiration_date);
        const registration = new Date(data.registration_date);
        return expiration >= registration;
    }
    return true;
}, {
    message: 'Expiration date must be after registration date',
    path: ['expiration_date'],
}).superRefine((data, ctx) => {
    // Company, Sales, and Governorate are required when the
    // membership is paid with a monthly payment type.
    if (!(data.is_paid && data.payment_type === 'monthly')) return;
    if (!data.company_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Company is required for a paid monthly membership', path: ['company_id'] });
    }
    if (!data.sales_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Sales is required for a paid monthly membership', path: ['sales_id'] });
    }
    if (!data.governorate_id) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Governorate is required for a paid monthly membership', path: ['governorate_id'] });
    }
}).superRefine((data, ctx) => {
    // The first member-payment row is required when the admin is marking an
    // incomplete, never-paid membership as paid — mirrors the edit form's
    // Payment card, which only appears under these same conditions.
    const requiresInitialPayment = data.is_paid && !data.membership_completed_at && !data.has_member_payments;
    if (!requiresInitialPayment) return;
    if (data.initial_payment_type !== 'free') {
        const amount = parseFloat(data.initial_payment_amount);
        if (data.initial_payment_amount === '' || isNaN(amount) || amount < 0) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Payment amount is required', path: ['initial_payment_amount'] });
        }
    }
    const months = parseInt(data.initial_payment_months_paid);
    if (isNaN(months) || months < 1) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'Months paid must be at least 1', path: ['initial_payment_months_paid'] });
    }
    if (!data.initial_payment_from_date) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'From date is required', path: ['initial_payment_from_date'] });
    }
    if (!data.initial_payment_to_date) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, message: 'To date is required', path: ['initial_payment_to_date'] });
    }
});

export const validateMemberForm = (memberData, isUpdate = false) => {
    try {
        const schema = isUpdate ? memberUpdateSchema : memberSchema;

        const formData = {
            name: memberData.name?.toString() || '',
            email: memberData.email?.toString() || '',
            phone: memberData.phone?.toString() || '',
            // For create mode, password is optional (auto-generated by backend)
            password: isUpdate ? (memberData.password?.toString() || '') : '',
            password_confirmation: isUpdate ? (memberData.password_confirmation?.toString() || '') : '',
            membership_number: memberData.membership_number?.toString() || '',
            national_id: memberData.national_id?.toString() || '',
            registration_date: memberData.registration_date?.toString() || '',
            expiration_date: memberData.expiration_date?.toString() || '',
            is_active: typeof memberData.is_active === 'boolean' ? memberData.is_active : (memberData.is_active === 'true' || memberData.is_active === true || memberData.is_active === 1),
            is_visible: typeof memberData.is_visible === 'boolean' ? memberData.is_visible : (memberData.is_visible === 'true' || memberData.is_visible === true || memberData.is_visible === 1),
            is_paid: typeof memberData.is_paid === 'boolean' ? memberData.is_paid : (memberData.is_paid === 'true' || memberData.is_paid === true || memberData.is_paid === 1),
            payment_type: memberData.payment_type?.toString() || '',
            company_id: memberData.company_id ?? null,
            sales_id: memberData.sales_id ?? null,
            governorate_id: memberData.governorate_id ?? null,
            membership_completed_at: memberData.membership_completed_at ?? null,
            has_member_payments: Boolean(memberData.has_member_payments),
            initial_payment_amount: memberData.initial_payment_amount ?? '',
            initial_payment_type: memberData.initial_payment_type?.toString() || '',
            initial_payment_months_paid: memberData.initial_payment_months_paid ?? '',
            initial_payment_from_date: memberData.initial_payment_from_date?.toString() || '',
            initial_payment_to_date: memberData.initial_payment_to_date?.toString() || '',
            initial_payment_notes: memberData.initial_payment_notes ?? null,
        };

        schema.parse(formData);
        return { isValid: true, errors: null };
    } catch (err) {
        console.error('Validation error:', err);
        
        // Handle Zod validation errors
        if (err.issues && Array.isArray(err.issues)) {
            const errors = err.issues.reduce((acc, error) => {
                const path = error.path && error.path.length > 0 ? error.path[0] : 'general';
                acc[path] = error.message;
                return acc;
            }, {});
            return { isValid: false, errors };
        }
        
        // Fallback for other error types
        if (err.errors && Array.isArray(err.errors)) {
            const errors = err.errors.reduce((acc, error) => {
                const path = error.path && error.path.length > 0 ? error.path[0] : 'general';
                acc[path] = error.message;
                return acc;
            }, {});
            return { isValid: false, errors };
        }
        
        return { isValid: false, errors: { general: err.message || 'Validation failed' } };
    }
};

