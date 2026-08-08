<template>
  <GovernorateLayout>
    <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        :title="t.governorate?.edit || 'Edit Governorate'"
        :breadcrumbs="[
          { label: t.common?.governorates || 'Governorates', link: route('admin.governorate.list'), active: false },
          { label: t.governorate?.edit || 'Edit Governorate', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto">
        <form @submit.prevent="handleSubmit" class="space-y-2 sm:space-y-3 md:space-y-4">
          <div class="grid grid-cols-1 gap-2 sm:gap-3 md:gap-4">
            <div class="space-y-2 sm:space-y-3 md:space-y-4">
              <GovernorateForm />
              <CitiesCard v-model="cities" />
            </div>
          </div>

          <!-- Sticky Form Actions -->
          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row p-2 sm:p-3 md:p-4 gap-2 sm:gap-3">
              <div class="flex-1"></div>
              <div class="flex gap-2 sm:gap-3 justify-end">
              <Link
                :href="route('admin.governorate.list')"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
              >
                {{ t.common?.cancel || 'Cancel' }}
              </Link>
              <button
                type="submit"
                :disabled="governorateStore.form.processing"
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px]"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                  <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                  <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                  <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                </svg>
                {{ t.governorate?.update || 'Update Governorate' }}
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
    </div>
  </GovernorateLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { watch, computed } from "vue";
import GovernorateLayout from "../GovernorateLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useGovernorateStore } from "../Stores/GovernorateStore";
import { GovernorateForm, CitiesCard } from "../_components/Form";

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  governorate: {
    type: Object,
    required: true,
  },
});

const governorateStore = useGovernorateStore();

const cities = computed({
  get: () => governorateStore.form.cities || [],
  set: (value) => { governorateStore.form.cities = value; }
});

// Set governorate data when props are received
// Use watch with immediate to ensure it runs on mount and when props change
watch(() => props.governorate, (newGovernorate) => {
  if (newGovernorate && newGovernorate.id) {
    governorateStore.setGovernorate(newGovernorate);
  }
}, { immediate: true, deep: true });

const handleSubmit = () => {
  governorateStore.updateGovernorate();
};
</script>

<style lang="scss" scoped></style>

