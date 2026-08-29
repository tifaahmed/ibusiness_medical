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

      <div class="max-w-7xl mx-auto space-y-2 sm:space-y-3 md:space-y-4">
        <!-- One-click English cleanup for this product's name / short description / description. -->
        <div class="flex flex-wrap items-center justify-end gap-2">
          <p v-if="englishFixMessage" class="mr-auto text-xs text-muted-foreground">{{ englishFixMessage }}</p>
          <button
            type="button"
            :disabled="!englishFixEnabled || englishFixRunning"
            :title="englishFixEnabled ? 'Translate / fix empty or Arabic English fields' : 'Set GEMINI_API_KEY in your .env file to enable this'"
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

        <form @submit.prevent="handleSubmit('return')" class="space-y-2 sm:space-y-3 md:space-y-4">
          <div class="grid grid-cols-1 gap-2 sm:gap-3 md:gap-4">
            <div class="space-y-2 sm:space-y-3 md:space-y-4">
              <ProductForm
                :product-types="productTypes"
                :tags="tags"
                :slug="product.slug"
                :ai-enabled="seoAiEnabled"
                :existing-large-image="product.large_image"
                :existing-small-image="product.small_image"
                :existing-og-image="product.og_image"
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
import { Link, router, usePage } from "@inertiajs/vue3";
import { watch, computed, ref } from "vue";
import ProductLayout from "../ProductLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useProductStore } from "../Stores/ProductStore";
import { ProductForm } from "../_components/Form";
import { useNotification } from "@/composables/useNotification";

const props = defineProps({
  product: { type: Object, required: true },
  productTypes: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  seoAiEnabled: { type: Boolean, default: false },
  englishFixEnabled: { type: Boolean, default: false },
});

const productStore = useProductStore();

const englishFixRunning = ref(false);
const englishFixMessage = ref('');

const fixEnglish = async () => {
  if (!props.englishFixEnabled || englishFixRunning.value) return;

  englishFixRunning.value = true;
  englishFixMessage.value = '';
  try {
    const { data } = await axios.post(route('admin.product.english.fix', props.product.slug));
    const applied = data?.applied?.length || 0;

    if (applied === 0) {
      useNotification().info('No English fields needed fixing.');
    } else {
      useNotification().success(`Fixed ${applied} English field(s). Reloading…`);
      router.reload({ only: ['product'] });
    }

    if (data?.errors?.length) {
      englishFixMessage.value = data.errors.join(' ');
    }
  } catch (error) {
    useNotification().error(error?.response?.data?.message || 'Could not fix the English fields. Please try again.');
  } finally {
    englishFixRunning.value = false;
  }
};

watch(() => props.product, (newProduct) => {
  if (newProduct && newProduct.id) {
    productStore.setProduct(newProduct);
  }
}, { immediate: true, deep: true });

const handleSubmit = (afterSave = 'return') => {
  productStore.updateForm(props.product.slug, afterSave);
};
</script>
