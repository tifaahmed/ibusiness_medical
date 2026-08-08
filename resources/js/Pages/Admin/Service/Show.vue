<template>
  <ServiceLayout>
    <Breadcrumb
      :title="t.service?.view || 'View Service'"
      :breadcrumbs="[
        { label: t.service?.title || 'Services', link: route('admin.service.list'), active: false },
        { label: t.service?.view || 'View Service', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Service Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.service?.information || 'Service Information' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.title_label || 'Title' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ service.title || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.slug || 'Slug' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ service.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.category || 'Category' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ service.category?.name || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.id || 'ID' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ service.id }}</p>
              </div>
              <div v-if="service.tag">
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.tag || 'Tag' }}</label>
                <p class="text-sm font-medium mt-0.5">
                  <span :class="tagBadgeClass(service.tag)" class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset">
                    {{ tagLabel(service.tag) }}
                  </span>
                </p>
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.new_price || 'Price' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ formatPrice(service.new_price) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.old_price || 'Old Price' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ formatPrice(service.old_price) || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div v-if="hasDiscount">
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.discount || 'Discount' }}</label>
                <p class="text-sm font-medium mt-0.5">
                  <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500 ring-1 ring-inset ring-emerald-500/20">
                    {{ discountPercentage }}% {{ t.service?.off || 'OFF' }}
                  </span>
                </p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.governorate?.label || 'Governorate' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ service.governorate?.name || t.common?.n_a || 'N/A' }}</p>
              </div>
            </div>
            <div v-if="service.lat || service.long" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.lat || 'Latitude' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ service.lat ?? (t.common?.n_a || 'N/A') }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.service?.long || 'Longitude' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ service.long ?? (t.common?.n_a || 'N/A') }}</p>
              </div>
            </div>
            <div v-if="service.tags && service.tags.length" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.service?.tags || 'Tags' }}</label>
              <div class="flex flex-wrap gap-2 mt-1">
                <span
                  v-for="tagItem in service.tags"
                  :key="tagItem.id"
                  class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
                  :style="tagChipStyle(tagItem)"
                >
                  <span v-if="tagItem.icon">{{ tagItem.icon }}</span>
                  {{ tagItem.name }}
                </span>
              </div>
            </div>
            <div v-if="service.short_subject" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.service?.short_subject || 'Short Subject' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white">{{ service.short_subject }}</p>
            </div>
            <div v-if="service.subject" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.service?.subject || 'Subject' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white whitespace-pre-wrap">{{ service.subject }}</p>
            </div>
          </div>
        </div>

        <!-- Map Card -->
        <div v-if="service.iframe_location" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.service?.iframe_location || 'Map Location' }}</h2>
          </div>
          <div class="p-3">
            <div class="rounded-lg overflow-hidden border border-border" v-html="service.iframe_location"></div>
          </div>
        </div>

        <!-- Images Card -->
        <div v-if="service.image || (service.gallery && service.gallery.length)" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.service?.images || 'Images' }}</h2>
          </div>
          <div class="p-3">
            <div v-if="service.image" class="mb-4">
              <label class="text-xs font-medium text-muted-foreground mb-2 block">{{ t.service?.main_image || 'Main Image' }}</label>
              <img :src="service.image" :alt="service.title" class="w-full max-w-md h-64 object-cover rounded-lg border border-border" />
            </div>
            <div v-if="service.gallery && service.gallery.length">
              <label class="text-xs font-medium text-muted-foreground mb-2 block">{{ t.service?.gallery || 'Gallery' }}</label>
              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <img
                  v-for="(img, index) in service.gallery"
                  :key="index"
                  :src="img.url || img"
                  class="w-full aspect-square object-cover rounded-lg border border-border"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.service.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>
          <Link
            v-if="service.slug"
            :href="route('admin.service.edit', service.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            {{ t.service?.edit || 'Edit Service' }}
          </Link>
        </div>
      </div>
    </div>
  </ServiceLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import ServiceLayout from "./ServiceLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed } from 'vue';
import { usePriceFormat } from '@/composables/usePriceFormat';

const { formatPrice } = usePriceFormat();

const props = defineProps({
  service: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const hasDiscount = computed(() => {
  return props.service.old_price && props.service.new_price && props.service.old_price > props.service.new_price;
});

const discountPercentage = computed(() => {
  if (!hasDiscount.value) return 0;
  return Math.round(((props.service.old_price - props.service.new_price) / props.service.old_price) * 100);
});

const tagLabels = {
  new: 'New',
  soon: 'Coming Soon',
  available: 'Available',
};

const tagBadgeClasses = {
  new: 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20',
  soon: 'bg-amber-500/10 text-amber-500 ring-amber-500/20',
  available: 'bg-sky-500/10 text-sky-500 ring-sky-500/20',
};

const tagLabel = (tag) => tagLabels[tag] || tag;
const tagBadgeClass = (tag) => tagBadgeClasses[tag] || 'bg-muted/50 text-muted-foreground ring-border';

const tagChipStyle = (tagItem) => {
  const color = tagItem.color || '#6B7280';
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
};
</script>

<style lang="scss" scoped></style>
