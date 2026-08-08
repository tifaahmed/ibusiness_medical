import { z } from "zod";

// Schema for translatable name field
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
    (data) => {
        return data.ar && data.en;
    },
    {
        message: 'Both Arabic and English names are required',
    }
);

export const governorateSchema = z.object({
    name: translatableNameSchema,
});

export const governorateUpdateSchema = z.object({
    name: translatableNameSchema,
});

export const validateGovernorateForm = (governorateData, isUpdate = false) => {
    try {
        const schema = isUpdate ? governorateUpdateSchema : governorateSchema;
        
        // Ensure name is an object
        let nameValue = governorateData.name || {};
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
        };

        schema.parse(formData);
        return { isValid: true, errors: null };
    } catch (err) {
        if (err.errors) {
            const errors = err.errors.reduce((acc, error) => {
                // Handle nested path like "name.ar" or "name.en"
                const path = error.path.join('.');
                acc[path] = error.message;
                return acc;
            }, {});
            return { isValid: false, errors };
        }
        return { isValid: false, errors: { general: 'Validation failed' } };
    }
};



