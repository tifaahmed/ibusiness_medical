<template>
  <div class="space-y-4">
    <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div class="py-2 px-6">
        <div class="title-golden leading-none font-semibold flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
          </svg>
          Company Information
        </div>
      </div>
      <div class="px-6">
        <FormTranslatableInput
          v-model="formName"
          label="Name"
          :error="companyStore.validationErrors?.name"
          placeholder="Enter company name"
          required
          :locales="['ar', 'en']"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput } from "@/Components/form";
import { useCompanyStore } from "../../Stores/CompanyStore";
import { computed } from "vue";
import { storeToRefs } from "pinia";

const companyStore = useCompanyStore();
const { form } = storeToRefs(companyStore);

const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) return {};
    return name;
  },
  set: (value) => { form.value.name = value; },
});
</script>
