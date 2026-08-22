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
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">Name</label>
                    <p class="text-sm font-medium mt-0.5">{{ getTranslatedName(product.name) }}</p>
                  </div>
                  <div v-if="product.short_subject">
                    <label class="text-xs font-medium text-muted-foreground">Short Description</label>
                    <p class="text-sm mt-0.5">{{ getTranslatedName(product.short_subject) }}</p>
                  </div>
                  <div class="flex gap-4">
                    <div v-if="product.new_price">
                      <label class="text-xs font-medium text-muted-foreground">New Price</label>
                      <p class="text-sm font-semibold mt-0.5 text-emerald-600 dark:text-emerald-400">{{ formatPrice(product.new_price) }}</p>
                    </div>
                    <div v-if="product.old_price">
                      <label class="text-xs font-medium text-muted-foreground">Old Price</label>
                      <p class="text-sm font-medium mt-0.5 text-muted-foreground line-through">{{ formatPrice(product.old_price) }}</p>
                    </div>
                  </div>
                  <div v-if="product.product_type">
                    <label class="text-xs font-medium text-muted-foreground">Product Type</label>
                    <p class="text-sm font-medium mt-0.5">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                        {{ getTranslatedName(product.product_type.name) }}
                      </span>
                    </p>
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
                    <img :src="product.large_image" :alt="getTranslatedName(product.name)" class="mt-1 w-full rounded-lg border border-border object-cover" />
                  </div>
                  <div v-if="product.small_image">
                    <label class="text-xs font-medium text-muted-foreground">Small Image</label>
                    <img :src="product.small_image" :alt="getTranslatedName(product.name)" class="mt-1 w-24 h-24 rounded-lg border border-border object-cover" />
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
                  v-for="(img, i) in product.gallery"
                  :key="i"
                  :src="img"
                  class="w-full h-32 rounded-lg border border-border object-cover"
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
  </ProductLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import ProductLayout from "./ProductLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";

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

const formatPrice = (price) => {
  if (price === null || price === undefined) return '';
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(price);
};
</script>
