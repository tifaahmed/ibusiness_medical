<template>
  <OfferLayout>


    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 relative z-10">
      <div class="mb-4">
        <div class="space-y-2"></div>
      </div>
      <div class="space-y-4">
        <div class="space-y-3">
          <form class="space-y-3" @submit.prevent="handleSubmit">
            <div class="space-y-3">
              <OfferForm :offer="offer" :facilities="facilities" :facility-branches="facilityBranches" />
            </div>

            <!-- Sticky Form Actions -->
            <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
              <div class="flex flex-col sm:flex-row p-4">
                <div class="flex-1"></div>
                <div class="flex gap-3 justify-end">
                  <Link
                    :href="route('admin.offer.list')"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                    type="button"
                  >
                    {{ t.common?.cancel || 'Cancel' }}
                  </Link>
                  <button
                    type="submit"
                    :disabled="offerStore.form.processing"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-2 btn-golden"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save w-4 h-4 mr-2">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    {{ isEditMode ? (t.offer?.update || 'Update Offer') : (t.offer?.create || 'Create Offer') }}
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </OfferLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import OfferLayout from "../OfferLayout.vue";
import { useOfferStore } from "../Stores/OfferStore";
import OfferForm from "./OfferForm.vue";
import { onMounted, computed, watch } from "vue";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  offer: {
    type: Object,
    default: null
  },
  facilities: {
    type: Array,
    default: () => []
  },
  facilityBranches: {
    type: Array,
    default: () => []
  }
});

const offerStore = useOfferStore();

// Determine if we're in edit mode
const isEditMode = computed(() => !!props.offer?.id);

// Initialize form based on mode
onMounted(() => {
  if (isEditMode.value) {
    offerStore.setOffer(props.offer);
  } else {
    offerStore.initializeForm();
  }
});

// Watch for changes in edit mode (prop updates)
watch(() => props.offer, (newOffer) => {
  if (newOffer && newOffer.id) {
    offerStore.setOffer(newOffer);
  }
}, { deep: true });

const handleSubmit = () => {
  if (isEditMode.value) {
    offerStore.updateOffer();
  } else {
    offerStore.submitForm();
  }
};
</script>

<style lang="scss" scoped></style>
