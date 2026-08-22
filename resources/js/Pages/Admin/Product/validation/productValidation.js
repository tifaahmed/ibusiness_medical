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

export const productSchema = z.object({
    name: translatableNameSchema,
    short_subject: z.object({
        ar: z.string().max(255).optional().default(''),
        en: z.string().max(255).optional().default(''),
    }).optional(),
    description: z.object({
        ar: z.string().max(65535).optional().default(''),
        en: z.string().max(65535).optional().default(''),
    }).optional(),
    old_price: z.coerce.number().min(0).optional().nullable(),
    new_price: z.coerce.number().min(0).optional().nullable(),
    cost_price: z.coerce.number().min(0).optional().nullable(),
    profit_price: z.coerce.number().min(0).optional().nullable(),
    product_type_id: z.coerce.number().int().positive().optional().nullable(),
});

export const productUpdateSchema = productSchema;

export const validateProductForm = (productData, isUpdate = false) => {
    try {
        const schema = isUpdate ? productUpdateSchema : productSchema;

        let nameValue = productData.name || {};
        if (typeof nameValue === 'string') {
            try { nameValue = JSON.parse(nameValue); } catch { nameValue = { ar: nameValue, en: nameValue }; }
        }

        const formData = {
            name: {
                ar: nameValue.ar?.toString() || '',
                en: nameValue.en?.toString() || '',
            },
            short_subject: productData.short_subject || undefined,
            description: productData.description || undefined,
            old_price: productData.old_price ?? undefined,
            new_price: productData.new_price ?? undefined,
            cost_price: productData.cost_price ?? undefined,
            profit_price: productData.profit_price ?? undefined,
            product_type_id: productData.product_type_id ?? undefined,
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
