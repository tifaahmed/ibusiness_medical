<template>
  <FacilityLayout>
    <Breadcrumb
      :title="t.facility?.view || 'View Facility'"
      :breadcrumbs="[
        { label: t.breadcrumbs?.facilities || 'Facilities', link: route('admin.facility.list'), active: false },
        { label: t.facility?.view || 'View Facility', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Facility Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.facility?.information || 'Facility Information' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.name || 'Name' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facility.name) }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.slug || 'Slug' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ facility.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.id || 'ID' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ facility.id }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.facility_branch?.branches || 'Branches' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ facility.branches_count || 0 }}</p>
              </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.facility_type?.label || 'Facility Type' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facility.facility_type?.name) || (t.common?.n_a || 'N/A') }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.governorate?.label || 'Governorate' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(facility.governorate?.name) || (t.common?.n_a || 'N/A') }}</p>
              </div>
            </div>
            <div v-if="facility.tags && facility.tags.length" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.service?.tags || 'Tags' }}</label>
              <div class="flex flex-wrap gap-2 mt-1">
                <span
                  v-for="tagItem in facility.tags"
                  :key="tagItem.id"
                  class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
                  :style="tagChipStyle(tagItem)"
                >
                  <span v-if="tagItem.icon">{{ tagItem.icon }}</span>
                  {{ tagItem.name }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Description Card -->
        <div v-if="getTranslatedName(facility.description)" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.facility?.description || 'Description' }}</h2>
          </div>
          <div class="p-3">
            <div class="prose prose-invert max-w-none text-sm text-white" v-html="getTranslatedName(facility.description)"></div>
          </div>
        </div>

        <!-- Branches Card -->
        <div v-if="facility.branches && facility.branches.length > 0" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.facility_branch?.branches || 'Branches' }} ({{ facility.branches.length }})</h2>
          </div>
          <div class="p-3">
            <div class="space-y-2">
              <div
                v-for="branch in facility.branches"
                :key="branch.id"
                class="border border-border rounded-lg p-3 hover:bg-muted/10 transition-colors"
              >
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.common?.name || 'Name' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(branch.name) || (t.facility_branch?.main_branch || 'Main Branch') }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.common?.address || 'Address' }}</label>
                    <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(branch.address) || (t.common?.n_a || 'N/A') }}</p>
                  </div>
                  <div>
                    <label class="text-xs font-medium text-muted-foreground">{{ t.common?.phone || 'Phone' }}</label>
                    <div v-if="getPhonesArray(branch.phone).length > 0" class="mt-0.5 space-y-1">
                      <p v-for="(phone, index) in getPhonesArray(branch.phone)" :key="index" class="text-sm font-medium text-white">
                        {{ phone }}
                      </p>
                    </div>
                    <p v-else class="text-sm font-medium mt-0.5 text-white">{{ t.common?.n_a || 'N/A' }}</p>
                  </div>
                  <div>
                    <Link
                      v-if="branch.slug"
                      :href="route('admin.facility-branch.show', branch.slug)"
                      class="text-primary hover:underline text-sm"
                    >
                      {{ t.common?.view_details || 'View Details' }} →
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
            :href="route('admin.facility.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>
          <Link
            v-if="facility.slug"
            :href="route('admin.facility.edit', facility.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            {{ t.facility?.edit || 'Edit Facility' }}
          </Link>
        </div>
      </div>
    </div>
  </FacilityLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import FacilityLayout from "./FacilityLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";

const props = defineProps({
  facility: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = computed(() => page.props.locale || 'ar');
const t = computed(() => page.props.translations?.admin || {});

const getTranslatedName = (name) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) {
    return name[locale.value] || name['ar'] || name['en'] || Object.values(name)[0] || '';
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

const tagChipStyle = (tagItem) => {
  const color = tagItem.color || '#6B7280';
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
};
</script>

<style lang="scss" scoped></style>


