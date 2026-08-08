import { z } from "zod";

const getMessage = (messages, key, fallback) => messages?.[key] || fallback;

const createOfferSchema = (messages = {}) => {
    // Schema for translatable title field (Arabic required)
    const translatableTitleSchema = z.object({
        ar: z.string()
            .min(1, getMessage(messages, 'title_ar_required', 'Arabic title is required'))
            .max(255, getMessage(messages, 'title_ar_max', 'Title (Arabic) must be less than 255 characters'))
            .transform(val => val ? String(val).trim() : ''),
        en: z.string()
            .max(255, getMessage(messages, 'title_en_max', 'Title (English) must be less than 255 characters'))
            .transform(val => val ? String(val).trim() : '')
            .optional()
            .or(z.literal('')),
    });

    // Schema for translatable short description (optional)
    const translatableShortDescSchema = z.object({
        ar: z.string()
            .max(500, getMessage(messages, 'short_description_ar_max', 'Short description (Arabic) must be less than 500 characters'))
            .transform(val => val ? String(val).trim() : '')
            .optional()
            .or(z.literal('')),
        en: z.string()
            .max(500, getMessage(messages, 'short_description_en_max', 'Short description (English) must be less than 500 characters'))
            .transform(val => val ? String(val).trim() : '')
            .optional()
            .or(z.literal('')),
    });

    // Schema for translatable full description (optional)
    const translatableFullDescSchema = z.object({
        ar: z.string()
            .transform(val => val ? String(val).trim() : '')
            .optional()
            .or(z.literal('')),
        en: z.string()
            .transform(val => val ? String(val).trim() : '')
            .optional()
            .or(z.literal('')),
    });

    return z.object({
        title: translatableTitleSchema,
        short_description: translatableShortDescSchema,
        full_description: translatableFullDescSchema,
        phone: z.string()
            .max(50, getMessage(messages, 'phone_max', 'Phone must be less than 50 characters'))
            .optional()
            .or(z.literal(''))
            .or(z.null())
            .transform(val => val || ''),
        price: z.union([
            z.number().min(0, getMessage(messages, 'price_min', 'Price must be at least 0')),
            z.string().transform(val => val === '' || val === null ? null : Number(val)),
            z.null(),
        ]).optional()
            .transform(val => {
                if (val === '' || val === null || val === undefined) return null;
                const num = Number(val);
                return isNaN(num) ? null : num;
            }),
        old_price: z.union([
            z.number().min(0, getMessage(messages, 'old_price_min', 'Old price must be at least 0')),
            z.string().transform(val => val === '' || val === null ? null : Number(val)),
            z.null(),
        ]).optional()
            .transform(val => {
                if (val === '' || val === null || val === undefined) return null;
                const num = Number(val);
                return isNaN(num) ? null : num;
            }),
        offerable_type: z.string()
            .min(1, getMessage(messages, 'type_required', 'Offer type is required'))
            .refine(val => ['App\\Models\\Facility', 'App\\Models\\FacilityBranch'].includes(val), {
                message: getMessage(messages, 'type_invalid', 'Invalid offer type')
            }),
        offerable_id: z.union([
            z.string().min(1, getMessage(messages, 'offerable_required', 'Facility or branch is required')),
            z.number().min(1, getMessage(messages, 'offerable_required', 'Facility or branch is required')),
        ]).transform(val => String(val)),
    });
};

export const offerSchema = createOfferSchema();
export const offerUpdateSchema = offerSchema;

export const validateOfferForm = (offerData, isUpdate = false, messages = {}) => {
    try {
        const schema = createOfferSchema(messages);

        // Ensure translatable fields are objects
        let titleValue = offerData.title || {};
        if (typeof titleValue === 'string') {
            try {
                titleValue = JSON.parse(titleValue);
            } catch {
                titleValue = { ar: titleValue, en: '' };
            }
        }

        let shortDescValue = offerData.short_description || {};
        if (typeof shortDescValue === 'string') {
            try {
                shortDescValue = JSON.parse(shortDescValue);
            } catch {
                shortDescValue = { ar: shortDescValue, en: '' };
            }
        }

        let fullDescValue = offerData.full_description || {};
        if (typeof fullDescValue === 'string') {
            try {
                fullDescValue = JSON.parse(fullDescValue);
            } catch {
                fullDescValue = { ar: fullDescValue, en: '' };
            }
        }

        const formData = {
            title: {
                ar: titleValue.ar?.toString() || '',
                en: titleValue.en?.toString() || '',
            },
            short_description: {
                ar: shortDescValue.ar?.toString() || '',
                en: shortDescValue.en?.toString() || '',
            },
            full_description: {
                ar: fullDescValue.ar?.toString() || '',
                en: fullDescValue.en?.toString() || '',
            },
            phone: offerData.phone?.toString() || '',
            price: offerData.price === '' || offerData.price === null ? null : offerData.price,
            old_price: offerData.old_price === '' || offerData.old_price === null ? null : offerData.old_price,
            offerable_type: offerData.offerable_type?.toString() || '',
            offerable_id: offerData.offerable_id?.toString() || '',
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
        return { isValid: false, errors: { general: getMessage(messages, 'failed', 'Validation failed') } };
    }
};
