import { z } from "zod";

// Schema for translatable name field (optional)
const translatableNameSchema = z.object({
    ar: z.string()
        .max(255, 'Name (Arabic) must be less than 255 characters')
        .transform(val => val ? String(val).trim() : '')
        .optional()
        .or(z.literal('')),
    en: z.string()
        .max(255, 'Name (English) must be less than 255 characters')
        .transform(val => val ? String(val).trim() : '')
        .optional()
        .or(z.literal('')),
});

// Schema for translatable address field (optional)
const translatableAddressSchema = z.object({
    ar: z.string()
        .max(65535, 'Address (Arabic) is too long')
        .transform(val => val ? String(val).trim() : '')
        .optional()
        .or(z.literal('')),
    en: z.string()
        .max(65535, 'Address (English) is too long')
        .transform(val => val ? String(val).trim() : '')
        .optional()
        .or(z.literal('')),
});

export const facilityBranchSchema = z.object({
    name: translatableNameSchema,
    address: translatableAddressSchema,
    phone: z.array(z.string().max(50, 'Each phone number must be less than 50 characters'))
        .optional()
        .or(z.literal(null))
        .transform(val => {
            if (!val || !Array.isArray(val)) return [];
            return val.map(p => String(p).trim()).filter(p => p.length > 0);
        }),
    facility_id: z.string()
        .min(1, 'Facility is required')
        .transform(val => String(val)),
    governorate_id: z.string().optional().or(z.literal('')),
    city_id: z.string().optional().or(z.literal('')),
    latitude: z.union([z.string(), z.number()])
        .optional()
        .transform(val => {
            if (val === '' || val === null || val === undefined) return null;
            const n = Number(val);
            return Number.isFinite(n) ? n : null;
        })
        .refine(val => val === null || (val >= -90 && val <= 90), {
            message: 'Latitude must be between -90 and 90',
        }),
    longitude: z.union([z.string(), z.number()])
        .optional()
        .transform(val => {
            if (val === '' || val === null || val === undefined) return null;
            const n = Number(val);
            return Number.isFinite(n) ? n : null;
        })
        .refine(val => val === null || (val >= -180 && val <= 180), {
            message: 'Longitude must be between -180 and 180',
        }),
});

export const facilityBranchUpdateSchema = z.object({
    name: translatableNameSchema,
    address: translatableAddressSchema,
    phone: z.array(z.string().max(50, 'Each phone number must be less than 50 characters'))
        .optional()
        .or(z.literal(null))
        .transform(val => {
            if (!val || !Array.isArray(val)) return [];
            return val.map(p => String(p).trim()).filter(p => p.length > 0);
        }),
    facility_id: z.string()
        .min(1, 'Facility is required')
        .transform(val => String(val)),
    governorate_id: z.string().optional().or(z.literal('')),
    city_id: z.string().optional().or(z.literal('')),
    latitude: z.union([z.string(), z.number()])
        .optional()
        .transform(val => {
            if (val === '' || val === null || val === undefined) return null;
            const n = Number(val);
            return Number.isFinite(n) ? n : null;
        })
        .refine(val => val === null || (val >= -90 && val <= 90), {
            message: 'Latitude must be between -90 and 90',
        }),
    longitude: z.union([z.string(), z.number()])
        .optional()
        .transform(val => {
            if (val === '' || val === null || val === undefined) return null;
            const n = Number(val);
            return Number.isFinite(n) ? n : null;
        })
        .refine(val => val === null || (val >= -180 && val <= 180), {
            message: 'Longitude must be between -180 and 180',
        }),
});

export const validateFacilityBranchForm = (facilityBranchData, isUpdate = false) => {
    try {
        const schema = isUpdate ? facilityBranchUpdateSchema : facilityBranchSchema;
        
        // Ensure translatable fields are objects
        let nameValue = facilityBranchData.name || {};
        if (typeof nameValue === 'string') {
            try {
                nameValue = JSON.parse(nameValue);
            } catch {
                nameValue = { ar: nameValue, en: nameValue };
            }
        }

        let addressValue = facilityBranchData.address || {};
        if (typeof addressValue === 'string') {
            try {
                addressValue = JSON.parse(addressValue);
            } catch {
                addressValue = { ar: addressValue, en: addressValue };
            }
        }

        // Handle phone - can be array, string, or null
        let phoneValue = facilityBranchData.phone || [];
        if (typeof phoneValue === 'string' && phoneValue.trim() !== '') {
            phoneValue = [phoneValue.trim()];
        } else if (!Array.isArray(phoneValue)) {
            phoneValue = [];
        } else {
            phoneValue = phoneValue.map(p => String(p).trim()).filter(p => p.length > 0);
        }

        const formData = {
            name: {
                ar: nameValue.ar?.toString() || '',
                en: nameValue.en?.toString() || '',
            },
            address: {
                ar: addressValue.ar?.toString() || '',
                en: addressValue.en?.toString() || '',
            },
            phone: phoneValue,
            facility_id: facilityBranchData.facility_id?.toString() || '',
            governorate_id: facilityBranchData.governorate_id ? String(facilityBranchData.governorate_id) : '',
            city_id: facilityBranchData.city_id ? String(facilityBranchData.city_id) : '',
            latitude: facilityBranchData.latitude ?? '',
            longitude: facilityBranchData.longitude ?? '',
        };

        schema.parse(formData);
        return { isValid: true, errors: null };
    } catch (err) {
        if (err.errors) {
            const errors = err.errors.reduce((acc, error) => {
                // Handle nested path like "name.ar" or "address.en"
                const path = error.path.join('.');
                acc[path] = error.message;
                return acc;
            }, {});
            return { isValid: false, errors };
        }
        return { isValid: false, errors: { general: 'Validation failed' } };
    }
};


