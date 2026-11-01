<template>
  <div class="space-y-2 sm:space-y-3 md:space-y-4">
    <!-- Store Category Information Card -->
    <div class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div class="py-2 px-3 sm:px-4 md:px-6">
        <div class="title-golden leading-none font-semibold">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6">
            <path d="m7.5 4.27 9 5.15"></path>
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
            <path d="m3.3 7 8.7 5 8.7-5"></path>
            <path d="M12 22V12"></path>
          </svg>
          <span class="text-sm sm:text-base">{{ t.store_category?.information || 'Store Category Information' }}</span>
        </div>
      </div>
      <div class="px-3 sm:px-4 md:px-6">
        <div class="space-y-2 sm:space-y-3 md:space-y-4">
          <FormTranslatableInput
            v-model="formName"
            :label="t.common?.name || 'Name'"
            :error="storeCategoryStore.validationErrors?.name"
            :placeholder="t.store_category?.name_placeholder || 'Enter store category name'"
            required
            :locales="['ar', 'en']"
          />
          <!-- Whichever box is filled is translated into the other one (Arabic in the English box is moved too). Nothing is saved until the form is. -->
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 h-8 text-xs sm:text-sm font-medium hover:bg-muted disabled:opacity-50 min-w-[10rem]"
              :disabled="fixing || (!formName.ar && !formName.en)"
              data-testid="store-category-name-ai-fix"
              @click="fixTranslation"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="fixing ? 'animate-pulse' : ''"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path></svg>
              {{ t.store_category?.translate_fix_name || 'Fix translation with AI' }}
            </button>
            <span v-if="fixError" class="text-xs text-destructive">{{ fixError }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormTranslatableInput } from "@/Components/form";
import { useStoreCategoryStore } from "../../Stores/StoreCategoryStore";
import { computed, ref } from "vue";
import axios from "axios";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const storeCategoryStore = useStoreCategoryStore();
const { form } = storeToRefs(storeCategoryStore);

const fixing = ref(false);
const fixError = ref('');

async function fixTranslation() {
  fixing.value = true;
  fixError.value = '';
  try {
    const { data } = await axios.post(route('admin.store-category.translations.single'), { ar: formName.value.ar || '', en: formName.value.en || '' });
    form.value.name = { ...formName.value, ar: data.ar, en: data.en };
  } catch (err) {
    fixError.value = err?.response?.data?.message || (t.value.store_category?.translate_fix_name_failed || 'The AI could not translate this name.');
  } finally {
    fixing.value = false;
  }
}

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
