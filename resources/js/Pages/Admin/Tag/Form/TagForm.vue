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
        <FormTranslatableInput
          v-model="formName"
          :label="t.tag?.name || 'Name'"
          :error="nameErrors"
          required
          :locales="['ar', 'en']"
        />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <div>
            <FormSelect
              v-model="formIcon"
              :label="t.tag?.icon || 'Icon'"
              :error="tagStore.validationErrors?.icon"
              :options="decoratedIconOptions"
              :placeholder="t.tag?.icon_placeholder || 'Select icon'"
            />
            <button
              type="button"
              @click="showIconGrid = !showIconGrid"
              class="mt-1 text-xs text-primary hover:underline cursor-pointer"
            >
              {{ showIconGrid
                ? (t.tag?.hide_all_icons || 'Hide all icons')
                : (t.tag?.browse_all_icons || 'Browse all icons') }}
            </button>
          </div>
          <FormSelect
            v-model="formColor"
            :label="t.tag?.color || 'Color'"
            :error="tagStore.validationErrors?.color"
            :options="colorOptions"
            :placeholder="t.tag?.color_placeholder || 'Select color'"
          />
        </div>

        <!-- Same value as the icon dropdown, laid out as a radio grid for browsing. -->
        <div v-if="showIconGrid" class="rounded-lg border border-border p-3 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-between">
            <input
              v-model="iconSearch"
              type="search"
              :placeholder="t.tag?.icon_search_placeholder || 'Filter icons...'"
              class="w-full sm:max-w-xs py-1.5 px-3 text-sm border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50"
            />
            <button
              v-if="formIcon"
              type="button"
              @click="formIcon = ''"
              class="text-xs text-muted-foreground hover:text-foreground cursor-pointer self-start sm:self-auto"
            >
              {{ t.common?.clear || 'Clear' }}
            </button>
          </div>

          <div
            v-if="filteredIconOptions.length"
            role="radiogroup"
            :aria-label="t.tag?.icon || 'Icon'"
            class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 gap-2 max-h-80 overflow-y-auto pr-1"
          >
            <label
              v-for="option in filteredIconOptions"
              :key="option.value"
              class="relative flex flex-col items-center justify-center gap-1 rounded-md border p-2 cursor-pointer transition-colors text-center"
              :class="formIcon === option.value
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border bg-background text-muted-foreground hover:bg-muted'"
              :style="tileStyle(option)"
              :title="tileTitle(option)"
            >
              <input
                type="radio"
                name="tag-icon"
                :value="option.value"
                v-model="formIcon"
                class="sr-only"
              />
              <span class="text-xl leading-none">{{ option.value }}</span>
              <!-- The name this icon was given on a previous tag, when it has one. -->
              <span
                class="text-[10px] leading-tight break-words"
                :style="usageFor(option) && formIcon !== option.value ? { color: usageFor(option).color || '#6B7280' } : null"
              >
                {{ tileLabel(option) }}
              </span>
            </label>
          </div>
          <p v-else class="text-xs text-muted-foreground">
            {{ t.tag?.no_icons_found || 'No icons match that search.' }}
          </p>
        </div>

        <div v-if="previewName || formIcon || formColor" class="flex items-center gap-2 pt-1">
          <span class="text-xs font-medium text-muted-foreground">{{ t.tag?.preview || 'Preview' }}:</span>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
            :style="previewStyle"
          >
            <span v-if="formIcon">{{ formIcon }}</span>
            {{ previewName || (t.tag?.name || 'Name') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormSelect, FormTranslatableInput } from "@/Components/form";
import { useTagStore } from "../Stores/TagStore";
import { computed, ref } from "vue";
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
  },
  // [{ icon, color, name }] — how each already used icon looked before.
  iconUsages: {
    type: Array,
    default: () => []
  }
});

const tagStore = useTagStore();
const { form } = storeToRefs(tagStore);
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const locale = computed(() => page.props.locale || 'ar');

// The name is a map of locale to text; the input needs a real object even
// when the form has not been filled in yet.
const formName = computed({
  get: () => {
    const name = form.value.name;
    if (!name || typeof name !== 'object' || Array.isArray(name)) {
      return {};
    }
    return name;
  },
  set: (value) => { form.value.name = value; },
});

// Laravel reports "name.ar" / "name.en"; the input wants them keyed by locale.
const nameErrors = computed(() => {
  const errors = tagStore.validationErrors || {};
  const perLocale = {};

  for (const code of ['ar', 'en']) {
    if (errors[`name.${code}`]) {
      perLocale[code] = errors[`name.${code}`];
    }
  }

  return Object.keys(perLocale).length ? perLocale : (errors.name || null);
});

const previewName = computed(() => formName.value[locale.value] || formName.value.ar || formName.value.en || '');

const formIcon = computed({
  get: () => form.value.icon ?? '',
  set: (value) => { form.value.icon = value; },
});

const formColor = computed({
  get: () => form.value.color ?? '',
  set: (value) => { form.value.color = value; },
});

const showIconGrid = ref(false);
const iconSearch = ref('');

// Labels arrive as "<emoji> <name>"; the grid tiles show the emoji separately.
const iconName = (option) => (option.label || '').replace(option.value, '').trim() || option.value;

// Previously used icons, keyed by the emoji itself.
const usageByIcon = computed(() => {
  const map = new Map();
  for (const usage of props.iconUsages || []) {
    if (usage?.icon) map.set(usage.icon, usage);
  }
  return map;
});

const usageFor = (option) => usageByIcon.value.get(option.value) || null;

// The name a previous tag gave this icon, in the current locale when possible.
const usageName = (usage) => {
  if (!usage) return '';
  const name = usage.name;
  if (typeof name === 'string') return name;
  return name?.[locale.value] || name?.en || name?.ar || Object.values(name || {})[0] || '';
};

// Dropdown labels gain the previous name, e.g. "🔥 Fire — Hot Deals".
const decoratedIconOptions = computed(() => props.iconOptions.map((option) => {
  const previous = usageName(usageFor(option));
  if (!previous) return option;
  return { ...option, label: `${option.label} — ${previous}` };
}));

// Tiles keep the generic label unless a previous tag already named this icon.
const tileLabel = (option) => {
  const previous = usageName(usageFor(option));
  return previous || iconName(option);
};

const tileTitle = (option) => {
  const previous = usageName(usageFor(option));
  if (!previous) return option.label;
  return `${option.label} — ${t.value?.used_before_as || 'Used before as'} "${previous}"`;
};

// Used-before tiles are tinted with the color their tag wore.
const tileStyle = (option) => {
  if (formIcon.value === option.value) return null;
  const usage = usageFor(option);
  if (!usage?.color) return null;
  return {
    borderColor: `${usage.color}66`,
    backgroundColor: `${usage.color}14`,
  };
};

const filteredIconOptions = computed(() => {
  const term = iconSearch.value.trim().toLowerCase();
  if (!term) return props.iconOptions;
  // Search matches the stock label, the emoji, and any previous tag name.
  return props.iconOptions.filter((option) => {
    const haystacks = [
      option.label || '',
      usageName(usageFor(option)),
    ];
    return haystacks.some((text) => text.toLowerCase().includes(term)) || option.value === term;
  });
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
