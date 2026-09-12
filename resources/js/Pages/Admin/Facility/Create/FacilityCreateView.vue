<template>
  <FacilityLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 max-w-7xl relative z-10">
      <div class="mb-4">
        <div class="space-y-2"></div>
      </div>
      <div class="space-y-4">
        <div class="space-y-3">
          <!-- One-click English cleanup, the same button the edit page carries.
               There is no saved facility here, so it works on the boxes as they
               stand and writes nothing until the form is submitted. -->
          <div class="flex flex-wrap items-center justify-end gap-2">
            <p v-if="englishFixMessage" class="mr-auto text-xs text-muted-foreground">{{ englishFixMessage }}</p>
            <!-- A disabled button with no reason on it reads as broken, and the
                 title tooltip cannot be reached without a hover — so the reason
                 gets its own marker, readable on tap as well. -->
            <button
              v-if="!englishFixEnabled"
              type="button"
              class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-border text-[11px] font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground"
              :title="englishFixDisabledReason"
              :aria-label="englishFixDisabledReason"
              @click="showEnglishFixReason = !showEnglishFixReason"
            >
              i
            </button>
            <p v-if="!englishFixEnabled && showEnglishFixReason" class="text-xs text-amber-300">
              {{ englishFixDisabledReason }}
            </p>
            <button
              type="button"
              :disabled="!canFixEnglish || englishFixRunning"
              :title="englishFixHint"
              class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              @click="fixEnglish"
            >
              <svg v-if="englishFixRunning" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
              </svg>
              {{ englishFixRunning ? 'Fixing English…' : 'Fix English fields with AI' }}
            </button>
          </div>

          <form class="space-y-3" @submit.prevent="handleSubmit">
            <TabBar v-model="activeTab" :tabs="tabs" :error-title="t.facility?.tab_has_errors || 'This tab has errors'" />

            <div v-show="activeTab === 'details'" class="space-y-3">
              <FacilityForm :facility-types="facilityTypes" :facility="null" :tags="tags" :sales-options="salesOptions" />
              <FacilityBranchCard v-model="branches" :governorates="governorates" :cities="cities" :ai-enabled="locationAiEnabled" :english-fix-enabled="englishFixEnabled" :place-ai-enabled="placeAiEnabled" />
              <FacilityManagerCard v-model="managers" />
            </div>

            <!-- v-show, not v-if: the SEO inputs stay mounted so AI-filled
                 values survive switching back to the details tab. -->
            <div v-show="activeTab === 'seo'" class="space-y-3">
              <FacilitySeoCard
                :facility="null"
                :facility-types="facilityTypes"
                :branches="branches"
                :governorates="governorates"
                :cities="cities"
                :ai-enabled="seoAiEnabled"
              />
            </div>

            <!-- Sticky Form Actions -->
            <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
              <div class="flex flex-col sm:flex-row p-4">
                <div class="flex-1"></div>
                <div class="flex gap-3 justify-end">
                  <Link
                    :href="route('admin.facility.list')"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                    type="button"
                  >
                    {{ t.common?.cancel || 'Cancel' }}
                  </Link>
                  <div class="relative inline-flex order-1 sm:order-2">
                    <button
                      type="submit"
                      :disabled="facilityStore.form.processing"
                      data-slot="button"
                      class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] btn-golden"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save w-4 h-4 mr-2">
                        <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                        <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                        <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                      </svg>
                      {{ t.facility?.create || 'Create Facility' }}
                    </button>
                    <ErrorTrackButton :errors="facilityStore.validationErrors || {}" :debug-log="facilityStore.debugLog" />
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { ref, onMounted, computed } from "vue";
import { useNotification } from "@/composables/useNotification";
import { nameIn, primaryName } from "@/lib/lookupNames";
import FacilityLayout from "../FacilityLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useFacilityStore } from "../Stores/FacilityStore";
import { FacilityForm, FacilityBranchCard, FacilitySeoCard, FacilityManagerCard } from "../_components/Form";
import TabBar from "@/Components/ui/TabBar.vue";
import ErrorTrackButton from "@/Components/ui/ErrorTrackButton.vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  facilityTypes: {
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
  tags: {
    type: Array,
    default: () => []
  },
  salesOptions: {
    type: Array,
    default: () => []
  },
  seoAiEnabled: {
    type: Boolean,
    default: false
  },
  locationAiEnabled: {
    type: Boolean,
    default: false
  },
  englishFixEnabled: {
    type: Boolean,
    default: false
  },
  placeAiEnabled: {
    type: Boolean,
    default: false
  }
});

const facilityStore = useFacilityStore();

const englishFixRunning = ref(false);
const englishFixMessage = ref('');
const showEnglishFixReason = ref(false);

// The one thing that turns this button off is a missing key, so the message
// names it rather than saying "unavailable".
const englishFixDisabledReason = computed(() =>
  t.value.facility?.english_fix_disabled
  || 'AI is not configured on this server: set GEMINI_API_KEY in the .env file, then restart, to enable this.'
);

/* Arabic is the source the AI translates from, so there is nothing to do until
   the facility or one of its branches has some. */
const hasArabic = computed(() => {
  const form = facilityStore.form || {};
  const own = nameIn(form.name, 'ar') !== '' || nameIn(form.description, 'ar') !== '';

  return own || branches.value.some(
    branch => nameIn(branch?.name, 'ar') !== '' || nameIn(branch?.address, 'ar') !== ''
  );
});

const canFixEnglish = computed(() => props.englishFixEnabled && hasArabic.value);

const englishFixHint = computed(() => {
  if (!props.englishFixEnabled) return englishFixDisabledReason.value;
  if (!hasArabic.value) {
    return t.value.facility?.english_fix_needs_arabic || 'Fill in the Arabic name or description first.';
  }

  return 'Translate / fix empty or Arabic English fields';
});

// The place a branch sits in, in English where the row carries it — context
// for the AI, so a district name is not read as a person's.
const lookupLabel = (rows, id) => {
  if (id === null || id === undefined || id === '') return null;
  const match = rows.find(row => String(row.id) === String(id));

  return match ? (primaryName(match.name, 'en') || null) : null;
};

const facilityTypeLabel = computed(() => {
  const match = props.facilityTypes.find(
    type => String(type.id) === String(facilityStore.form?.facility_type_id)
  );

  return match ? primaryName(match.name, 'en') : '';
});

// Fills the English side of the facility and of every branch already in the
// list from their Arabic ones, as they stand in the form — the edit page's
// button reads the saved row, which does not exist yet here. Nothing is
// written; the values land in the open form for the admin to check.
const fixEnglish = async () => {
  if (!canFixEnglish.value || englishFixRunning.value) return;

  englishFixRunning.value = true;
  englishFixMessage.value = '';
  try {
    const form = facilityStore.form;
    const { data } = await axios.post(route('admin.facility.translate'), {
      name: { ar: nameIn(form.name, 'ar'), en: nameIn(form.name, 'en') },
      description: { ar: nameIn(form.description, 'ar'), en: nameIn(form.description, 'en') },
      facility_type: facilityTypeLabel.value || null,
      branches: branches.value.map(branch => ({
        name: { ar: nameIn(branch?.name, 'ar'), en: nameIn(branch?.name, 'en') },
        address: { ar: nameIn(branch?.address, 'ar'), en: nameIn(branch?.address, 'en') },
        governorate: lookupLabel(props.governorates, branch?.governorate_id),
        city: lookupLabel(props.cities, branch?.city_id),
      })),
    });

    const values = data?.values || {};
    const branchValues = data?.branches || {};
    let filled = 0;

    ['name', 'description'].forEach((field) => {
      if (!values[field]) return;
      form[field] = { ...(form[field] || {}), en: values[field] };
      filled += 1;
    });

    branches.value = branches.value.map((branch, index) => {
      const answer = branchValues[index] || branchValues[String(index)];
      if (!answer) return branch;

      const updated = { ...branch };
      ['name', 'address'].forEach((field) => {
        if (!answer[field]) return;
        updated[field] = { ...(updated[field] || {}), en: answer[field] };
        filled += 1;
      });

      return updated;
    });

    if (filled === 0) {
      useNotification().info('No English fields needed fixing.');
    } else {
      useNotification().success(`Filled ${filled} English field(s). Check them before saving.`);
    }
  } catch (error) {
    useNotification().error(
      error?.response?.data?.message || 'Could not fix the English fields. Please try again.'
    );
  } finally {
    englishFixRunning.value = false;
  }
};

const activeTab = ref('details');

// Server-side errors can land on a tab the admin isn't looking at, so flag it.
const SEO_ERROR_KEYS = ['meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_image'];
const isSeoErrorKey = (key) => SEO_ERROR_KEYS.some(field => key === field || key.startsWith(`${field}.`));

const errorKeys = computed(() => Object.keys(facilityStore.validationErrors || {}));

const tabs = computed(() => [
  {
    key: 'details',
    label: t.value.facility?.tab_details || 'Details',
    hasError: errorKeys.value.some(key => !isSeoErrorKey(key)),
  },
  {
    key: 'seo',
    label: t.value.facility?.tab_seo || 'SEO',
    hasError: errorKeys.value.some(isSeoErrorKey),
  },
]);
const branches = ref([]);
const managers = ref([]);

onMounted(() => {
  facilityStore.initializeForm();
});

const handleSubmit = () => {
  facilityStore.submitForm(branches.value, managers.value);
};
</script>

<style lang="scss" scoped></style>

