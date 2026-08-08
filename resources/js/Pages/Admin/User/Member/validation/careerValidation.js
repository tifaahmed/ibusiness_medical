import { z } from "zod";

export const careerSchema = z.object({
    name: z.string()
        .min(1, 'Name is required')
        .max(255, 'Name must be less than 255 characters')
        .transform(val => String(val).trim()),
    description: z.string()
        .min(1, 'Description is required')
        .max(65535, 'Description is too long')
        .transform(val => String(val).trim()),
    application_end_date: z.string()
        .min(1, 'Application end date is required')
        .refine((date) => {
            const selectedDate = new Date(date);
            const today = new Date();
            return selectedDate >= today;
        }, 'Application end date must be in the future')
        .transform(val => String(val)),
    is_active: z.boolean({
        required_error: "Active status is required",
        invalid_type_error: "Active status must be true or false"
    })
});

export const validateCareerForm = (careerData) => {
    try {
        // Log the incoming data to verify values
        console.log('Validating career data:', careerData);

        const formData = {
            name: careerData.name?.toString() || '',
            description: careerData.description?.toString() || '',
            application_end_date: careerData.application_end_date?.toString() || '',
            is_active: careerData.is_active === undefined ? false : Boolean(careerData.is_active) // Default to false if undefined
        };

        // Log the transformed data
        console.log('Transformed form data:', formData);

        console.log('Form data before validation:', formData);
        careerSchema.parse(formData);
        return { isValid: true, errors: null };
    } catch (err) {
        console.error('Validation error:', err);
        const errors = err.errors.reduce((acc, error) => {
            acc[error.path[0]] = error.message;
            return acc;
        }, {});
        return { isValid: false, errors };
    }
};
