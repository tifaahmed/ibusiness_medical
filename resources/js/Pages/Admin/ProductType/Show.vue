<template>
  <ProductTypeLayout>
    <Breadcrumb
      :title="t.product_type?.view || 'View Product Type'"
      :breadcrumbs="[
        { label: t.breadcrumbs?.product_types || 'Product Types', link: route('admin.product-type.list'), active: false },
        { label: t.product_type?.view || 'View Product Type', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Product Type Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.product_type?.information || 'Product Type Information' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.name || 'Name' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(productType.name) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.slug || 'Slug' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ productType.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.id || 'ID' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ productType.id }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.product-type.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>
          <Link
            v-if="productType.slug"
            :href="route('admin.product-type.edit', productType.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            {{ t.product_type?.edit || 'Edit Product Type' }}
          </Link>
        </div>
      </div>
    </div>
  </ProductTypeLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import ProductTypeLayout from "./ProductTypeLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";

const props = defineProps({
  productType: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = page.props.locale || 'ar';
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale] || name['ar'] || name['en'] || Object.values(name)[0] || '';
  }
  return '';
};
</script>

<style lang="scss" scoped></style>
