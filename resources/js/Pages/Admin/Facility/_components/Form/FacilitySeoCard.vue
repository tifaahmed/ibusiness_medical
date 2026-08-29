<template>
  <div class="space-y-3">
    <!-- Search Metadata Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header flex flex-wrap items-start justify-between gap-3 py-2 px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.3-4.3"></path>
          </svg>
          {{ t.facility?.seo_metadata || 'Search Metadata' }}
        </div>

        <!-- AI fill -->
        <div class="flex flex-col items-end gap-1">
          <button
            type="button"
            :disabled="!canGenerate || generating"
            @click="generate"
            class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 h-9 px-4 py-2 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 btn-golden"
            :title="generateHint"
          >
            <svg v-if="generating" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 3v3m0 12v3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1M3 12h3m12 0h3M5.6 18.4l2.1-2.1m8.6-8.6 2.1-2.1"></path>
            </svg>
            {{ generating ? (t.facility?.seo_generating || 'Generating…') : (t.facility?.seo_generate || 'Generate with AI') }}
          </button>
          <p v-if="generateHint" class="text-[11px] text-muted-foreground max-w-[280px] text-right">
            {{ generateHint }}
          </p>
        </div>
      </div>

      <div data-slot="card-content" class="px-6 space-y-4">
        <p class="text-xs text-muted-foreground">
          {{ t.facility?.seo_help || 'Used as the page title, search-result snippet and share preview for this facility. Leave blank to fall back to the site defaults.' }}
        </p>

        <!-- Meta Title -->
        <div data-slot="form-item" class="grid gap-1">
          <FormTranslatableInput
            v-model="metaTitle"
            :label="t.facility?.meta_title || 'Meta Title'"
            :error="facilityStore.validationErrors?.['meta_title.ar'] || facilityStore.validationErrors?.['meta_title.en'] || facilityStore.validationErrors?.meta_title"
            :locales="['ar', 'en']"
            :maxlength="TITLE_MAX"
          />
          <div class="flex gap-4 text-[11px] text-muted-foreground">
            <span :class="counterClass(metaTitle.ar, TITLE_MAX)">AR {{ (metaTitle.ar || '').length }}/{{ TITLE_MAX }}</span>
            <span :class="counterClass(metaTitle.en, TITLE_MAX)">EN {{ (metaTitle.en || '').length }}/{{ TITLE_MAX }}</span>
          </div>
        </div>

        <!-- Meta Description -->
        <div data-slot="form-item" class="grid gap-1">
          <label class="block text-sm font-medium text-white mb-1">
            {{ t.facility?.meta_description || 'Meta Description' }}
          </label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="loc in ['ar', 'en']" :key="`desc-${loc}`">
              <label :for="`meta-description-${loc}`" class="block text-xs font-medium text-muted-foreground mb-1">
                {{ loc.toUpperCase() }}
              </label>
              <textarea
                :id="`meta-description-${loc}`"
                :value="metaDescription[loc] || ''"
                @input="setDescription(loc, $event.target.value)"
                :dir="loc === 'ar' ? 'rtl' : 'ltr'"
                rows="3"
                :maxlength="DESCRIPTION_MAX"
                class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md mt-1 focus:ring-[3px] focus:ring-ring/50 resize-y"
                :placeholder="t.facility?.meta_description_placeholder || 'One or two sentences shown under the title in search results'"
              ></textarea>
              <p class="mt-1 text-[11px]" :class="counterClass(metaDescription[loc], DESCRIPTION_MAX)">
                {{ (metaDescription[loc] || '').length }}/{{ DESCRIPTION_MAX }}
              </p>
            </div>
          </div>
          <p v-if="descriptionError" class="mt-1 text-sm text-destructive">{{ descriptionError }}</p>
        </div>

        <!-- Meta Keywords -->
        <div data-slot="form-item" class="grid gap-1">
          <FormTranslatableInput
            v-model="metaKeywords"
            :label="t.facility?.meta_keywords || 'Meta Keywords'"
            :error="facilityStore.validationErrors?.['meta_keywords.ar'] || facilityStore.validationErrors?.['meta_keywords.en'] || facilityStore.validationErrors?.meta_keywords"
            :locales="['ar', 'en']"
          />
          <p class="text-[11px] text-muted-foreground">
            {{ t.facility?.meta_keywords_help || 'Comma-separated terms, e.g. clinic, Cairo, medical discount.' }}
          </p>
        </div>

        <!-- Canonical URL -->
        <div data-slot="form-item" class="grid gap-1">
          <FormInput
            v-model="canonicalUrl"
            :label="t.facility?.canonical_url || 'Canonical URL'"
            type="url"
            dir="ltr"
            :error="facilityStore.validationErrors?.canonical_url"
            :placeholder="t.facility?.canonical_url_placeholder || 'https://example.com/partners/facility-slug'"
          />
          <p class="text-[11px] text-muted-foreground">
            {{ t.facility?.canonical_url_help || 'Only set this when the same content lives at another address you want search engines to prefer.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Share Image Card -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min gap-1.5 py-2 px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
            <circle cx="9" cy="9" r="2"/>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
          </svg>
          {{ t.facility?.og_image || 'Share Image (Open Graph)' }}
        </div>
      </div>
      <div data-slot="card-content" class="px-6">
        <label class="block text-sm font-medium text-white mb-2">
          {{ t.facility?.og_image || 'Share Image (Open Graph)' }}
          <span class="text-xs text-muted-foreground ml-2">{{ t.facility?.og_image_hint || '(Optional - 1200×630 recommended, max 5MB)' }}</span>
        </label>
        <ImageFileInput
          :max-size="5"
          :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
          :initial-preview="props.facility?.og_image || ''"
          :crop-aspect-ratio="1200 / 630"
          :crop-output-width="1200"
          @file-selected="onOgImageSelected"
          @error="(err) => ogImageError = err"
        />
        <p v-if="facilityStore.validationErrors?.og_image || ogImageError" class="mt-1 text-sm text-destructive">
          {{ facilityStore.validationErrors?.og_image || ogImageError }}
        </p>
        <p class="mt-2 text-[11px] text-muted-foreground">
          {{ t.facility?.og_image_help || 'Shown when the facility page is shared on WhatsApp, Facebook or X. Falls back to the cover image when empty.' }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormTranslatableInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useFacilityStore } from "../../Stores/FacilityStore";
import { useNotification } from "@/composables/useNotification";
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

// Kept in sync with FacilitySeoGenerator::TITLE_MAX / DESCRIPTION_MAX and the
// max: rules in Store/UpdateFacilityRequest.
const TITLE_MAX = 60;
const DESCRIPTION_MAX = 160;

const props = defineProps({
  facility: {
    type: Object,
    default: () => null
  },
  facilityTypes: {
    type: Array,
    default: () => []
  },
  // Governorate/city names come from the branches the admin has entered, so the
  // model can mention real coverage instead of guessing.
  branches: {
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
  aiEnabled: {
    type: Boolean,
    default: false
  }
});

const facilityStore = useFacilityStore();
const { form } = storeToRefs(facilityStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

const generating = ref(false);
const ogImageError = ref('');

const asObject = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const translatable = (field) => computed({
  get: () => asObject(form.value[field]),
  set: (value) => { form.value[field] = value; },
});

const metaTitle = translatable('meta_title');
const metaDescription = translatable('meta_description');
const metaKeywords = translatable('meta_keywords');

const canonicalUrl = computed({
  get: () => form.value.canonical_url ?? '',
  set: (value) => { form.value.canonical_url = value; },
});

const setDescription = (loc, value) => {
  metaDescription.value = { ...metaDescription.value, [loc]: value };
};

const descriptionError = computed(() =>
  facilityStore.validationErrors?.['meta_description.ar']
  || facilityStore.validationErrors?.['meta_description.en']
  || facilityStore.validationErrors?.meta_description
  || ''
);

const counterClass = (value, max) => {
  const length = (value || '').length;
  if (length === 0) return 'text-muted-foreground';
  if (length >= max) return 'text-amber-400';
  return 'text-emerald-400';
};

// ImageFileInput emits null when the admin clears the preview. On edit that
// has to become an explicit delete, otherwise the stored image survives.
const onOgImageSelected = (file) => {
  facilityStore.form.og_image = file || null;
  facilityStore.form.og_image_delete = !file && Boolean(props.facility?.og_image);
  ogImageError.value = '';
};

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    return name[locale.value] || name.ar || name.en || Object.values(name)[0] || '';
  }
  return '';
};

// The AI needs a name to work from; everything else is optional context.
const hasName = computed(() => {
  const name = asObject(form.value.name);
  return Boolean((name.ar || '').trim() || (name.en || '').trim());
});

const canGenerate = computed(() => props.aiEnabled && hasName.value);

const generateHint = computed(() => {
  if (!props.aiEnabled) return t.value.facility?.seo_ai_disabled || 'Set GEMINI_API_KEY in your .env file to enable AI generation.';
  if (!hasName.value) return t.value.facility?.seo_needs_name || 'Enter the facility name first.';
  return '';
});

const facilityTypeLabel = computed(() => {
  const id = form.value.facility_type_id;
  if (!id) return '';
  const match = props.facilityTypes.find(type => String(type.id) === String(id));
  return match ? getTranslatedName(match.name) : '';
});

// Branch rows only carry governorate_id/city_id, so resolve them to names here.
const branchPlaceNames = (idKey, source) => {
  const ids = [...new Set(props.branches.map(branch => branch?.[idKey]).filter(Boolean).map(String))];
  return ids
    .map(id => {
      const match = source.find(entry => String(entry.id) === id);
      return match ? getTranslatedName(match.name) : '';
    })
    .filter(Boolean);
};

const generate = async () => {
  if (!canGenerate.value || generating.value) return;

  generating.value = true;
  try {
    const { data } = await axios.post(route('admin.facility.seo.generate'), {
      name: asObject(form.value.name),
      description: asObject(form.value.description),
      facility_type: facilityTypeLabel.value || null,
      discount_percent: form.value.discount_percent ?? null,
      governorates: branchPlaceNames('governorate_id', props.governorates),
      cities: branchPlaceNames('city_id', props.cities),
    });

    const seo = data?.seo;
    if (!seo) throw new Error('empty');

    metaTitle.value = { ...metaTitle.value, ...seo.meta_title };
    metaDescription.value = { ...metaDescription.value, ...seo.meta_description };
    metaKeywords.value = { ...metaKeywords.value, ...seo.meta_keywords };

    // The AI wrote into fields the server may have flagged before — clear those.
    ['meta_title', 'meta_description', 'meta_keywords'].forEach((field) => {
      if (!facilityStore.validationErrors) return;
      delete facilityStore.validationErrors[field];
      delete facilityStore.validationErrors[`${field}.ar`];
      delete facilityStore.validationErrors[`${field}.en`];
    });

    useNotification().success(t.value.facility?.seo_generated || 'SEO fields filled in. Review them before saving.');
  } catch (error) {
    const message = error?.response?.data?.message
      || error?.response?.data?.errors?.name?.[0]
      || (t.value.facility?.seo_generate_failed || 'Could not generate the SEO fields. Please try again.');
    useNotification().error(message);
  } finally {
    generating.value = false;
  }
};
</script>

<style lang="scss" scoped></style>
