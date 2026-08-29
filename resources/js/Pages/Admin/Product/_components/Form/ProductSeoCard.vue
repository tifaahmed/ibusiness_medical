<template>
  <div class="space-y-3">
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header flex flex-wrap items-start justify-between gap-3 py-2 px-3 sm:px-4 md:px-6 [.border-b]:pb-6">
        <div class="min-w-0 space-y-1.5">
          <div data-slot="card-title" class="leading-none font-semibold title-golden">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.3-4.3"></path>
            </svg>
            SEO Metadata
          </div>
          <p class="text-xs text-muted-foreground max-w-2xl">
            Controls how this product appears in Google results and when its page is shared.
            Leave a field empty to fall back to the product name / description automatically.
          </p>
        </div>

        <!-- AI fill -->
        <div class="flex flex-col items-end gap-1 shrink-0">
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
            {{ generating ? 'Generating…' : 'Generate with AI' }}
          </button>
          <p v-if="generateHint" class="text-[11px] text-muted-foreground max-w-[280px] text-right">
            {{ generateHint }}
          </p>
        </div>
      </div>

      <div data-slot="card-content" class="px-3 sm:px-4 md:px-6 space-y-5">
        <!-- Meta Title -->
        <div class="space-y-1">
          <FormTranslatableInput
            v-model="metaTitle"
            label="Meta Title"
            :error="seoError('meta_title')"
            :locales="['ar', 'en']"
            :maxlength="TITLE_MAX"
            placeholder="Digital Medical Scale — Accurate Weight Monitoring"
          />
          <div class="flex gap-4 text-[11px]">
            <span :class="counterClass(metaTitle.ar, TITLE_MAX)">AR {{ (metaTitle.ar || '').length }}/{{ TITLE_MAX }}</span>
            <span :class="counterClass(metaTitle.en, TITLE_MAX)">EN {{ (metaTitle.en || '').length }}/{{ TITLE_MAX }}</span>
          </div>
          <p class="text-[11px] text-muted-foreground">
            The clickable headline in search results. Aim for 50–60 characters, put the product name first, and include one strong keyword.
          </p>
        </div>

        <!-- Meta Description -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Meta Description</label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="loc in ['ar', 'en']" :key="`desc-${loc}`">
              <label :for="`meta-description-${loc}`" class="block text-xs font-medium text-muted-foreground mb-1">
                {{ loc.toUpperCase() }}
              </label>
              <textarea
                :id="`meta-description-${loc}`"
                :value="metaDescription[loc] || ''"
                @input="metaDescription = { ...metaDescription, [loc]: $event.target.value }"
                :dir="loc === 'ar' ? 'rtl' : 'ltr'"
                rows="3"
                :maxlength="DESCRIPTION_MAX"
                placeholder="One or two sentences shown under the title in search results"
                class="w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md focus:ring-[3px] focus:ring-ring/50 resize-y"
              ></textarea>
              <p class="mt-1 text-[11px]" :class="counterClass(metaDescription[loc], DESCRIPTION_MAX)">
                {{ (metaDescription[loc] || '').length }}/{{ DESCRIPTION_MAX }}
              </p>
            </div>
          </div>
          <p class="text-[11px] text-muted-foreground">
            Your sales pitch in the snippet: what it is, who it is for, and one benefit or offer. 120–160 characters is ideal; longer gets cut off.
          </p>
        </div>

        <!-- Meta Keywords -->
        <div class="space-y-1">
          <FormTranslatableInput
            v-model="metaKeywords"
            label="Meta Keywords"
            :error="seoError('meta_keywords')"
            :locales="['ar', 'en']"
            placeholder="medical scale, digital scale, clinic equipment"
          />
          <p class="text-[11px] text-muted-foreground">
            Comma-separated terms people might search for. Optional — modern search engines barely use them, so only add truly relevant words.
          </p>
        </div>

        <!-- Canonical URL -->
        <div class="space-y-1">
          <FormInput
            v-model="canonicalUrl"
            label="Canonical URL"
            type="url"
            dir="ltr"
            :error="productStore.validationErrors?.canonical_url"
            placeholder="https://example.com/products/digital-medical-scale"
          />
          <p class="text-[11px] text-muted-foreground">
            Only set this if the same product lives at another address and you want search engines to prefer this exact URL. Leave empty in most cases.
          </p>
        </div>

        <!-- Final result preview -->
        <div class="rounded-lg border border-border overflow-hidden">
          <div class="flex items-center justify-between gap-2 px-3 py-2 bg-muted/40 border-b border-border">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              Search result preview
            </span>
            <div class="flex rounded-md border border-border overflow-hidden">
              <button
                v-for="loc in ['ar', 'en']"
                :key="`preview-${loc}`"
                type="button"
                @click="previewLocale = loc"
                class="px-2.5 py-1 text-xs font-medium cursor-pointer transition-colors"
                :class="previewLocale === loc ? 'bg-primary text-primary-foreground' : 'bg-background text-muted-foreground hover:text-foreground'"
              >
                {{ loc.toUpperCase() }}
              </button>
            </div>
          </div>

          <!-- Styled like a Google result on purpose: white page, real link colors. -->
          <div class="bg-white px-4 py-4" dir="ltr">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 text-[10px] font-bold flex items-center justify-center shrink-0">
                {{ siteHost.slice(0, 1).toUpperCase() }}
              </span>
              <div class="leading-tight min-w-0">
                <p class="text-xs text-slate-800 truncate">{{ siteName }}</p>
                <p class="text-xs text-slate-500 truncate">{{ previewUrl }}</p>
              </div>
            </div>
            <p class="text-xl leading-snug text-[#1a0dab] truncate hover:underline cursor-pointer" :dir="previewLocale === 'ar' ? 'rtl' : 'ltr'" :title="previewTitle">
              {{ previewTitle }}
            </p>
            <p class="mt-1 text-sm leading-relaxed text-slate-600 line-clamp-2" :dir="previewLocale === 'ar' ? 'rtl' : 'ltr'">
              {{ previewDescription }}
            </p>
          </div>

          <div class="px-3 py-2 border-t border-border bg-muted/40 flex flex-wrap gap-x-4 gap-y-1 text-[11px]">
            <span :class="counterClass(previewTitleRaw, TITLE_MAX)">Title {{ previewTitleRaw.length }}/{{ TITLE_MAX }}</span>
            <span :class="counterClass(previewDescriptionRaw, DESCRIPTION_MAX)">Description {{ previewDescriptionRaw.length }}/{{ DESCRIPTION_MAX }}</span>
            <span class="text-muted-foreground">Falls back to the product {{ previewDescFallback }} when empty.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Share Image (Open Graph) -->
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm">
      <div data-slot="card-header" class="@container/card-header grid auto-rows-min gap-1.5 py-2 px-3 sm:px-4 md:px-6">
        <div data-slot="card-title" class="leading-none font-semibold title-golden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
            <circle cx="9" cy="9" r="2" />
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
          </svg>
          Share Image (Open Graph)
        </div>
      </div>
      <div data-slot="card-content" class="px-3 sm:px-4 md:px-6">
        <label class="block text-sm font-medium mb-2">
          Share Image (Open Graph)
          <span class="text-xs text-muted-foreground ml-2">(Optional — 1200×630 recommended, max 5MB)</span>
        </label>
        <ImageFileInput
          :max-size="5"
          :accepted-types="['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/avif']"
          :initial-preview="props.existingOgImage || ''"
          :crop-aspect-ratio="1200 / 630"
          :crop-output-width="1200"
          @file-selected="onOgImageSelected"
          @error="(err) => ogImageError = err"
        />
        <p v-if="productStore.validationErrors?.og_image || ogImageError" class="mt-1 text-sm text-destructive">
          {{ productStore.validationErrors?.og_image || ogImageError }}
        </p>
        <p class="mt-2 text-[11px] text-muted-foreground">
          Shown when the product page is shared on WhatsApp, Facebook or X. Falls back to the product's large image when empty.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { FormInput, FormTranslatableInput } from "@/Components/form";
import ImageFileInput from "@/Components/form/ImageFileInput.vue";
import { useProductStore } from "../../Stores/ProductStore";
import { useNotification } from "@/composables/useNotification";
import { computed, ref } from "vue";
import { storeToRefs } from "pinia";
import { usePage } from "@inertiajs/vue3";

// Kept in sync with ProductSeoGenerator::TITLE_MAX / DESCRIPTION_MAX and the
// max rules in Store/UpdateProductRequest.
const TITLE_MAX = 60;
const DESCRIPTION_MAX = 160;

const props = defineProps({
  slug: { type: String, default: '' },
  aiEnabled: { type: Boolean, default: false },
  existingOgImage: { type: String, default: '' },
  productTypes: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
});

const productStore = useProductStore();
const { form } = storeToRefs(productStore);
const page = usePage();
const locale = computed(() => page.props.locale || 'ar');

const translatedName = (name) => {
  if (typeof name === 'string') return name;
  if (name && typeof name === 'object') {
    return name[locale.value] || name.ar || name.en || Object.values(name)[0] || '';
  }
  return '';
};

const productTypeLabel = computed(() => {
  const id = form.value.product_type_id;
  if (!id) return '';
  const match = props.productTypes.find((type) => String(type.id) === String(id));
  return match ? translatedName(match.name) : '';
});

const tagLabels = computed(() => {
  const ids = (form.value.tag_ids || []).map(String);
  return props.tags
    .filter((tag) => ids.includes(String(tag.id)))
    .map((tag) => translatedName(tag.name))
    .filter(Boolean);
});

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

const seoError = (field) =>
  productStore.validationErrors?.[`${field}.ar`]
  || productStore.validationErrors?.[`${field}.en`]
  || productStore.validationErrors?.[field]
  || null;

const counterClass = (value, max) => {
  const length = (value || '').length;
  if (length === 0) return 'text-muted-foreground';
  // Over the limit reads amber; inside it reads green.
  return length >= max ? 'text-amber-400' : 'text-emerald-400';
};

// --- Share image -------------------------------------------------------------

// ImageFileInput emits null when the admin clears the preview. On edit that
// has to become an explicit delete, otherwise the stored image survives.
const onOgImageSelected = (file) => {
  productStore.form.og_image = file || null;
  productStore.form.og_image_delete = !file && Boolean(props.existingOgImage);
  ogImageError.value = '';
};

// --- AI fill ----------------------------------------------------------------

const hasName = computed(() => {
  const name = asObject(form.value.name);
  return Boolean((name.ar || '').trim() || (name.en || '').trim());
});

const canGenerate = computed(() => props.aiEnabled && hasName.value);

const generateHint = computed(() => {
  if (!props.aiEnabled) return 'Set GEMINI_API_KEY in your .env file to enable AI generation.';
  if (!hasName.value) return 'Enter the product name first.';
  return '';
});

const generate = async () => {
  if (!canGenerate.value || generating.value) return;

  generating.value = true;
  try {
    const { data } = await axios.post(route('admin.product.seo.generate'), {
      name: asObject(form.value.name),
      short_subject: asObject(form.value.short_subject),
      description: asObject(form.value.description),
      product_type: productTypeLabel.value || null,
      old_price: form.value.old_price || null,
      new_price: form.value.new_price || null,
      tags: tagLabels.value,
    });

    const seo = data?.seo;
    if (!seo) throw new Error('empty');

    metaTitle.value = { ...metaTitle.value, ...seo.meta_title };
    metaDescription.value = { ...metaDescription.value, ...seo.meta_description };
    metaKeywords.value = { ...metaKeywords.value, ...seo.meta_keywords };

    ['meta_title', 'meta_description', 'meta_keywords'].forEach((field) => {
      if (!productStore.validationErrors) return;
      delete productStore.validationErrors[field];
      delete productStore.validationErrors[`${field}.ar`];
      delete productStore.validationErrors[`${field}.en`];
    });

    useNotification().success('SEO fields filled in. Review them before saving.');
  } catch (error) {
    const message = error?.response?.data?.message
      || error?.response?.data?.errors?.name?.[0]
      || 'Could not generate the SEO fields. Please try again.';
    useNotification().error(message);
  } finally {
    generating.value = false;
  }
};

// --- Preview -------------------------------------------------------------

const previewLocale = ref('en');
const siteHost = typeof window !== 'undefined' ? window.location.host : 'example.com';
const siteName = siteHost.replace(/^www\./, '');

const previewUrl = computed(() =>
  `https://${siteHost}/products/${props.slug || 'your-product-slug'}`
);

const firstOf = (map) => {
  const values = Object.values(asObject(map)).filter(Boolean);
  return values.length ? values[0] : '';
};

const previewTitleRaw = computed(() => {
  const map = asObject(form.value.meta_title);
  return (map[previewLocale.value] || asObject(form.value.name)[previewLocale.value] || firstOf(form.value.name) || '').trim();
});
const previewTitle = computed(() => previewTitleRaw.value || 'Your product title will appear here');

const previewDescFallback = computed(() =>
  (asObject(form.value.meta_description)[previewLocale.value] || '').trim() ? 'title'
    : ((asObject(form.value.short_subject)[previewLocale.value] || '').trim() ? 'short description' : 'description')
);

const previewDescriptionRaw = computed(() => {
  const own = (asObject(form.value.meta_description)[previewLocale.value] || '').trim();
  if (own) return own;
  const shortSubject = (asObject(form.value.short_subject)[previewLocale.value] || '').trim();
  if (shortSubject) return shortSubject;
  return (asObject(form.value.description)[previewLocale.value] || '').trim().slice(0, DESCRIPTION_MAX);
});
const previewDescription = computed(() => previewDescriptionRaw.value || 'The meta description you write here is exactly what shoppers read under the link.');
</script>

<style lang="scss" scoped></style>
