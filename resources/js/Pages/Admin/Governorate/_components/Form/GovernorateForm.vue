<template>
  <div class="space-y-2 sm:space-y-3 md:space-y-4">
    <!-- Governorate Information Card -->
    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <span class="text-sm sm:text-base">{{ t.governorate?.information || 'Governorate Information' }}</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6">
        <div class="space-y-2 sm:space-y-3 md:space-y-4">
          <FormTranslatableInput
            v-model="formName"
            :label="t.common?.name || 'Name'"
            :error="governorateStore.validationErrors?.name"
            :placeholder="t.governorate?.name_placeholder || 'Enter governorate name'"
            required
            :locales="['ar', 'en']"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput } from "@/Components/form";
import { useGovernorateStore } from "../../Stores/GovernorateStore";
import { computed, watch } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const governorateStore = useGovernorateStore();
const { form } = storeToRefs(governorateStore);

// Ensure we always have a valid name object for the form
const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) {
      return {};
    }
    return name;
  },
  set: (value) => {
    form.value.name = value;
  }
});
</script>

<style lang="scss" scoped></style>

