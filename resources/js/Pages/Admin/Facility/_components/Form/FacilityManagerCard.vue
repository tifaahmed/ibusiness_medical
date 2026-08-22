<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          {{ t.facility_manager?.title || 'Facility Managers' }}
        </div>
        <button
          v-if="!showAddForm"
          @click="showAddForm = true; editingIndex = null"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.facility_manager?.add_manager || 'Add Manager' }}
        </button>
      </div>
    </div>

    <div class="px-6">
      <!-- Add/Edit Form -->
      <div v-if="showAddForm || editingIndex !== null" class="mb-6 p-4 bg-accent/50 rounded-lg border border-border">
        <h3 class="text-sm font-semibold mb-4 text-white">
          {{ editingIndex !== null ? (t.facility_manager?.edit_manager || 'Edit Manager') : (t.facility_manager?.add_new_manager || 'Add New Manager') }}
        </h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <FormInput
                v-model="form.name"
                :label="t.facility_manager?.name || 'Name'"
                :placeholder="t.facility_manager?.name_placeholder || 'Enter manager name'"
                :error="errors.name"
              />
            </div>
            <div>
              <FormInput
                v-model="form.position"
                :label="t.facility_manager?.position || 'Position'"
                :placeholder="t.facility_manager?.position_placeholder || 'Enter position'"
                :error="errors.position"
              />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-white mb-2">
                {{ t.facility_manager?.phones || 'Phone Numbers' }}
                <span class="text-xs text-white/70 ml-1">{{ t.facility_manager?.phones_help || '(one per line)' }}</span>
              </label>
              <textarea
                v-model="phonesText"
                :class="[
                  'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md min-h-[80px] focus:ring-[3px] focus:ring-ring/50',
                  errors.phones ? 'border-destructive focus:border-destructive focus:ring-destructive/20 dark:focus:ring-destructive/40' : ''
                ]"
                :placeholder="t.facility_manager?.phones_placeholder || 'Enter phone numbers, one per line\nExample:\n+20 123 456 7890\n+20 987 654 3210'"
              ></textarea>
              <p v-if="errors.phones" class="mt-1 text-sm text-destructive">{{ errors.phones }}</p>
            </div>
          </div>
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
              {{ editingIndex !== null ? (t.common?.update || 'Update') : (t.common?.add || 'Add') }} {{ t.facility_manager?.label || 'Manager' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Managers List -->
      <div v-if="modelValue && modelValue.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="(manager, index) in modelValue"
          :key="manager.id || index"
          class="p-4 bg-accent/30 rounded-lg border border-border hover:bg-accent/50 transition-colors"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="mb-2">
                <h4 class="font-semibold text-white mb-1">
                  {{ manager.name || (t.facility_manager?.unnamed || 'Unnamed Manager') }}
                </h4>
                <p v-if="manager.position" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect>
                    <line x1="16" x2="16" y1="2" y2="6"></line>
                    <line x1="8" x2="8" y1="2" y2="6"></line>
                    <line x1="3" x2="21" y1="10" y2="10"></line>
                  </svg>
                  {{ manager.position }}
                </p>
              </div>
              <div v-if="manager.phones && manager.phones.length > 0" class="flex flex-wrap gap-2 text-xs text-white/70">
                <span
                  v-for="(phone, phoneIndex) in manager.phones"
                  :key="phoneIndex"
                  class="inline-flex items-center gap-1"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  {{ phone }}
                </span>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button
                @click="editManager(index)"
                type="button"
                class="p-2 rounded-md hover:bg-accent transition-colors"
                title="Edit"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button
                @click="deleteManager(index)"
                type="button"
                class="p-2 rounded-md hover:bg-destructive/20 transition-colors"
                title="Delete"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-destructive">
                  <polyline points="3 6 5 6 21 6"></polyline>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!showAddForm" class="text-center py-8 text-white">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 opacity-50 text-white/70">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        <p class="text-white">{{ t.facility_manager?.no_managers || 'No managers added yet.' }}</p>
        <p class="text-sm mt-1 text-white/80">{{ t.facility_manager?.add_manager_help || 'Click "Add Manager" to get started.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { FormInput } from '@/Components/form';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

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
  name: '',
  position: '',
  phones: []
});

const phonesText = computed({
  get: () => {
    if (!form.value.phones || !Array.isArray(form.value.phones)) {
      return '';
    }
    return form.value.phones.filter(p => p && p.trim()).join('\n');
  },
  set: (value) => {
    if (!value || !value.trim()) {
      form.value.phones = [];
      return;
    }
    form.value.phones = value
      .split('\n')
      .map(p => p.trim())
      .filter(p => p.length > 0);
  }
});

const resetForm = () => {
  form.value = {
    name: '',
    position: '',
    phones: []
  };
  errors.value = {};
};

const cancelForm = () => {
  showAddForm.value = false;
  editingIndex.value = null;
  resetForm();
};

const editManager = (index) => {
  editingIndex.value = index;
  showAddForm.value = true;
  const manager = props.modelValue[index];

  form.value = {
    name: manager.name || '',
    position: manager.position || '',
    phones: Array.isArray(manager.phones) ? [...manager.phones] : (manager.phones ? [manager.phones] : [])
  };
  errors.value = {};
};

const handleSubmit = () => {
  errors.value = {};

  if (!form.value.name || !form.value.name.trim()) {
    errors.value.name = t.value?.facility_manager?.name_required || 'Manager name is required';
    return;
  }

  const managerData = {
    id: editingIndex.value !== null && props.modelValue[editingIndex.value]?.id
      ? props.modelValue[editingIndex.value].id
      : null,
    name: form.value.name || '',
    position: form.value.position || '',
    phones: form.value.phones && form.value.phones.length > 0 ? form.value.phones : null
  };

  const currentManagers = [...props.modelValue];

  if (editingIndex.value !== null) {
    currentManagers[editingIndex.value] = { ...currentManagers[editingIndex.value], ...managerData };
  } else {
    currentManagers.push(managerData);
  }

  emit('update:modelValue', currentManagers);
  cancelForm();
};

const deleteManager = (index) => {
  if (!confirm(t.value?.facility_manager?.confirm_remove || 'Are you sure you want to remove this manager?')) {
    return;
  }

  const currentManagers = [...props.modelValue];
  currentManagers.splice(index, 1);
  emit('update:modelValue', currentManagers);
};
</script>

<style scoped></style>
