import { z } from "zod";

const translatableNameSchema = z.object({
    ar: z.string().min(1, 'Name (Arabic) is required').max(255),
    en: z.string().min(1, 'Name (English) is required').max(255),
});

const companySchema = z.object({ name: translatableNameSchema });

export const validateCompanyForm = (data, isUpdate = false) => {
    try {
        let nameValue = data.name || {};
        if (typeof nameValue === 'string') {
            try { nameValue = JSON.parse(nameValue); } catch { nameValue = { ar: nameValue, en: nameValue }; }
        }
        companySchema.parse({ name: { ar: nameValue.ar?.toString() || '', en: nameValue.en?.toString() || '' } });
        return { isValid: true, errors: null };
    } catch (err) {
        if (err.errors) {
            const errors = err.errors.reduce((acc, e) => { acc[e.path.join('.')] = e.message; return acc; }, {});
            return { isValid: false, errors };
        }
        return { isValid: false, errors: { general: 'Validation failed' } };
    }
};
