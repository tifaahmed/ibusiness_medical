<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <FormInput
        v-model="careerStore.form.name"
        label="Name"
        :error="careerStore.validationErrors?.name"
        placeholder="Enter career name"
        required
      />
      <FormDateInput
        v-model="careerStore.form.application_end_date"
        label="Application End Date"
        :error="careerStore.validationErrors?.application_end_date"
        required
      />
      <FormCheckbox
        v-model="careerStore.form.is_active"
        label="Active Status"
        :error="careerStore.validationErrors?.is_active"
      />
    </div>

    <!-- Description Input -->
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Description
        <span class="text-red-500">*</span>
      </label>
      <TinyMCETextEditor
        id="career-description"
        :modelValue="careerStore.form.description"
        @update:modelValue="(val) => (careerStore.form.description = val)"
        placeholder="Enter career description"
        :error="careerStore.validationErrors?.description"
      />
      <p
        v-if="careerStore.validationErrors?.description"
        class="mt-2 text-sm text-red-600"
      >
        {{ careerStore.validationErrors.description }}
      </p>
    </div>
  </div>
</template>

<script setup>
import {
  FormInput,
  FormDateInput,
  FormCheckbox,
  TinyMCETextEditor,
} from "@/Components/form";
import { useCareerStore } from "@/stores/dashboard/careerStore";

const careerStore = useCareerStore();
</script>

<style lang="scss" scoped></style>
