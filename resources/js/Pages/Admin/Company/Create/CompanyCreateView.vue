<template>
  <CompanyLayout>
    <div class="space-y-4 p-2 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="Create Company"
        :breadcrumbs="[{ label: 'Companies', link: route('admin.company.list'), active: false }, { label: 'Create Company', link: '#', active: true }]"
      />
      <div class="max-w-7xl mx-auto">
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <CompanyForm />
          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row p-4 gap-3">
              <div class="flex-1"></div>
              <div class="flex gap-3 justify-end">
                <Link :href="route('admin.company.list')" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2">
                  Cancel
                </Link>
                <button type="submit" :disabled="companyStore.form.processing" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 min-w-[140px]">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/>
                    <path d="M7 3v4a1 1 0 0 0 1 1h7"/>
                  </svg>
                  Create Company
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </CompanyLayout>
</template>

<script setup>
import { Link } from "@inertiajs/vue3";
import { onMounted } from "vue";
import CompanyLayout from "../CompanyLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { useCompanyStore } from "../Stores/CompanyStore";
import { CompanyForm } from "../_components/Form";

const companyStore = useCompanyStore();
onMounted(() => companyStore.initializeForm());
const handleSubmit = () => companyStore.submitForm();
</script>
