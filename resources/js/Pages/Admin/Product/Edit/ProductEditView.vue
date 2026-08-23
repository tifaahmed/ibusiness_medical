<template>
  <ProductLayout>
    <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Edit Product"
        :breadcrumbs="[
          { label: 'Products', link: route('admin.product.list'), active: false },
          { label: 'Edit', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto">
        <form @submit.prevent="handleSubmit('return')" class="space-y-2 sm:space-y-3 md:space-y-4">
          <div class="grid grid-cols-1 gap-2 sm:gap-3 md:gap-4">
            <div class="space-y-2 sm:space-y-3 md:space-y-4">
              <ProductForm
                :product-types="productTypes"
                :tags="tags"
                :slug="product.slug"
                :existing-large-image="product.large_image"
                :existing-small-image="product.small_image"
                :existing-gallery="product.gallery"
              />
            </div>
          </div>

          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row p-2 sm:p-3 md:p-4 gap-2 sm:gap-3">
              <div class="flex-1"></div>
              <div class="flex flex-wrap gap-2 sm:gap-3 justify-end">
                <Link
                  :href="route('admin.product.list')"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
                >
                  Cancel
                </Link>
                <button
                  type="button"
                  :disabled="productStore.isLoading"
                  @click="handleSubmit('stay')"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                  </svg>
                  Update &amp; Stay
                </button>
                <button
                  type="button"
                  :disabled="productStore.isLoading"
                  @click="handleSubmit('show')"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  Update &amp; Show
                </button>
                <button
                  type="submit"
                  :disabled="productStore.isLoading"
                  class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px]"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M9 14 4 9l5-5"></path>
                    <path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11"></path>
                  </svg>
                  {{ productStore.isLoading ? 'Updating…' : 'Update & Return' }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </ProductLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { watch, computed } from "vue";
import ProductLayout from "../ProductLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useProductStore } from "../Stores/ProductStore";
import { ProductForm } from "../_components/Form";

const props = defineProps({
  product: { type: Object, required: true },
  productTypes: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
});

const productStore = useProductStore();

watch(() => props.product, (newProduct) => {
  if (newProduct && newProduct.id) {
    productStore.setProduct(newProduct);
  }
}, { immediate: true, deep: true });

const handleSubmit = (afterSave = 'return') => {
  productStore.updateForm(props.product.slug, afterSave);
};
</script>
