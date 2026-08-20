<template>
  <div class="space-y-2 sm:space-y-3 md:space-y-4">
    <!-- Product Type Information Card -->
    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <path d="m7.5 4.27 9 5.15"></path>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
            <path d="m3.3 7 8.7 5 8.7-5"></path>
            <path d="M12 22V12"></path>
          </svg>
          <span class="text-sm sm:text-base">{{ t.product_type?.information || 'Product Type Information' }}</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6">
        <div class="space-y-2 sm:space-y-3 md:space-y-4">
          <FormTranslatableInput
            v-model="formName"
            :label="t.common?.name || 'Name'"
            :error="productTypeStore.validationErrors?.name"
            :placeholder="t.product_type?.name_placeholder || 'Enter product type name'"
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
import { useProductTypeStore } from "../../Stores/ProductTypeStore";
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const productTypeStore = useProductTypeStore();
const { form } = storeToRefs(productTypeStore);

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
