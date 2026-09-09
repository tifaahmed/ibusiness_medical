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
              <div v-if="managerPhones(manager).length > 0" class="flex flex-wrap gap-2 text-xs text-white/70">
                <span
                  v-for="(entry, phoneIndex) in managerPhones(manager)"
                  :key="phoneIndex"
                  class="inline-flex items-center gap-1"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  <span dir="ltr">{{ entry.number }}</span>
                  <span class="text-white/50">· {{ typeLabel(entry.type) }}</span>
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
      <div v-else class="text-center py-8 text-white">
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

    <!-- Add / Edit Manager modal -->
    <Teleport to="body">
      <div
        v-if="isFormOpen"
        class="fixed inset-0 z-[110] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
      >
        <div class="my-8 w-full max-w-2xl overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
          <div class="flex items-start gap-3 border-b border-border p-4">
            <h3 class="text-sm font-semibold text-white">
              {{ editingIndex !== null ? (t.facility_manager?.edit_manager || 'Edit Manager') : (t.facility_manager?.add_new_manager || 'Add New Manager') }}
            </h3>
            <button
              type="button"
              class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
              :title="t.common?.close || 'Close (Esc)'"
              @click="requestClose"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
              </svg>
            </button>
          </div>

          <!-- .stop: this form is nested inside the page form; without it the submit
               event bubbles up and triggers a full facility save. -->
          <form @submit.prevent.stop="handleSubmit">
            <div class="max-h-[70vh] overflow-y-auto p-4 space-y-4">
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
                  <BranchPhonesInput
                    v-model="form.phones"
                    :label="t.facility_manager?.phones || 'Phone Numbers'"
                    :hint="t.facility_manager?.phones_help || '(one number per row)'"
                    :errors="errors"
                    error-prefix="phones"
                  />
                </div>
              </div>
            </div>
            <div class="flex gap-3 justify-end border-t border-border p-3">
              <button
                type="button"
                @click="requestClose"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background text-white shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              >
                {{ t.common?.cancel || 'Cancel' }}
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
              >
                <svg v-if="saving" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                  <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                </svg>
                {{ editingIndex !== null ? (t.common?.update || 'Update') : (t.common?.add || 'Add') }} {{ t.facility_manager?.label || 'Manager' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import axios from 'axios';
import { FormInput, BranchPhonesInput } from '@/Components/form';
import { normalizePhoneEntries, phoneTypeLabel } from '@/lib/branchPhones';
import { usePage } from '@inertiajs/vue3';
import { useNotification } from '@/composables/useNotification';

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  // Set on the edit page only: with a facility to attach it to, a manager is
  // saved the moment the modal is submitted instead of waiting for the
  // facility save. Blank on the create page, where there is no facility yet.
  facilitySlug: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue']);

const showAddForm = ref(false);
const editingIndex = ref(null);
const errors = ref({});
const saving = ref(false);

const isFormOpen = computed(() => showAddForm.value || editingIndex.value !== null);

const form = ref({
  name: '',
  position: '',
  phones: []
});

// Phones are stored as { number, type }; rows written before types existed
// still hold flat strings, so everything is read through the shared reader.
const managerPhones = (manager) => normalizePhoneEntries(manager?.phones);

const typeLabel = (type) =>
  t.value.facility_branch?.phone_types?.[type] || phoneTypeLabel(type);

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
  formSnapshot.value = '';
  resetForm();
};

/* ---- leaving the modal --------------------------------------------------

   Nothing typed here is written until "Add"/"Update" is pressed, so a modal
   that closed on a stray click outside it threw the work away silently. It now
   closes only the two deliberate ways — Cancel and the × — and both ask first
   when there is something to lose. Mirrors the branch modal next door.
------------------------------------------------------------------------- */

// The form as it stood when the modal opened. Empty while it is shut.
const formSnapshot = ref('');

const formFingerprint = () => JSON.stringify({
  name: form.value.name || '',
  position: form.value.position || '',
  phones: normalizePhoneEntries(form.value.phones),
});

const formIsDirty = computed(() =>
  isFormOpen.value && formSnapshot.value !== '' && formFingerprint() !== formSnapshot.value
);

const requestClose = () => {
  if (formIsDirty.value) {
    const message = t.value?.facility_manager?.confirm_discard
      || 'This manager has changes that have not been added yet. Leave and lose them?';

    if (!window.confirm(message)) return;

    useNotification().info(
      t.value?.facility_manager?.discarded
      || 'Manager changes discarded — nothing was saved.'
    );
  }

  cancelForm();
};

// After the opener has filled the boxes — editManager flips this flag before it
// copies the manager in — so the snapshot is the row as it arrived.
watch(isFormOpen, (open) => {
  if (open) nextTick(() => { formSnapshot.value = formFingerprint(); });
  else formSnapshot.value = '';
});

const editManager = (index) => {
  editingIndex.value = index;
  showAddForm.value = true;
  const manager = props.modelValue[index];

  form.value = {
    name: manager.name || '',
    position: manager.position || '',
    phones: normalizePhoneEntries(manager.phones)
  };
  errors.value = {};
};

const handleSubmit = async () => {
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

  // Without a facility there is nothing to attach the manager to yet, so it
  // waits in the list until the facility itself is created.
  if (!props.facilitySlug) {
    applyManager(managerData);
    cancelForm();
    return;
  }

  saving.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.manager.save', props.facilitySlug), {
      ...managerData,
      phones: managerData.phones || []
    });

    // The saved row carries the real id, which is what keeps the later
    // facility save from writing a second copy of this manager.
    applyManager(data.manager);
    useNotification().success(
      data.created
        ? (t.value?.facility_manager?.created || 'Manager created successfully')
        : (t.value?.facility_manager?.updated || 'Manager updated successfully')
    );
    cancelForm();
  } catch (error) {
    errors.value = modalErrors(error?.response?.data?.errors);
    useNotification().error(
      error?.response?.data?.message
      || (t.value?.facility_manager?.save_failed || 'Failed to save the manager. Please try again.')
    );
  } finally {
    saving.value = false;
  }
};

// Put a manager — the one just saved, or the local copy on the create page —
// into the list the form holds.
const applyManager = (manager) => {
  const currentManagers = [...props.modelValue];

  if (editingIndex.value !== null) {
    currentManagers[editingIndex.value] = { ...currentManagers[editingIndex.value], ...manager };
  } else {
    currentManagers.push(manager);
  }

  emit('update:modelValue', currentManagers);
};

/* Server-side errors for the manager modal. Laravel reports the phone list per
   row ("phones.0"), while the inputs take one message per field. */
const modalErrors = (responseErrors) => {
  const mapped = {};

  Object.entries(responseErrors || {}).forEach(([key, messages]) => {
    const field = key.split('.')[0];
    const message = Array.isArray(messages) ? messages[0] : messages;

    if (!mapped[field]) mapped[field] = message;
  });

  return mapped;
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
