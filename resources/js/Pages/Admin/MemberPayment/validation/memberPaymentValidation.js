import { z } from 'zod';

const memberPaymentSchema = z.object({
    membership_id: z.union([z.string(), z.number()]).refine(v => v !== '' && v !== null && v !== undefined, {
        message: 'Membership is required',
    }),
    amount: z.union([z.string(), z.number()]).refine(v => {
        const n = parseFloat(v);
        return !isNaN(n) && n >= 0;
    }, { message: 'Amount must be a non-negative number' }),
    type: z.string().optional(),
    months_paid: z.union([z.string(), z.number()]).refine(v => {
        const n = parseInt(v);
        return !isNaN(n) && n >= 1;
    }, { message: 'Months paid must be at least 1' }),
    from_date: z.string().min(1, 'From date is required'),
    to_date: z.string().min(1, 'To date is required'),
    notes: z.string().max(1000).nullable().optional(),
});

export function validateMemberPaymentForm(data) {
    const result = memberPaymentSchema.safeParse(data);

    if (result.success) {
        return { isValid: true, errors: null };
    }

    const errors = {};
    result.error.errors.forEach(err => {
        const field = err.path.join('.');
        errors[field] = err.message;
    });

    return { isValid: false, errors };
}
