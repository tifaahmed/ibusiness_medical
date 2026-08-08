<template>
  <FacilityBranchLayout>
    <Breadcrumb
      title="View Facility Branch"
      :breadcrumbs="[
        { label: 'Facility Branches', link: route('admin.facility-branch.list'), active: false },
        { label: 'View Facility Branch', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Facility Branch Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">Facility Branch Information</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">Name</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facilityBranch.name) || 'Main Branch' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Slug</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ facilityBranch.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Phone</label>
                <div v-if="getPhonesArray(facilityBranch.phone).length > 0" class="mt-0.5 space-y-1">
                  <p v-for="(phone, index) in getPhonesArray(facilityBranch.phone)" :key="index" class="text-sm font-medium text-white">
                    {{ phone }}
                  </p>
                </div>
                <p v-else class="text-sm font-medium mt-0.5 text-white">N/A</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">ID</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ facilityBranch.id }}</p>
              </div>
            </div>
            <div class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">Address</label>
              <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facilityBranch.address) || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <!-- Parent Facility Card -->
        <div v-if="facilityBranch.facility" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">Parent Facility</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">Facility Name</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facilityBranch.facility.name) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Facility Type</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facilityBranch.facility.facility_type?.name) || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Governorate</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facilityBranch.facility.governorate?.name) || 'N/A' }}</p>
              </div>
              <div>
                <Link
                  v-if="facilityBranch.facility.slug"
                  :href="route('admin.facility.show', facilityBranch.facility.slug)"
                  class="text-primary hover:underline text-sm"
                >
                  View Facility →
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.facility-branch.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            Back to List
          </Link>
          <Link
            v-if="facilityBranch.slug"
            :href="route('admin.facility-branch.edit', facilityBranch.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit Facility Branch
          </Link>
        </div>
      </div>
    </div>
  </FacilityBranchLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import { usePage } from '@inertiajs/vue3';
import FacilityBranchLayout from "./FacilityBranchLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";

const props = defineProps({
  facilityBranch: {
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

const getPhonesArray = (phone) => {
  if (!phone) return [];
  if (typeof phone === 'string' && phone.trim() !== '') {
    return [phone.trim()];
  }
  if (Array.isArray(phone)) {
    return phone.filter(p => p && String(p).trim().length > 0).map(p => String(p).trim());
  }
  return [];
};
</script>

<style lang="scss" scoped></style>


