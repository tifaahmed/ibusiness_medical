import { z } from 'zod';

const membershipUsageSchema = z.object({
    membership_id: z.union([z.string(), z.number()]).refine(v => v !== '' && v !== null && v !== undefined, {
        message: 'Membership is required',
    }),
    facility_id: z.union([z.string(), z.number()]).refine(v => v !== '' && v !== null && v !== undefined, {
        message: 'Facility is required',
    }),
    facility_branch_id: z.union([z.string(), z.number(), z.null()]).optional(),
    facility_type_id: z.union([z.string(), z.number()]).refine(v => v !== '' && v !== null && v !== undefined, {
        message: 'Facility type is required',
    }),
    amount: z.union([z.string(), z.number()]).refine(v => {
        const n = parseFloat(v);
        return !isNaN(n) && n >= 0;
    }, { message: 'Amount must be a non-negative number' }),
    description: z.string().max(1000).nullable().optional(),
});

export function validateMembershipUsageForm(data) {
    const result = membershipUsageSchema.safeParse(data);

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
