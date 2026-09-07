<template>
  <div class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
    <div class="py-2 px-6">
      <div class="title-golden leading-none font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          {{ t.facility_branch?.title || 'Facility Branches' }}
        </div>
        <button
          @click="openAddForm"
          type="button"
          class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.facility_branch?.add_branch || 'Add Branch' }}
        </button>
      </div>
    </div>

    <div class="px-6">
      <!-- Branches List -->
      <div v-if="modelValue && modelValue.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="(branch, index) in modelValue"
          :key="branch.id || index"
          class="p-4 bg-accent/30 rounded-lg border-2 transition-colors hover:bg-accent/50"
          :class="statusBorderClass(branchStatus(branch))"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
              <div class="mb-2">
                <div class="flex items-center gap-2 mb-1">
                  <h4 class="font-semibold text-white">
                    {{ getTranslatedName(branch.name) || (t.facility_branch?.unnamed_branch || 'Unnamed Branch') }}
                  </h4>
                  <span
                    v-if="branchStatus(branch) !== 'unchanged'"
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                    :class="statusBadgeClass(branchStatus(branch))"
                  >
                    {{ branchStatus(branch) === 'added'
                      ? (t.common?.new || 'New')
                      : (t.common?.edited || 'Edited') }}
                  </span>
                </div>
                <p v-if="getTranslatedName(branch.address)" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                  {{ getTranslatedName(branch.address) }}
                </p>
                <p v-if="branchLocation(branch)" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <path d="M3 21h18"></path>
                    <path d="M5 21V7l8-4v18"></path>
                    <path d="M19 21V11l-6-4"></path>
                  </svg>
                  {{ branchLocation(branch) }}
                </p>
                <p v-if="branch.google_location_url" class="text-sm mb-2">
                  <a
                    :href="branch.google_location_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-primary hover:underline"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                      <polyline points="15 3 21 3 21 9"></polyline>
                      <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    {{ t.facility_branch?.view_on_maps || 'Open in Google Maps' }}
                  </a>
                </p>
                <p v-if="hasCoordinates(branch)" class="text-sm text-white/80 mb-2">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1 text-white/50">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 2v3"></path>
                    <path d="M12 19v3"></path>
                    <path d="M2 12h3"></path>
                    <path d="M19 12h3"></path>
                  </svg>
                  {{ Number(branch.latitude) }}, {{ Number(branch.longitude) }}
                </p>
              </div>
              <p v-if="branch.created_by_name" class="mb-2 text-xs text-white/60">
                {{ t.common?.created_by || 'Created By' }}: {{ branch.created_by_name }}
                <span v-if="branch.created_at">· {{ branch.created_at }}</span>
              </p>
              <ul v-if="serverErrors[index]" class="mb-2 space-y-1 text-sm text-destructive">
                <li v-for="(message, messageIndex) in serverErrors[index]" :key="messageIndex">{{ message }}</li>
              </ul>
              <div v-if="branchPhones(branch).length > 0" class="flex flex-wrap gap-2 text-xs text-white/70">
                <span
                  v-for="(phone, phoneIndex) in branchPhones(branch)"
                  :key="phoneIndex"
                  class="inline-flex items-center gap-1"
                  :title="phoneLabel(phone.type)"
                >
                  <!-- WhatsApp numbers get the WhatsApp mark so the two kinds
                       of line are told apart at a glance. -->
                  <svg v-if="isWhatsappPhone(phone.type)" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-emerald-400">
                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm5.8 14.1c-.25.69-1.44 1.32-1.98 1.36-.53.05-1.02.24-3.44-.72-2.9-1.14-4.74-4.1-4.88-4.29-.14-.19-1.16-1.55-1.16-2.95 0-1.4.73-2.09.99-2.38.26-.29.57-.36.76-.36h.55c.18 0 .42-.07.65.5.25.6.83 2.07.9 2.22.07.14.12.31.02.5-.1.19-.15.31-.29.48-.14.17-.3.38-.43.51-.14.14-.29.29-.12.57.16.29.73 1.2 1.56 1.95 1.07.95 1.97 1.25 2.25 1.39.29.14.45.12.62-.07.17-.19.71-.83.9-1.12.19-.29.38-.24.64-.14.26.09 1.66.78 1.94.93.29.14.48.21.55.33.07.12.07.69-.18 1.38z"/>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white/50">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                  <span dir="ltr">{{ phone.number }}</span>
                  <span class="text-white/40">· {{ phoneLabel(phone.type) }}</span>
                </span>
              </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
              <button
                @click="editBranch(index)"
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
                @click="deleteBranch(index)"
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
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <p class="text-white">{{ t.facility_branch?.no_branches || 'No branches added yet.' }}</p>
        <p class="text-sm mt-1 text-white/80">{{ t.facility_branch?.add_branch_help || 'Click "Add Branch" to get started.' }}</p>
      </div>
    </div>

    <!-- Add / Edit Branch modal -->
    <Teleport to="body">
      <div
        v-if="isFormOpen"
        class="fixed inset-0 z-[110] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        @click.self="cancelForm"
      >
        <div class="my-8 w-full max-w-3xl overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
          <div class="flex items-start gap-3 border-b border-border p-4">
            <h3 class="text-sm font-semibold text-white">
              {{ editingIndex !== null ? (t.facility_branch?.edit_branch || 'Edit Branch') : (t.facility_branch?.add_new_branch || 'Add New Branch') }}
            </h3>
            <!-- Shown even when it cannot run: a button that quietly disappears
                 reads as a missing feature, while a disabled one with its
                 reason attached reads as something to go and switch on. -->
            <button
              v-if="translateHint"
              type="button"
              class="ml-auto inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-border text-[11px] font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground"
              :title="translateHint"
              :aria-label="translateHint"
              @click="showTranslateHint = !showTranslateHint"
            >
              i
            </button>
            <span v-if="translateHint && showTranslateHint" class="max-w-[16rem] text-[11px] text-amber-300">
              {{ translateHint }}
            </span>
            <button
              type="button"
              :disabled="!canTranslate || translating"
              :class="[
                'inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none',
                translateHint ? '' : 'ml-auto'
              ]"
              :title="translateHint || (t.facility?.english_fix_branch_hint || 'Translate the Arabic name and address into the English boxes')"
              @click="fixEnglish"
            >
              <svg v-if="translating" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
              </svg>
              {{ translating
                ? (t.facility?.english_fixing || 'Fixing English…')
                : (t.facility?.english_fix_branch || 'Fix English with AI') }}
            </button>
            <button
              type="button"
              class="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
              :title="t.common?.close || 'Close (Esc)'"
              @click="cancelForm"
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
              <!-- Read-only: the branch's creator is recorded on save, never edited here. -->
              <p v-if="editingCreator" class="text-xs text-muted-foreground">
                {{ t.common?.created_by || 'Created By' }}: <span class="text-white/80">{{ editingCreator }}</span>
              </p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                  <FormTranslatableInput
                    v-model="form.name"
                    :label="t.facility_branch?.branch_name || 'Branch Name'"
                    :error="errors.name"
                    :placeholder="t.facility_branch?.branch_name_placeholder || 'Enter branch name'"
                    :locales="['ar', 'en']"
                  />
                  <!-- Branch names are usually "<facility> - <city>", so the city
                       is one click rather than retyped. One button per language,
                       each appending that language's spelling of the city. -->
                  <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button
                      v-for="locale in ['ar', 'en']"
                      :key="locale"
                      type="button"
                      :disabled="!canAddCity(locale)"
                      class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
                      :title="addCityHint(locale)"
                      @click="addCityToName(locale)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"></path><path d="M12 5v14"></path>
                      </svg>
                      {{ (t.facility_branch?.add_city_to_name || 'Add city to name') }} ({{ locale.toUpperCase() }})
                    </button>
                    <span v-if="addCityHint('ar') && addCityHint('ar') === addCityHint('en')" class="text-[11px] text-white/70">
                      {{ addCityHint('ar') }}
                    </span>
                  </div>
                </div>
                <div class="md:col-span-2">
                  <FormTranslatableInput
                    v-model="form.address"
                    :label="t.common?.address || 'Address'"
                    :error="errors.address"
                    :placeholder="t.facility_branch?.address_placeholder || 'Enter branch address'"
                    :locales="['ar', 'en']"
                    multiline
                    :rows="3"
                  />
                </div>
                <div>
                  <FormSelect
                    v-model="form.governorate_id"
                    required
                    :label="t.governorate?.label || 'Governorate'"
                    :options="governorateOptions"
                    :error="errors.governorate_id"
                    :placeholder="t.governorate?.select || 'Select a governorate'"
                  />
                </div>
                <div>
                  <FormSelect
                    v-model="form.city_id"
                    required
                    :label="t.city?.label || 'City'"
                    :options="cityOptions"
                    :error="errors.city_id"
                    :placeholder="t.city?.select || 'Select a city'"
                  />
                </div>
                <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2">
                  <p v-if="locateHint" class="text-[11px] text-white/70 order-2 sm:order-1">{{ locateHint }}</p>
                  <button
                    v-if="aiEnabled"
                    type="button"
                    :disabled="!canLocate || locating"
                    class="order-1 sm:order-2 inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium transition hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
                    :title="locateHint || (t.facility?.location_generate_hint || 'Read the address above and fill in the coordinates and the Google Maps link')"
                    @click="locate"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                      <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    {{ locating ? (t.facility?.location_generating || 'Locating…') : (t.facility?.location_generate || 'Find on map with AI') }}
                  </button>
                </div>
                <div>
                  <FormInput
                    v-model="form.latitude"
                    :label="t.facility?.latitude || 'Latitude'"
                    type="number"
                    step="any"
                    :placeholder="t.facility?.latitude_placeholder || 'e.g. 30.0444'"
                    :error="errors.latitude"
                  />
                </div>
                <div>
                  <FormInput
                    v-model="form.longitude"
                    :label="t.facility?.longitude || 'Longitude'"
                    type="number"
                    step="any"
                    :placeholder="t.facility?.longitude_placeholder || 'e.g. 31.2357'"
                    :error="errors.longitude"
                  />
                </div>
                <div class="md:col-span-2">
                  <FormInput
                    v-model="form.google_location_url"
                    :label="t.facility_branch?.google_location_url || 'Google Location URL'"
                    type="url"
                    :placeholder="t.facility_branch?.google_location_url_placeholder || 'https://maps.app.goo.gl/...'"
                    :error="errors.google_location_url"
                  />
                </div>
                <div class="md:col-span-2">
                  <BranchPhonesInput
                    v-model="form.phone"
                    :label="t.facility_branch?.phone_numbers || 'Phone Numbers'"
                    :hint="t.facility_branch?.phone_help || '(one number per row)'"
                    :errors="errors"
                  />
                </div>
              </div>
            </div>
            <div class="flex gap-3 justify-end border-t border-border p-3">
              <button
                type="button"
                @click="cancelForm"
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
                {{ editingIndex !== null ? (t.common?.update || 'Update') : (t.common?.add || 'Add') }} {{ t.facility_branch?.label || 'Branch' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { FormTranslatableInput, FormSelect, FormInput, BranchPhonesInput } from '@/Components/form';
import { usePage } from '@inertiajs/vue3';
import { useFacilityStore } from '../../Stores/FacilityStore';
import { useNotification } from '@/composables/useNotification';
import { normalizePhoneEntries, phoneTypeLabel, isWhatsapp } from '@/lib/branchPhones';

const facilityStore = useFacilityStore();
const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const locale = computed(() => page.props.locale || 'ar');

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  cities: {
    type: Array,
    default: () => []
  },
  // Set on the edit page only: with a facility to attach it to, a branch is
  // saved the moment the modal is submitted instead of waiting for the
  // facility save. Blank on the create page, where there is no facility yet.
  facilitySlug: {
    type: String,
    default: ''
  },
  // False when GEMINI_API_KEY is unset — the "Find on map with AI" button is
  // hidden rather than offered and then refused by the route behind it.
  aiEnabled: {
    type: Boolean,
    default: false
  },
  // Same gate for the modal's "Fix English with AI" button.
  englishFixEnabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const showAddForm = ref(false);
const editingIndex = ref(null);
const errors = ref({});
const saving = ref(false);
const translating = ref(false);
const showTranslateHint = ref(false);

const isFormOpen = computed(() => showAddForm.value || editingIndex.value !== null);

const form = ref({
  name: {},
  address: {},
  phone: [],
  governorate_id: '',
  city_id: '',
  latitude: '',
  longitude: '',
  google_location_url: ''
});

/* ---- change tracking -------------------------------------------------------
   Snapshot the branches as the server last sent them so the list can outline
   which ones the admin has touched. The baseline is keyed by branch id and is
   re-taken whenever a fresh facility payload arrives (e.g. after "Save & stay").
--------------------------------------------------------------------------- */
const baseline = ref(new Map());

const sortedObject = (value) => {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return value ?? null;
  return Object.keys(value).sort().reduce((acc, key) => {
    acc[key] = value[key];
    return acc;
  }, {});
};

const numeric = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

const branchFingerprint = (branch) => JSON.stringify({
  name: sortedObject(branch.name),
  address: sortedObject(branch.address),
  phone: Array.isArray(branch.phone) ? branch.phone : (branch.phone ? [branch.phone] : []),
  governorate_id: numeric(branch.governorate_id),
  city_id: numeric(branch.city_id),
  latitude: numeric(branch.latitude),
  longitude: numeric(branch.longitude),
  google_location_url: branch.google_location_url || null
});

const captureBaseline = () => {
  const map = new Map();
  (props.modelValue || []).forEach((branch) => {
    if (branch.id != null) {
      map.set(branch.id, branchFingerprint(branch));
    }
  });
  baseline.value = map;
};

const branchStatus = (branch) => {
  if (branch.id == null || !baseline.value.has(branch.id)) return 'added';
  return baseline.value.get(branch.id) === branchFingerprint(branch) ? 'unchanged' : 'changed';
};

const statusBorderClass = (status) => ({
  added: 'border-emerald-500',
  changed: 'border-amber-500',
  unchanged: 'border-border'
}[status]);

const statusBadgeClass = (status) => ({
  added: 'bg-emerald-500/15 text-emerald-400',
  changed: 'bg-amber-500/15 text-amber-400'
}[status]);

// A new facility payload from the server (fresh object identity) resets the
// baseline; edits made in the browser keep the current baseline so they stay
// highlighted until the next save. nextTick lets the parent push the fresh
// branches down as our modelValue first.
watch(() => page.props.facility, () => nextTick(captureBaseline));

const optionLabel = (name) => {
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return name;
};

const governorateOptions = computed(() =>
  props.governorates.map(governorate => ({
    value: governorate.id,
    label: optionLabel(governorate.name)
  }))
);

const cityOptions = computed(() => {
  const selectedGov = form.value.governorate_id;
  return props.cities
    .filter(city => !selectedGov || String(city.governorate_id) === String(selectedGov))
    .map(city => ({
      value: city.id,
      label: optionLabel(city.name)
    }));
});

// Phones are stored as { number, type }; rows written before types existed
// still hold flat strings, so everything is read through the shared reader.
const branchPhones = (branch) => normalizePhoneEntries(branch?.phone);

const phoneLabel = (type) => t.value.facility_branch?.phone_types?.[type] || phoneTypeLabel(type);

const isWhatsappPhone = (type) => isWhatsapp(type);

/* ---- AI location ----------------------------------------------------------
   The address the admin has typed goes to the server, which asks the model to
   geocode it and builds the Google Maps link from the coordinates it returns.
   Nothing is saved here — the values land in the open form and are stored with
   the branch, so the admin can check the pin and correct it first. This is the
   same shape as "Generate SEO with AI" on the SEO tab.
--------------------------------------------------------------------------- */
const locating = ref(false);

const filledText = (value) => {
  if (typeof value === 'string') return value.trim() !== '';
  if (value && typeof value === 'object') {
    return Object.values(value).some(entry => typeof entry === 'string' && entry.trim() !== '');
  }
  return false;
};

// An address is what the model actually searches on; a governorate or city
// alone would only ever produce a centre-of-town pin.
const hasAddress = computed(() => filledText(form.value.address));

const canLocate = computed(() => props.aiEnabled && hasAddress.value);

const locateHint = computed(() => {
  if (!props.aiEnabled) return '';
  if (!hasAddress.value) return t.value.facility?.location_needs_address || 'Enter the branch address first.';
  return '';
});

const optionLabelFor = (options, id) => {
  if (!id) return '';
  const match = options.find(option => String(option.value) === String(id));
  return match ? match.label : '';
};

const locate = async () => {
  if (!canLocate.value || locating.value) return;

  locating.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.branch.locate'), {
      address: form.value.address || {},
      name: form.value.name || {},
      facility_name: facilityStore.form?.name || {},
      governorate: optionLabelFor(governorateOptions.value, form.value.governorate_id) || null,
      city: optionLabelFor(cityOptions.value, form.value.city_id) || null,
    });

    const location = data?.location;
    if (!location) throw new Error('empty');

    form.value.latitude = location.latitude;
    form.value.longitude = location.longitude;
    form.value.google_location_url = location.google_location_url;

    // The AI wrote into fields the server may have flagged before — clear those.
    ['latitude', 'longitude', 'google_location_url'].forEach((field) => {
      delete errors.value[field];
    });

    const place = location.matched_place ? ` (${location.matched_place})` : '';
    useNotification().success(
      (t.value.facility?.location_generated || 'Location filled in. Open the map link to check the pin before saving.') + place
    );
  } catch (error) {
    const message = error?.response?.data?.message
      || error?.response?.data?.errors?.address?.[0]
      || (t.value.facility?.location_generate_failed || 'Could not find the location. Please try again.');
    useNotification().error(message);
  } finally {
    locating.value = false;
  }
};

/* The Arabic side is the source the AI translates from, so there is nothing to
   do until one of the two Arabic boxes has something in it. */
const canTranslate = computed(() =>
  props.englishFixEnabled
  && (
    String(form.value.name?.ar || '').trim() !== ''
    || String(form.value.address?.ar || '').trim() !== ''
  )
);

const translateHint = computed(() => {
  if (!props.englishFixEnabled) {
    return t.value.facility?.english_fix_disabled
      || 'AI is not configured on this server: set GEMINI_API_KEY in the .env file, then restart, to enable this.';
  }
  if (translating.value) return '';
  if (!canTranslate.value) {
    return t.value.facility?.english_fix_needs_arabic
      || 'Fill in the Arabic name or address first.';
  }
  return '';
});

// Fills the English name/address from the Arabic ones as they stand in the
// form — the facility-wide button works on saved rows, this one does not need
// the branch to exist yet.
const fixEnglish = async () => {
  if (!canTranslate.value || translating.value) return;

  translating.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.branch.translate'), {
      name: { ar: form.value.name?.ar || '' },
      address: { ar: form.value.address?.ar || '' },
      facility_name: optionLabel(facilityStore.form?.name || {}) || null,
      governorate: optionLabelFor(governorateOptions.value, form.value.governorate_id) || null,
      city: optionLabelFor(cityOptions.value, form.value.city_id) || null,
    });

    const values = data?.values || {};
    const filled = [];

    ['name', 'address'].forEach((field) => {
      if (!values[field]) return;
      form.value[field] = { ...(form.value[field] || {}), en: values[field] };
      delete errors.value[field];
      filled.push(field);
    });

    if (filled.length === 0) throw new Error('empty');

    useNotification().success(
      t.value.facility?.english_fix_branch_done
      || 'English filled in. Check it before saving.'
    );
  } catch (error) {
    useNotification().error(
      error?.response?.data?.message
      || (t.value.facility?.english_fix_failed || 'Could not fix the English fields. Please try again.')
    );
  } finally {
    translating.value = false;
  }
};

/* "Add city to name" — a branch is nearly always "<facility> - <city>", and
   typing that out in both languages is the same edit every time. */
const selectedCity = computed(() =>
  props.cities.find(city => String(city.id) === String(form.value.city_id)) || null
);

const cityNameIn = (locale) => {
  const name = selectedCity.value?.name;
  if (!name) return '';
  if (typeof name === 'object') return String(name[locale] || '').trim();

  // A city stored as a plain string has one spelling; offer it for whichever
  // side it reads as, rather than guessing it must be the Arabic one.
  const plain = String(name).trim();
  const isArabic = /[\u0600-\u06FF]/.test(plain);

  return (locale === 'ar') === isArabic ? plain : '';
};

// A city already written into this language's name is not appended a second
// time — the button for that language goes dead instead.
const canAddCity = (locale) => {
  const city = cityNameIn(locale);
  if (city === '') return false;

  return !String(form.value.name?.[locale] || '').toLowerCase().includes(city.toLowerCase());
};

const addCityHint = (locale) => {
  if (!form.value.city_id) return t.value.city?.select || 'Select a city first';
  if (cityNameIn(locale) === '') {
    return t.value.facility_branch?.city_missing_locale
      || `This city has no ${locale.toUpperCase()} name`;
  }
  if (!canAddCity(locale)) {
    return t.value.facility_branch?.city_already_in_name || 'The city is already in the name';
  }
  return '';
};

const addCityToName = (locale) => {
  if (!canAddCity(locale)) return;

  const city = cityNameIn(locale);
  const current = String(form.value.name?.[locale] || '').trim();

  form.value.name = {
    ...(form.value.name || {}),
    [locale]: current === '' ? city : `${current} - ${city}`,
  };

  delete errors.value.name;
};

// Clear the selected city when it no longer belongs to the chosen governorate
watch(() => form.value.governorate_id, (newGov, oldGov) => {
  if (oldGov !== undefined && newGov !== oldGov) {
    const stillValid = props.cities.some(
      c => String(c.id) === String(form.value.city_id) && String(c.governorate_id) === String(newGov)
    );
    if (!stillValid) {
      form.value.city_id = '';
    }
  }
});

const resetForm = () => {
  form.value = {
    name: {},
    address: {},
    phone: [],
    governorate_id: '',
    city_id: '',
    latitude: '',
    longitude: '',
    google_location_url: ''
  };
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

const onKeydown = (event) => {
  if (event.key === 'Escape' && isFormOpen.value) {
    cancelForm();
  }
};

watch(isFormOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});

onMounted(() => {
  captureBaseline();
  window.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});

// Creator of the branch open in the modal; new branches have none yet.
const editingCreator = computed(() =>
  (editingIndex.value !== null ? props.modelValue[editingIndex.value]?.created_by_name : null) || null
);

/* Server-side branch errors arrive flattened ("branches.2.name.ar"). The modal
   is closed by then, so they are shown on the card they belong to. */
const serverErrors = computed(() => {
  const byIndex = {};

  Object.entries(facilityStore.validationErrors || {}).forEach(([key, message]) => {
    const match = key.match(/^branches\.(\d+)\./);
    if (!match) return;

    const index = Number(match[1]);
    byIndex[index] = byIndex[index] || [];
    byIndex[index].push(Array.isArray(message) ? message[0] : message);
  });

  return byIndex;
});

const getTranslatedName = (name) => {
  if (!name) return '';
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const getGovernorateName = (id) => {
  if (id === null || id === undefined || id === '') return '';
  const gov = props.governorates.find(g => String(g.id) === String(id));
  return gov ? optionLabel(gov.name) : '';
};

const getCityName = (id) => {
  if (id === null || id === undefined || id === '') return '';
  const city = props.cities.find(c => String(c.id) === String(id));
  return city ? optionLabel(city.name) : '';
};

// "City, Governorate" for a saved branch (omits whichever is missing)
const branchLocation = (branch) => {
  return [getCityName(branch.city_id), getGovernorateName(branch.governorate_id)]
    .filter(Boolean)
    .join('، ');
};

const hasCoordinates = (branch) => {
  const valid = (v) => v !== null && v !== undefined && v !== '';
  return valid(branch.latitude) && valid(branch.longitude);
};

const editBranch = (index) => {
  editingIndex.value = index;
  showAddForm.value = true;
  const branch = props.modelValue[index];

  // Ensure name and address are objects
  let nameValue = branch.name || {};
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

  let addressValue = branch.address || {};
  if (typeof addressValue === 'string') {
    try {
      addressValue = JSON.parse(addressValue);
    } catch {
      addressValue = { ar: addressValue, en: addressValue };
    }
  }
  if (!addressValue || typeof addressValue !== 'object' || Array.isArray(addressValue)) {
    addressValue = {};
  }

  form.value = {
    name: nameValue,
    address: addressValue,
    phone: Array.isArray(branch.phone) ? branch.phone : (branch.phone ? [branch.phone] : []),
    governorate_id: branch.governorate_id ?? '',
    city_id: branch.city_id ?? '',
    latitude: branch.latitude ?? '',
    longitude: branch.longitude ?? '',
    google_location_url: branch.google_location_url ?? ''
  };
  errors.value = {};
};

/* ---- uniqueness inside the facility ---------------------------------------
   A branch may not repeat the name or the address of another branch of the
   same facility. The server enforces this as well; checking here means the
   admin is told inside the modal instead of after posting the whole facility.
   Values are compared per locale and loosely, so "Main Branch" and
   "main  branch " count as the same entry.
--------------------------------------------------------------------------- */
const compareKey = (value) =>
  (typeof value === 'string' ? value.trim().replace(/\s+/gu, ' ').toLowerCase() : '');

// The locale whose value is already taken by another branch, or null.
const duplicateLocale = (field) => {
  const values = form.value[field] || {};

  for (const [locale, value] of Object.entries(values)) {
    const key = compareKey(value);
    if (!key) continue;

    const taken = (props.modelValue || []).some((branch, index) => {
      if (index === editingIndex.value) return false;
      return compareKey((branch[field] || {})[locale]) === key;
    });

    if (taken) return locale;
  }

  return null;
};

const localeLabel = (locale) => ({ ar: 'Arabic', en: 'English' }[locale] || locale);

const handleSubmit = async () => {
  errors.value = {};

  // Basic validation
  const nameObj = form.value.name || {};
  const hasName = Object.keys(nameObj).some(key => nameObj[key] && nameObj[key].trim());
  if (!hasName) {
    errors.value.name = t.value?.facility_branch?.name_required || 'Branch name is required in at least one language';
    return;
  }

  // A branch without a place on the map is what makes the directory unusable,
  // so both are asked for here rather than left to be filled in later.
  if (!form.value.governorate_id) {
    errors.value.governorate_id = t.value?.governorate?.required || 'Governorate is required';
  }
  if (!form.value.city_id) {
    errors.value.city_id = t.value?.city?.required || 'City is required';
  }
  if (errors.value.governorate_id || errors.value.city_id) {
    return;
  }

  const duplicateName = duplicateLocale('name');
  if (duplicateName) {
    errors.value.name = (t.value?.facility_branch?.duplicate_name
      || 'Another branch of this facility already uses this name.') + ` (${localeLabel(duplicateName)})`;
    return;
  }

  const duplicateAddress = duplicateLocale('address');
  if (duplicateAddress) {
    errors.value.address = (t.value?.facility_branch?.duplicate_address
      || 'Another branch of this facility already uses this address.') + ` (${localeLabel(duplicateAddress)})`;
    return;
  }

  const locationUrl = (form.value.google_location_url || '').trim();
  if (locationUrl && !/^https?:\/\//i.test(locationUrl)) {
    errors.value.google_location_url = t.value?.facility_branch?.google_location_url_invalid
      || 'The Google location URL must start with http:// or https://';
    return;
  }

  const branchData = {
    id: editingIndex.value !== null && props.modelValue[editingIndex.value]?.id
      ? props.modelValue[editingIndex.value].id
      : null,
    name: form.value.name || {},
    address: form.value.address || {},
    phone: form.value.phone && form.value.phone.length > 0 ? form.value.phone : null,
    governorate_id: form.value.governorate_id || null,
    city_id: form.value.city_id || null,
    latitude: form.value.latitude !== '' && form.value.latitude !== null ? form.value.latitude : null,
    longitude: form.value.longitude !== '' && form.value.longitude !== null ? form.value.longitude : null,
    google_location_url: (form.value.google_location_url || '').trim() || null
  };

  // Without a facility there is nothing to attach the branch to yet, so it
  // waits in the list until the facility itself is created.
  if (!props.facilitySlug) {
    applyBranch(branchData);
    cancelForm();
    return;
  }

  saving.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.branch.save', props.facilitySlug), {
      ...branchData,
      phone: branchData.phone || []
    });

    applyBranch(data.branch);
    markSaved(data.branch);
    useNotification().success(
      data.created
        ? (t.value?.facility_branch?.created || 'Branch created successfully')
        : (t.value?.facility_branch?.updated_success || 'Branch updated successfully')
    );
    cancelForm();
  } catch (error) {
    errors.value = modalErrors(error?.response?.data?.errors);
    useNotification().error(
      error?.response?.data?.message
      || (t.value?.facility_branch?.save_failed || 'Failed to save the branch. Please try again.')
    );
  } finally {
    saving.value = false;
  }
};

// Put a branch — the one just saved, or the local copy on the create page —
// into the list the form holds.
const applyBranch = (branch) => {
  const currentBranches = [...props.modelValue];

  if (editingIndex.value !== null) {
    currentBranches[editingIndex.value] = { ...currentBranches[editingIndex.value], ...branch };
  } else {
    currentBranches.push(branch);
  }

  emit('update:modelValue', currentBranches);
};

// A branch that has just been written is the stored state now, so it should
// stop showing the "New"/"Edited" badge.
const markSaved = (branch) => {
  if (!branch || branch.id == null) return;

  const next = new Map(baseline.value);
  next.set(branch.id, branchFingerprint(branch));
  baseline.value = next;
};

/* Server-side errors for the branch modal. Laravel flattens them per locale
   ("name.ar"), while the inputs take one message per field. */
const modalErrors = (responseErrors) => {
  const mapped = {};

  Object.entries(responseErrors || {}).forEach(([key, messages]) => {
    const field = key.split('.')[0];
    const message = Array.isArray(messages) ? messages[0] : messages;

    if (!mapped[field]) mapped[field] = message;
  });

  return mapped;
};

const deleteBranch = (index) => {
  if (!confirm(t.value?.facility_branch?.confirm_remove || 'Are you sure you want to remove this branch?')) {
    return;
  }

  const currentBranches = [...props.modelValue];
  currentBranches.splice(index, 1);
  emit('update:modelValue', currentBranches);
};
</script>

<style scoped></style>
