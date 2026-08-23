<template>
  <ProductLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Product"
        :breadcrumbs="[
          { label: 'Products', link: route('admin.product.list'), active: false },
          { label: getTranslatedName(product.name), link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2">
        <div class="space-y-3">
          <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
            <div class="p-3 border-b border-border">
              <h2 class="text-lg font-semibold">Product Information</h2>
            </div>
            <div class="p-3">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2 space-y-3">
                  <div v-if="translationPairs(product.name).length">
                    <label class="text-xs font-medium text-muted-foreground">Name</label>
                    <div class="mt-0.5 space-y-1">
                      <p
                        v-for="pair in translationPairs(product.name)"
                        :key="`name-${pair.lang}`"
                        class="text-sm font-medium"
                        :dir="pair.lang === 'AR' ? 'rtl' : 'ltr'"
                      >
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border border-border bg-muted/50 text-muted-foreground me-1 align-middle">{{ pair.lang }}</span>
                        {{ pair.text }}
                      </p>
                    </div>
                  </div>
                  <div v-if="translationPairs(product.short_subject).length">
                    <label class="text-xs font-medium text-muted-foreground">Short Description</label>
                    <div class="mt-0.5 space-y-1">
                      <p
                        v-for="pair in translationPairs(product.short_subject)"
                        :key="`short-${pair.lang}`"
                        class="text-sm"
                        :dir="pair.lang === 'AR' ? 'rtl' : 'ltr'"
                      >
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border border-border bg-muted/50 text-muted-foreground me-1 align-middle">{{ pair.lang }}</span>
                        {{ pair.text }}
                      </p>
                    </div>
                  </div>
                  <div v-if="descriptionPairs.length">
                    <label class="text-xs font-medium text-muted-foreground">Description</label>
                    <div class="mt-0.5 space-y-2">
                      <div
                        v-for="pair in descriptionPairs"
                        :key="`desc-${pair.lang}`"
                        :dir="pair.lang === 'AR' ? 'rtl' : 'ltr'"
                      >
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border border-border bg-muted/50 text-muted-foreground align-middle">{{ pair.lang }}</span>
                        <!-- The editor stores rich text, so it is rendered as
                             markup here instead of showing the raw tags. -->
                        <div
                          class="prose prose-sm prose-invert max-w-none text-sm mt-1 [&_img]:rounded-lg [&_img]:border [&_img]:border-border"
                          v-html="pair.text"
                        ></div>
                      </div>
                    </div>
                  </div>
                  <div class="flex gap-4 flex-wrap">
                    <div v-if="product.new_price">
                      <label class="text-xs font-medium text-muted-foreground">New Price</label>
                      <p class="text-sm font-semibold mt-0.5 value-positive">{{ formatPrice(product.new_price) }}</p>
                    </div>
                    <div v-if="product.old_price">
                      <label class="text-xs font-medium text-muted-foreground">Old Price</label>
                      <p class="text-sm font-medium mt-0.5 value-muted line-through">{{ formatPrice(product.old_price) }}</p>
                    </div>
                    <div v-if="product.cost_price !== null && product.cost_price !== undefined && product.cost_price !== ''">
                      <label class="text-xs font-medium text-muted-foreground">Cost Price</label>
                      <p class="text-sm font-medium mt-0.5">{{ formatPrice(product.cost_price) }}</p>
                    </div>
                    <div v-if="product.profit_price !== null && product.profit_price !== undefined && product.profit_price !== ''">
                      <label class="text-xs font-medium text-muted-foreground">Profit Price</label>
                      <p class="text-sm font-medium mt-0.5 value-positive">{{ formatPrice(product.profit_price) }}</p>
                    </div>
                  </div>
                  <div v-if="product.product_type">
                    <label class="text-xs font-medium text-muted-foreground">Product Type</label>
                    <p class="text-sm font-medium mt-0.5">
                      <span class="chip chip-accent">{{ getTranslatedName(product.product_type.name) }}</span>
                    </p>
                  </div>
                  <div v-if="adminNote">
                    <label class="text-xs font-medium text-muted-foreground">Admin Note</label>
                    <!-- Internal only: never shown to a customer, so it reads as
                         a remark set aside rather than part of the product. -->
                    <p class="panel-note mt-1 text-sm whitespace-pre-wrap">{{ adminNote }}</p>
                  </div>
                  <div v-if="product.tags?.length">
                    <label class="text-xs font-medium text-muted-foreground">Tags</label>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span
                        v-for="tag in product.tags"
                        :key="tag.id"
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                        :style="tag.color ? { backgroundColor: tag.color + '20', color: tag.color } : {}"
                      >
                        {{ tag.icon }} {{ tag.name }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="space-y-3">
                  <div v-if="product.large_image">
                    <label class="text-xs font-medium text-muted-foreground">Large Image</label>
                    <img
                      :src="product.large_image"
                      :alt="getTranslatedName(product.name)"
                      class="mt-1 w-full rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                      @click="openLightbox(product.large_image)"
                    />
                  </div>
                  <div v-if="product.small_image">
                    <label class="text-xs font-medium text-muted-foreground">Small Image</label>
                    <img
                      :src="product.small_image"
                      :alt="getTranslatedName(product.name)"
                      class="mt-1 w-24 h-24 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                      @click="openLightbox(product.small_image)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="product.gallery?.length" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
            <div class="p-3 border-b border-border">
              <h2 class="text-lg font-semibold">Gallery</h2>
            </div>
            <div class="p-3">
              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <img
                  v-for="img in galleryImages"
                  :key="img.url"
                  :src="img.url"
                  :alt="img.alt"
                  class="w-full h-32 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                  @click="openLightbox(img.url)"
                />
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
            <Link
              :href="route('admin.product.list')"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M15 18l-6-6 6-6"></path>
              </svg>
              Back to List
            </Link>
            <Link
              v-if="product.slug"
              :href="route('admin.product.edit', product.slug)"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
              Edit Product
            </Link>
          </div>
        </div>
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </ProductLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import ProductLayout from "./ProductLayout.vue";
import ImageLightbox from "@/Components/ui/ImageLightbox.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed, ref } from "vue";

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = page.props.locale || 'ar';

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

/* Both locales side by side — admins proofread translations, so hiding the
   other language behind the current locale hides exactly what they came to
   check. Plain strings (legacy rows) have no second locale to show. */
const translationPairs = (value) => {
  if (typeof value === 'string' && value.trim() !== '') {
    return [{ lang: 'AR', text: value }];
  }
  if (typeof value === 'object' && value !== null) {
    return [
      { lang: 'AR', text: (value.ar || '').trim() },
      { lang: 'EN', text: (value.en || '').trim() },
    ].filter(pair => pair.text !== '');
  }
  return [];
};

/* Quill always leaves a wrapper behind — `<p><br></p>` for an untouched editor —
   so emptiness is judged on the text and embeds, not on the markup. */
const isBlankHtml = (html) => {
  const value = html || '';
  if (/<(img|iframe|video|table)\b/i.test(value)) return false;
  return value.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim() === '';
};

const descriptionPairs = computed(() =>
  translationPairs(props.product.description).filter((pair) => !isBlankHtml(pair.text))
);

const formatPrice = (price) => {
  if (price === null || price === undefined) return '';
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(price);
};

/* The gallery accessor on the model answers { id, url } per row, so the src has
   to reach for .url — binding the row itself renders "[object Object]" and the
   picture never loads. */
const galleryImages = computed(() =>
  (props.product.gallery || []).map((img, i) => ({
    url: typeof img === 'string' ? img : img?.url,
    alt: `${getTranslatedName(props.product.name)} — ${i + 1}`,
  })).filter(img => img.url)
);

// Every picture on the page shares one viewer, so paging through it walks the
// product images as well as the gallery.
const lightboxImages = computed(() => {
  const name = getTranslatedName(props.product.name);
  const main = [
    props.product.large_image ? { url: props.product.large_image, alt: `${name} — large image` } : null,
    props.product.small_image ? { url: props.product.small_image, alt: `${name} — small image` } : null,
  ].filter(Boolean);

  return [...main, ...galleryImages.value];
});

/* An internal remark, plain text rather than a translation blob — but a blank
   string, a "null" that survived a form round trip, and whitespace all mean the
   same thing here: there is no note to show. */
const adminNote = computed(() => {
  const note = props.product.admin_note;

  return typeof note === 'string' && note.trim() !== '' && note.trim() !== 'null' ? note : '';
});

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = lightboxImages.value.findIndex(img => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};
</script>
