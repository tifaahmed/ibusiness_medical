<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          {{ t.city?.title || 'Cities' }}
          <span class="text-xs text-muted-foreground font-normal">({{ modelValue?.length || 0 }})</span>
        </div>
        <button
          v-if="!showAddForm"
          @click="openAddForm"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.city?.add || 'Add City' }}
        </button>
      </div>
    </div>

    <div class="px-6">
      <!-- Add/Edit Form -->
      <div v-if="showAddForm" class="mb-6 p-4 bg-accent/50 rounded-lg border border-border">
        <h3 class="text-sm font-semibold mb-4 text-white">
          {{ editingIndex !== null ? (t.city?.edit || 'Edit City') : (t.city?.add_new || 'Add New City') }}
        </h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <FormTranslatableInput
            v-model="form.name"
            :label="t.common?.name || 'Name'"
            :error="errors.name"
            :placeholder="t.city?.name_placeholder || 'Enter city name'"
            :locales="['ar', 'en']"
            required
          />
          <div class="flex gap-3 justify-end">
            <button
              type="button"
              @click="cancelForm"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background text-white shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
            >
              {{ t.common?.cancel || 'Cancel' }}
            </button>
            <button
              type="submit"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
            >
              {{ editingIndex !== null ? (t.common?.update || 'Update') : (t.common?.add || 'Add') }}
            </button>
          </div>
        </form>
      </div>

      <!-- Cities Grid -->
      <div v-if="modelValue && modelValue.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
        <div
          v-for="(city, index) in modelValue"
          :key="city.id || `new-${index}`"
          class="p-3 bg-accent/30 rounded-lg border border-border hover:bg-accent/50 transition-colors flex items-center justify-between gap-3"
        >
          <div class="flex-1 min-w-0">
            <h4 class="font-medium text-white truncate" :title="getTranslatedName(city.name)">
              {{ getTranslatedName(city.name) || (t.city?.unnamed || 'Unnamed City') }}
            </h4>
            <p v-if="city.slug" class="text-xs text-white/60 font-mono mt-0.5 truncate">{{ city.slug }}</p>
          </div>
          <div class="flex gap-1 flex-shrink-0">
            <button
              @click="editCity(index)"
              type="button"
              class="p-1.5 rounded-md hover:bg-accent transition-colors"
              :title="t.common?.edit || 'Edit'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </button>
            <button
              @click="deleteCity(index)"
              type="button"
              class="p-1.5 rounded-md hover:bg-destructive/20 transition-colors"
              :title="t.common?.delete || 'Delete'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-destructive">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!showAddForm" class="text-center py-8 text-white">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 opacity-50 text-white/70">
          <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
          <circle cx="12" cy="10" r="3"></circle>
        </svg>
        <p class="text-white">{{ t.city?.no_cities || 'No cities added yet.' }}</p>
        <p class="text-sm mt-1 text-white/80">{{ t.city?.add_help || 'Click "Add City" to get started.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { FormTranslatableInput } from '@/Components/form';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const locale = computed(() => page.props.locale || 'ar');

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue']);

const showAddForm = ref(false);
const editingIndex = ref(null);
const errors = ref({});

const form = ref({
  name: {}
});

const resetForm = () => {
  form.value = { name: {} };
  errors.value = {};
};

const openAddForm = () => {
  resetForm();
  editingIndex.value = null;
  showAddForm.value = true;
};

const cancelForm = () => {
  showAddForm.value = false;
  editingIndex.value = null;
  resetForm();
};

const getTranslatedName = (name) => {
  if (!name) return '';
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const normalizeName = (value) => {
  let nameValue = value || {};
  if (typeof nameValue === 'string') {
    try {
      nameValue = JSON.parse(nameValue);
    } catch {
      nameValue = { ar: nameValue, en: nameValue };
    }
  }
  if (!nameValue || typeof nameValue !== 'object' || Array.isArray(nameValue)) {
    nameValue = {};
  }
  return nameValue;
};

const editCity = (index) => {
  editingIndex.value = index;
  showAddForm.value = true;
  form.value = {
    name: normalizeName(props.modelValue[index]?.name)
  };
  errors.value = {};
};

const handleSubmit = () => {
  errors.value = {};

  const nameObj = form.value.name || {};
  const hasName = Object.keys(nameObj).some(key => nameObj[key] && String(nameObj[key]).trim());
  if (!hasName) {
    errors.value.name = t.value?.city?.name_required || 'City name is required in at least one language';
    return;
  }

  const cityData = {
    id: editingIndex.value !== null && props.modelValue[editingIndex.value]?.id
      ? props.modelValue[editingIndex.value].id
      : null,
    name: form.value.name || {},
    slug: editingIndex.value !== null ? props.modelValue[editingIndex.value]?.slug : null,
  };

  const current = [...props.modelValue];
  if (editingIndex.value !== null) {
    current[editingIndex.value] = { ...current[editingIndex.value], ...cityData };
  } else {
    current.push(cityData);
  }

  emit('update:modelValue', current);
  cancelForm();
};

const deleteCity = (index) => {
  if (!confirm(t.value?.city?.confirm_remove || 'Are you sure you want to remove this city?')) {
    return;
  }
  const current = [...props.modelValue];
  current.splice(index, 1);
  emit('update:modelValue', current);
};
</script>
