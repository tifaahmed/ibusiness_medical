import { ref, computed } from 'vue';
import { z } from 'zod';

/**
 * Vue composable for Zod form validation
 * @param {z.ZodSchema} schema - Zod schema for validation
 * @param {Object} initialValues - Initial form values
 * @returns {Object} Form validation state and methods
 */
export function useZodForm(schema, initialValues = {}) {
  const formData = ref({ ...initialValues });
  const errors = ref({});
  const touched = ref({});
  const isSubmitting = ref(false);

  /**
   * Validate a single field
   * @param {string} fieldName - Name of the field to validate
   */
  const validateField = (fieldName) => {
    try {
      // Create a partial schema for the field
      const fieldSchema = schema.shape[fieldName];
      if (fieldSchema) {
        fieldSchema.parse(formData.value[fieldName]);
        // Remove error if validation passes
        if (errors.value[fieldName]) {
          delete errors.value[fieldName];
        }
      }
    } catch (error) {
      if (error.errors && error.errors.length > 0) {
        errors.value[fieldName] = error.errors[0].message;
      }
    }
  };

  /**
   * Validate all fields
   * @returns {boolean} True if form is valid
   */
  const validate = () => {
    try {
      schema.parse(formData.value);
      errors.value = {};
      return true;
    } catch (error) {
      if (error.errors) {
        errors.value = error.errors.reduce((acc, err) => {
          const path = err.path[0];
          acc[path] = err.message;
          return acc;
        }, {});
      }
      return false;
    }
  };

  /**
   * Mark a field as touched
   * @param {string} fieldName - Name of the field
   */
  const touchField = (fieldName) => {
    touched.value[fieldName] = true;
    validateField(fieldName);
  };

  /**
   * Reset form to initial values
   */
  const reset = () => {
    formData.value = { ...initialValues };
    errors.value = {};
    touched.value = {};
    isSubmitting.value = false;
  };

  /**
   * Set form data
   * @param {Object} data - Form data to set
   */
  const setFormData = (data) => {
    formData.value = { ...formData.value, ...data };
  };

  /**
   * Check if form is valid
   */
  const isValid = computed(() => {
    try {
      schema.parse(formData.value);
      return true;
    } catch {
      return false;
    }
  });

  /**
   * Check if a specific field has an error
   * @param {string} fieldName - Name of the field
   * @returns {string|null} Error message or null
   */
  const getFieldError = (fieldName) => {
    return errors.value[fieldName] || null;
  };

  /**
   * Check if a field has been touched
   * @param {string} fieldName - Name of the field
   * @returns {boolean}
   */
  const isFieldTouched = (fieldName) => {
    return touched.value[fieldName] || false;
  };

  return {
    formData,
    errors,
    touched,
    isSubmitting,
    isValid,
    validate,
    validateField,
    touchField,
    reset,
    setFormData,
    getFieldError,
    isFieldTouched,
  };
}


