<template>
  <FacilityLayout>
    <div class="container mx-auto px-4 py-6 md:px-6 lg:px-8 max-w-7xl relative z-10">
      <div class="mb-4">
        <div class="space-y-2"></div>
      </div>
      <div class="space-y-4">
        <div class="space-y-3">
          <form class="space-y-3" @submit.prevent="handleSubmit">
            <div class="space-y-3">
              <FacilityForm :facility-types="facilityTypes" :facility="null" :tags="tags" />
              <FacilityBranchCard v-model="branches" :governorates="governorates" :cities="cities" />
            </div>

            <!-- Sticky Form Actions -->
            <div class="sticky bottom-0 z-10 bg-card border rounded-lg">
              <div class="flex flex-col sm:flex-row p-4">
                <div class="flex-1"></div>
                <div class="flex gap-3 justify-end">
                  <Link
                    :href="route('admin.facility.list')"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 order-2 sm:order-1"
                    type="button"
                  >
                    {{ t.common?.cancel || 'Cancel' }}
                  </Link>
                  <button
                    type="submit"
                    :disabled="facilityStore.form.processing"
                    data-slot="button"
                    class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 has-[>svg]:px-3 min-w-[140px] order-1 sm:order-2 btn-golden"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save w-4 h-4 mr-2">
                      <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                      <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                      <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                    </svg>
                    {{ t.facility?.create || 'Create Facility' }}
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { ref, onMounted, computed } from "vue";
import FacilityLayout from "../FacilityLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useFacilityStore } from "../Stores/FacilityStore";
import { FacilityForm, FacilityBranchCard } from "../_components/Form";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  facilityTypes: {
    type: Array,
    default: () => []
  },
  governorates: {
    type: Array,
    default: () => []
  },
  cities: {
    type: Array,
    default: () => []
  },
  tags: {
    type: Array,
    default: () => []
  }
});

const facilityStore = useFacilityStore();
const branches = ref([]);

onMounted(() => {
  facilityStore.initializeForm();
});

const handleSubmit = () => {
  facilityStore.submitForm(branches.value);
};
</script>

<style lang="scss" scoped></style>

