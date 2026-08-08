<template>
  <GovernorateLayout>
    <Breadcrumb
      title="View Governorate"
      :breadcrumbs="[
        { label: 'Governorates', link: route('admin.governorate.list'), active: false },
        { label: 'View Governorate', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Governorate Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">Governorate Information</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">Name</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(governorate.name) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Slug</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ governorate.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Facilities Count</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ governorate.facilities_count || 0 }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">ID</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ governorate.id }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Facilities Card -->
        <div v-if="governorate.facilities && governorate.facilities.length > 0" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">Facilities ({{ governorate.facilities.length }})</h2>
          </div>
          <div class="p-3">
            <div class="space-y-2">
              <div
                v-for="facility in governorate.facilities"
                :key="facility.id"
                class="border border-border rounded-lg p-3 hover:bg-muted/10 transition-colors"
              >
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">Name</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facility.name) }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">Type</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facility.facility_type?.name) || 'N/A' }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">Phone</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ facility.phone || 'N/A' }}</p>
                  </div>
                  <div>
                    <Link
                      v-if="facility.slug"
                      :href="route('admin.facility.show', facility.slug)"
                      class="text-primary hover:underline text-sm"
                    >
                      View Details →
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.governorate.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            Back to List
          </Link>
          <Link
            v-if="governorate.slug"
            :href="route('admin.governorate.edit', governorate.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit Governorate
          </Link>
        </div>
      </div>
    </div>
  </GovernorateLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import { usePage } from '@inertiajs/vue3';
import GovernorateLayout from "./GovernorateLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";

const props = defineProps({
  governorate: {
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
</script>

<style lang="scss" scoped></style>



