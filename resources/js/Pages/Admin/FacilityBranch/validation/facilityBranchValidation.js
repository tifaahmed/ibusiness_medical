import { z } from "zod";
import { normalizePhoneEntries } from '@/lib/branchPhones';

/* The branch name is asked for in at least one language — the same rule the
   facility form's branch modal applies, and the one the server enforces. */
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
}).refine(
    value => String(value.ar || '').trim() !== '' || String(value.en || '').trim() !== '',
    { message: 'Branch name is required in at least one language', path: ['ar'] },
);

/* The address, unlike the name, is required in BOTH languages: a branch listed
   in one language only shows up blank on the other side of the directory, and
   the address is what the AI geocoder reads to place the branch on the map. */
const translatableAddressSchema = z.object({
    ar: z.string()
        .min(1, 'Address (Arabic) is required')
        .max(65535, 'Address (Arabic) is too long')
        .transform(val => String(val).trim()),
    en: z.string()
        .min(1, 'Address (English) is required')
        .max(65535, 'Address (English) is too long')
        .transform(val => String(val).trim()),
}).refine(
    value => String(value.ar || '').trim() !== '',
    { message: 'Address (Arabic) is required', path: ['ar'] },
).refine(
    value => String(value.en || '').trim() !== '',
    { message: 'Address (English) is required', path: ['en'] },
);

export const facilityBranchSchema = z.object({
    name: translatableNameSchema,
    address: translatableAddressSchema,
    // One entry per number, each carrying the kind of line it is. Flat strings
    // from rows written before types are accepted and typed on the way through.
    phone: z.any()
        .optional()
        .transform(val => normalizePhoneEntries(val)),
    facility_id: z.string()
        .min(1, 'Facility is required')
        .transform(val => String(val)),
    // A branch without a place on the map is what makes the directory
    // unusable, so both are asked for here rather than left for later.
    governorate_id: z.string().min(1, 'Governorate is required'),
    city_id: z.string().min(1, 'City is required'),
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
    google_location_url: z.string()
        .optional()
        .or(z.literal(''))
        .transform(val => {
            if (!val || !val.trim()) return null;
            return val.trim();
        })
        .refine(val => val === null || /^https?:\/\/.+/.test(val), {
            message: 'Please enter a valid URL',
        }),
});

export const facilityBranchUpdateSchema = z.object({
    name: translatableNameSchema,
    address: translatableAddressSchema,
    // One entry per number, each carrying the kind of line it is. Flat strings
    // from rows written before types are accepted and typed on the way through.
    phone: z.any()
        .optional()
        .transform(val => normalizePhoneEntries(val)),
    facility_id: z.string()
        .min(1, 'Facility is required')
        .transform(val => String(val)),
    // A branch without a place on the map is what makes the directory
    // unusable, so both are asked for here rather than left for later.
    governorate_id: z.string().min(1, 'Governorate is required'),
    city_id: z.string().min(1, 'City is required'),
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
    google_location_url: z.string()
        .optional()
        .or(z.literal(''))
        .transform(val => {
            if (!val || !val.trim()) return null;
            return val.trim();
        })
        .refine(val => val === null || /^https?:\/\/.+/.test(val), {
            message: 'Please enter a valid URL',
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

        // Phones: { number, type } entries, whatever shape they arrived in.
        const phoneValue = normalizePhoneEntries(facilityBranchData.phone);

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
            google_location_url: facilityBranchData.google_location_url ?? '',
        };

        schema.parse(formData);
        return { isValid: true, errors: null };
    } catch (err) {
        // zod 4 carries the list on `issues`; `errors` was its zod 3 name and is
        // gone, which used to turn every failure into one nameless message with
        // nothing marked on the form.
        const issues = err?.issues || err?.errors;

        if (Array.isArray(issues)) {
            const errors = issues.reduce((acc, issue) => {
                // Nested paths like "name.ar" or "address.en" are kept whole, so
                // the field that failed is the field that lights up.
                const path = issue.path.join('.');
                if (!acc[path]) acc[path] = issue.message;
                return acc;
            }, {});
            return { isValid: false, errors };
        }
        return { isValid: false, errors: { general: 'Validation failed' } };
    }
};


