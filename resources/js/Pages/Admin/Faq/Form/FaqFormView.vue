<template>
  <FaqLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="space-y-4">
        <form class="space-y-3" @submit.prevent="handleSubmit">
          <FaqForm :faq="faq" />

          <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
            <div class="flex flex-col sm:flex-row p-4">
              <div class="flex-1"></div>
              <div class="flex gap-3 justify-end">
                <Link
                  :href="route('admin.faq.list')"
                  data-slot="button"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                  type="button"
                >
                  {{ t.common?.cancel || 'Cancel' }}
                </Link>
                <button
                  type="submit"
                  :disabled="faqStore.form.processing"
                  data-slot="button"
                  class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-2 btn-golden"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-2">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                  </svg>
                  {{ isEditMode ? (t.faq?.update || 'Update FAQ') : (t.faq?.create || 'Create FAQ') }}
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </FaqLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import FaqLayout from "../FaqLayout.vue";
import { useFaqStore } from "../Stores/FaqStore";
import FaqForm from "./FaqForm.vue";
import { onMounted, computed, watch } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  faq: {
    type: Object,
    default: null,
  },
});

const faqStore = useFaqStore();

const isEditMode = computed(() => !!props.faq?.id);

onMounted(() => {
  if (isEditMode.value) {
    faqStore.setFaq(props.faq);
  } else {
    faqStore.initializeForm();
  }
});

watch(() => props.faq, (newFaq) => {
  if (newFaq && newFaq.id) faqStore.setFaq(newFaq);
}, { deep: true });

const handleSubmit = () => {
  if (isEditMode.value) {
    faqStore.updateFaq();
  } else {
    faqStore.submitForm();
  }
};
</script>
