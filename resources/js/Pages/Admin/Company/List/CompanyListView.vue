<template>
  <CompanyLayout>
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon sm:w-6 sm:h-6 flex-shrink-0">
                  <rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                </svg>
                <span class="text-sm sm:text-base truncate">Companies Management</span>
              </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
              <a
                :href="route('admin.company.export')"
                data-slot="button"
                title="Export all companies to Excel"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-accent h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 text-foreground"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-emerald-600">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <path d="m7 10 5 5 5-5"/>
                  <path d="M12 15V3"/>
                </svg>
                <span class="hidden sm:inline">Export Excel</span>
                <span class="sm:hidden">Export</span>
              </a>
              <Link
                :href="route('admin.company.import')"
                data-slot="button"
                title="Import companies from an Excel file"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all border bg-background shadow-xs hover:bg-accent h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 text-foreground"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <path d="M17 8l-5-5-5 5"/>
                  <path d="M12 3v12"/>
                </svg>
                <span class="hidden sm:inline">Import Excel</span>
                <span class="sm:hidden">Import</span>
              </Link>
              <Link
                :href="route('admin.company.create')"
                data-slot="button"
                class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 btn-golden"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                  <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                <span class="hidden sm:inline">Add New Company</span>
                <span class="sm:hidden">Add</span>
              </Link>
            </div>
          </div>
          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <CompanyListFilterContent :initial-filters="filters" @filter-change="handleFilterChange" />
          </div>
        </div>
      </div>

      <div class="w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6">
        <CompanyListTable :companies="companies" @delete="handleDelete" />
      </div>
    </div>
  </CompanyLayout>
</template>

<script setup>
import CompanyLayout from "../CompanyLayout.vue";
import CompanyListFilterContent from "./CompanyListFilterContent.vue";
import CompanyListTable from "./CompanyListTable.vue";
import { useCompanyStore } from "../Stores/CompanyStore";
import { Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps({
  companies: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: '' }) },
});

const companyStore = useCompanyStore();
companyStore.setCompanies(props.companies);

const companies = computed(() => props.companies);
const filters = ref(props.filters || { search: '' });

const handleFilterChange = (newFilters) => { filters.value = newFilters; };

const handleDelete = (companySlug) => {
  companyStore.confirmDelete(companySlug);
};
</script>
