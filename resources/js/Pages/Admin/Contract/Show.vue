<template>
  <ContractLayout>
    <Breadcrumb
      title="View Contract"
      :breadcrumbs="[
        { label: 'Contracts', link: route('admin.contract.list'), active: false },
        { label: 'View Contract', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <!-- Contract Information Card -->
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">Contract Information</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">Name</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ getTranslatedName(contract.name) || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Slug</label>
                <p class="text-sm font-medium mt-0.5 text-white font-mono text-xs">{{ contract.slug }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Sort Order</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ contract.sort_order }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">Status</label>
                <p class="text-sm font-medium mt-0.5">
                  <span :class="[
                    'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                    contract.is_active ? 'bg-emerald-500/10 text-emerald-500 ring-emerald-500/20' : 'bg-red-500/10 text-red-500 ring-red-500/20'
                  ]">
                    {{ contract.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </p>
              </div>
            </div>
            <div v-if="getTranslatedName(contract.description)" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">Description</label>
              <p class="text-sm font-medium mt-0.5 text-white whitespace-pre-wrap">{{ getTranslatedName(contract.description) }}</p>
            </div>
          </div>
        </div>

        <!-- Image Card -->
        <div v-if="contract.image" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">Image</h2>
          </div>
          <div class="p-3">
            <img :src="contract.image" alt="Contract Image" class="w-64 h-64 object-cover rounded-lg border border-border" />
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.contract.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            Back to List
          </Link>
          <Link
            v-if="contract.slug"
            :href="route('admin.contract.edit', contract.slug)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit Contract
          </Link>
        </div>
      </div>
    </div>
  </ContractLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import { usePage } from '@inertiajs/vue3';
import ContractLayout from "./ContractLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";

const props = defineProps({
  contract: {
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
