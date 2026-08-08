<template>
  <div class="space-y-3">
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] !items-start gap-1.5 py-2 px-6 [.border-b]:pb-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            <path d="M9 7a1 1 0 11-2 0 1 1 0 012 0z"/>
          </svg>
          {{ t.tag?.information || 'Tag Information' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6 space-y-4">
        <FormInput
          v-model="formName"
          :label="t.tag?.name || 'Name'"
          :error="tagStore.validationErrors?.name"
          :placeholder="t.tag?.name_placeholder || 'Enter tag name'"
          required
        />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <FormSelect
            v-model="formIcon"
            :label="t.tag?.icon || 'Icon'"
            :error="tagStore.validationErrors?.icon"
            :options="iconOptions"
            :placeholder="t.tag?.icon_placeholder || 'Select icon'"
          />
          <FormSelect
            v-model="formColor"
            :label="t.tag?.color || 'Color'"
            :error="tagStore.validationErrors?.color"
            :options="colorOptions"
            :placeholder="t.tag?.color_placeholder || 'Select color'"
          />
        </div>

        <div v-if="formName || formIcon || formColor" class="flex items-center gap-2 pt-1">
          <span class="text-xs font-medium text-muted-foreground">{{ t.tag?.preview || 'Preview' }}:</span>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
            :style="previewStyle"
          >
            <span v-if="formIcon">{{ formIcon }}</span>
            {{ formName || (t.tag?.name || 'Name') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormSelect } from "@/Components/form";
import { useTagStore } from "../Stores/TagStore";
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  tag: {
    type: Object,
    default: () => null
  },
  iconOptions: {
    type: Array,
    default: () => []
  },
  colorOptions: {
    type: Array,
    default: () => []
  }
});

const tagStore = useTagStore();
const { form } = storeToRefs(tagStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const formName = computed({
  get: () => form.value.name ?? '',
  set: (value) => { form.value.name = value; },
});

const formIcon = computed({
  get: () => form.value.icon ?? '',
  set: (value) => { form.value.icon = value; },
});

const formColor = computed({
  get: () => form.value.color ?? '',
  set: (value) => { form.value.color = value; },
});

const previewStyle = computed(() => {
  const color = formColor.value || '#6B7280';
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
});
</script>

<style lang="scss" scoped></style>
