<template>
  <FacilityLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="mb-4">
        <div class="space-y-2"></div>
      </div>
      <div class="space-y-4">
        <div class="space-y-3">
          <div
            v-if="page.props.flash?.success"
            class="rounded-md border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm"
          >
            {{ page.props.flash.success }}
          </div>

          <!-- One-click English cleanup for this facility + its branches. -->
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
              :disabled="!englishFixEnabled || englishFixRunning"
              :title="englishFixEnabled ? (t.facility_branch?.fix_languages_all_hint || 'Fix the Arabic and English of the facility and its branches when either has a problem') : englishFixDisabledReason"
              class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              @click="fixEnglish"
            >
              <svg v-if="englishFixRunning" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 8h14M5 8a2 2 0 0 1 0-4h14a2 2 0 0 1 0 4M5 8v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"></path>
              </svg>
              {{ englishFixRunning ? (t.facility_branch?.fix_languages_fixing || 'Fixing languages…') : (t.facility_branch?.fix_languages || 'Fix languages with AI') }}
            </button>
          </div>

          <form class="space-y-3" @submit.prevent="handleSubmit()">
            <TabBar v-model="activeTab" :tabs="tabs" :error-title="t.facility?.tab_has_errors || 'This tab has errors'" />

            <div v-show="activeTab === 'details'" class="space-y-3">
              <FacilityForm :facility-types="facilityTypes" :facility="facility" :tags="tags" :sales-options="salesOptions" />
              <FacilityBranchCard v-model="branches" :governorates="governorates" :cities="cities" :facility-slug="facility.slug" :ai-enabled="locationAiEnabled" :english-fix-enabled="englishFixEnabled" :place-ai-enabled="placeAiEnabled" />
              <FacilityManagerCard v-model="managers" :facility-slug="facility.slug" />
              <FacilityBranchesMap :branches="branches" />
            </div>

            <!-- v-show, not v-if: the SEO inputs stay mounted so AI-filled
                 values survive switching back to the details tab. -->
            <div v-show="activeTab === 'seo'" class="space-y-3">
              <FacilitySeoCard
                :facility="facility"
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
                <div class="flex flex-wrap gap-3 justify-end">
                  <Link
                    :href="route('admin.facility.list')"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3"
                    type="button"
                  >
                    {{ t.common?.cancel || 'Cancel' }}
                  </Link>
                  <button
                    type="button"
                    :disabled="facilityStore.form.processing"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-accent dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px]"
                    @click="handleSubmit({ stay: true })"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save w-4 h-4 mr-2">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    {{ t.facility?.save_stay || 'Save & Stay' }}
                  </button>
                  <div class="relative inline-flex">
                    <button
                      type="button"
                      :disabled="facilityStore.form.processing"
                      data-slot="button"
                      class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] btn-golden"
                      @click="handleSubmit({ stay: false })"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save w-4 h-4 mr-2">
                        <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                        <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                        <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                      </svg>
                      {{ t.facility?.save_return || 'Save & Return' }}
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
import { Link, router, usePage } from "@inertiajs/vue3";
import { watch, ref, computed } from "vue";
import FacilityLayout from "../FacilityLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useFacilityStore } from "../Stores/FacilityStore";
import { FacilityForm, FacilityBranchCard, FacilitySeoCard, FacilityManagerCard } from "../_components/Form";
import FacilityBranchesMap from "../_components/Form/FacilityBranchesMap.vue";
import TabBar from "@/Components/ui/TabBar.vue";
import ErrorTrackButton from "@/Components/ui/ErrorTrackButton.vue";
import { useNotification } from "@/composables/useNotification";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  facility: {
    type: Object,
    required: true,
  },
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

const fixEnglish = async () => {
  if (!props.englishFixEnabled || englishFixRunning.value) return;

  englishFixRunning.value = true;
  englishFixMessage.value = '';
  try {
    const { data } = await axios.post(route('admin.facility.english.fix', props.facility.slug));
    const applied = data?.applied?.length || 0;

    if (applied === 0) {
      useNotification().info(t.value.facility_branch?.fix_languages_nothing || 'Both languages already look right.');
    } else {
      useNotification().success(
        (t.value.facility_branch?.fix_languages_all_done || 'Fixed :count field(s). Reloading…').replace(':count', applied)
      );
      router.reload({ only: ['facility'] });
    }

    if (data?.errors?.length) {
      englishFixMessage.value = data.errors.join(' ');
    }
  } catch (error) {
    useNotification().error(error?.response?.data?.message || t.value.facility_branch?.fix_languages_failed || 'Could not fix the languages. Please try again.');
  } finally {
    englishFixRunning.value = false;
  }
};

// The open tab lives in the URL (?tab=seo), so a reload or a shared link lands
// on the same tab. "details" is the default and keeps the address clean.
const TAB_KEYS = ['details', 'seo'];
const tabFromUrl = () => {
  const tab = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('tab') : null;
  return TAB_KEYS.includes(tab) ? tab : 'details';
};
const activeTab = ref(tabFromUrl());

watch(activeTab, (tab) => {
  const url = new URL(window.location.href);
  if (tab === 'details') {
    url.searchParams.delete('tab');
  } else {
    url.searchParams.set('tab', tab);
  }
  // Keep Inertia's own history state; only the address changes, nothing reloads.
  window.history.replaceState(window.history.state, '', url);
});

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

// Set facility data when props are received
// Use watch with immediate to ensure it runs on mount and when props change
watch(() => props.facility, (newFacility) => {
  if (newFacility && newFacility.id) {
    facilityStore.setFacility(newFacility);
    branches.value = newFacility.branches || [];
    managers.value = newFacility.managers || [];
  }
}, { immediate: true, deep: true });

const handleSubmit = (options = {}) => {
  facilityStore.updateFacility(branches.value, managers.value, { ...options, tab: activeTab.value });
};
</script>

<style lang="scss" scoped></style>

