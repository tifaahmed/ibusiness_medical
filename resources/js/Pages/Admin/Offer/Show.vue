<template>
  <OfferLayout>
    <Breadcrumb
      :title="t.offer?.view || 'View Offer'"
      :breadcrumbs="[
        { label: t.offer?.title || 'Offers', link: route('admin.offer.list'), active: false },
        { label: t.offer?.view || 'View Offer', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Offer Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.offer?.information || 'Offer Information' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.offer?.title_label || 'Title' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(offer.title) || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.slug || 'Slug' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ offer.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.phone || 'Phone' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ offer.phone || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.id || 'ID' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ offer.id }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.offer?.price || 'Price' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ formatPrice(offer.price) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.offer?.old_price || 'Old Price' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ formatPrice(offer.old_price) || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div v-if="hasDiscount">
                <label class="text-xs font-medium text-muted-foreground">{{ t.offer?.discount || 'Discount' }}</label>
                <p class="text-sm font-medium mt-0.5">
                  <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500 ring-1 ring-inset ring-emerald-500/20">
                    {{ discountPercentage }}% {{ t.offer?.off || 'OFF' }}
                  </span>
                </p>
              </div>
            </div>
            <div v-if="getTranslatedName(offer.short_description)" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.offer?.short_description || 'Short Description' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(offer.short_description) }}</p>
            </div>
            <div v-if="getTranslatedName(offer.full_description)" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.offer?.full_description || 'Full Description' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white whitespace-pre-wrap">{{ getTranslatedName(offer.full_description) }}</p>
            </div>
          </div>
        </div>

        <!-- Images Card -->
        <div v-if="offer.image || offer.thumbnail" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.offer?.images || 'Images' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Main Image -->
              <div v-if="offer.image">
                <label class="text-xs font-medium text-muted-foreground mb-2 block">{{ t.offer?.main_image || 'Main Image' }}</label>
                <img :src="offer.image" :alt="t.offer?.main_image || 'Offer Image'" class="w-full h-64 object-cover rounded-lg border border-border" />
              </div>
              <!-- Thumbnail -->
              <div v-if="offer.thumbnail">
                <label class="text-xs font-medium text-muted-foreground mb-2 block">{{ t.offer?.thumbnail || 'Thumbnail' }}</label>
                <img :src="offer.thumbnail" :alt="t.offer?.thumbnail || 'Offer Thumbnail'" class="w-full h-64 object-cover rounded-lg border border-border" />
              </div>
            </div>
          </div>
        </div>

        <!-- Attached To Card -->
        <div v-if="offer.offerable" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.offer?.attached_to || 'Attached To' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.type || 'Type' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">
                  <span :class="[
                    'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                    offerableTypeBadge.class
                  ]">
                    {{ offerableTypeBadge.label }}
                  </span>
                </p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.name || 'Name' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(offer.offerable.name) }}</p>
              </div>
              <div v-if="offer.offerable.facility">
                <label class="text-xs font-medium text-muted-foreground">{{ t.facility_branch?.parent_facility || 'Parent Facility' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(offer.offerable.facility?.name) || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <Link
                  v-if="offerableViewLink"
                  :href="offerableViewLink"
                  class="text-primary hover:underline text-sm"
                >
                  {{ viewRelatedLabel }}
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.offer.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>
          <Link
            v-if="offer.slug"
            :href="route('admin.offer.edit', offer.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            {{ t.offer?.edit || 'Edit Offer' }}
          </Link>
        </div>
      </div>
    </div>
  </OfferLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import { usePage } from '@inertiajs/vue3';
import OfferLayout from "./OfferLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed } from 'vue';
import { usePriceFormat } from '@/composables/usePriceFormat';

const { formatPrice } = usePriceFormat();

const props = defineProps({
  offer: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};

const hasDiscount = computed(() => {
  return props.offer.old_price && props.offer.price && props.offer.old_price > props.offer.price;
});

const discountPercentage = computed(() => {
  if (!hasDiscount.value) return 0;
  return Math.round(((props.offer.old_price - props.offer.price) / props.offer.old_price) * 100);
});

const offerableTypeBadge = computed(() => {
  if (props.offer.offerable_type === 'App\\Models\\Facility') {
    return {
      label: t.value.facility?.label || 'Facility',
      class: 'bg-blue-500/10 text-blue-500 ring-blue-500/20'
    };
  } else if (props.offer.offerable_type === 'App\\Models\\FacilityBranch') {
    return {
      label: t.value.offer?.branch || t.value.facility_branch?.label || 'Branch',
      class: 'bg-purple-500/10 text-purple-500 ring-purple-500/20'
    };
  }
  return {
    label: t.value.offer?.unknown || 'Unknown',
    class: 'bg-gray-500/10 text-gray-500 ring-gray-500/20'
  };
});

const viewRelatedLabel = computed(() => {
  return (t.value.offer?.view_related || 'View :type').replace(':type', offerableTypeBadge.value.label);
});

const offerableViewLink = computed(() => {
  if (!props.offer.offerable) return null;

  if (props.offer.offerable_type === 'App\\Models\\Facility') {
    return route('admin.facility.show', props.offer.offerable.slug);
  } else if (props.offer.offerable_type === 'App\\Models\\FacilityBranch') {
    return route('admin.facility-branch.show', props.offer.offerable.slug);
  }

  return null;
});
</script>

<style lang="scss" scoped></style>
