import { z } from "zod";

const translatableNameSchema = z.object({
    ar: z.string()
        .min(1, 'Name (Arabic) is required')
        .max(255, 'Name (Arabic) must be less than 255 characters')
        .transform(val => String(val).trim()),
    en: z.string()
        .min(1, 'Name (English) is required')
        .max(255, 'Name (English) must be less than 255 characters')
        .transform(val => String(val).trim()),
}).refine(
    (data) => data.ar && data.en,
    { message: 'Both Arabic and English names are required' }
);

const translatableDescriptionSchema = z.object({
    ar: z.string().max(1000, 'Description (Arabic) must be less than 1000 characters').optional().default(''),
    en: z.string().max(1000, 'Description (English) must be less than 1000 characters').optional().default(''),
});

export const facilitySchema = z.object({
    name: translatableNameSchema,
    description: translatableDescriptionSchema.optional(),
    facility_type_id: z.string()
        .min(1, 'Facility type is required')
        .transform(val => String(val)),
});

export const facilityUpdateSchema = z.object({
    name: translatableNameSchema,
    description: translatableDescriptionSchema.optional(),
    facility_type_id: z.string()
        .min(1, 'Facility type is required')
        .transform(val => String(val)),
});

export const validateFacilityForm = (facilityData, isUpdate = false) => {
    try {
        const schema = isUpdate ? facilityUpdateSchema : facilitySchema;

        let nameValue = facilityData.name || {};
        if (typeof nameValue === 'string') {
            try {
                nameValue = JSON.parse(nameValue);
            } catch {
                nameValue = { ar: nameValue, en: nameValue };
            }
        }

        const formData = {
            name: {
                ar: nameValue.ar?.toString() || '',
                en: nameValue.en?.toString() || '',
            },
            facility_type_id: facilityData.facility_type_id?.toString() || '',
        };

        schema.parse(formData);
        return { isValid: true, errors: null };
    } catch (err) {
        if (err.errors) {
            const errors = err.errors.reduce((acc, error) => {
                const path = error.path.join('.');
                acc[path] = error.message;
                return acc;
            }, {});
            return { isValid: false, errors };
        }
        return { isValid: false, errors: { general: 'Validation failed' } };
    }
};
