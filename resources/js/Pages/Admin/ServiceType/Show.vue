<template>
  <ServiceTypeLayout>
    <Breadcrumb
      :title="t.serviceType?.view || 'View Category'"
      :breadcrumbs="[
        { label: t.serviceType?.title || 'Service Categories', link: route('admin.service-type.list'), active: false },
        { label: t.serviceType?.view || 'View Category', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.serviceType?.information || 'Category Information' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.name_en || 'Name (EN)' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ serviceType.name?.en || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.name_ar || 'Name (AR)' }}</label>
                <p dir="rtl" class="text-sm font-medium mt-0.5 text-white">{{ serviceType.name?.ar || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.id || 'ID' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ serviceType.id }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.status || 'Status' }}</label>
                <p class="text-sm font-medium mt-0.5">
                  <span
                    v-if="serviceType.is_active"
                    class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-500 ring-1 ring-inset ring-emerald-500/20"
                  >
                    {{ t.common?.active || 'Active' }}
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center rounded-full bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive ring-1 ring-inset ring-destructive/20"
                  >
                    {{ t.common?.inactive || 'Inactive' }}
                  </span>
                </p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.services_count || 'Services' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ serviceType.services_count || 0 }}</p>
              </div>
            </div>
            <div v-if="serviceType.icon || serviceType.color" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
              <div v-if="serviceType.icon">
                <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.icon || 'Icon' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white text-2xl">{{ serviceType.icon }}</p>
              </div>
              <div v-if="serviceType.color">
                <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.color || 'Color' }}</label>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="w-6 h-6 rounded-full border border-border" :style="{ backgroundColor: serviceType.color }"></span>
                  <span class="text-sm font-medium text-white font-mono">{{ serviceType.color }}</span>
                </div>
              </div>
            </div>
            <div v-if="serviceType.description" class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.serviceType?.description || 'Description' }}</label>
              <p class="text-sm font-medium mt-0.5 text-white whitespace-pre-wrap">{{ serviceType.description }}</p>
            </div>
          </div>
        </div>

        <div v-if="serviceType.image" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.serviceType?.image || 'Image' }}</h2>
          </div>
          <div class="p-3">
            <img :src="serviceType.image" :alt="serviceType.name" class="w-full max-w-md h-64 object-cover rounded-lg border border-border" />
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.service-type.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>
          <Link
            v-if="serviceType.id"
            :href="route('admin.service-type.edit', serviceType.id)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            {{ t.serviceType?.edit || 'Edit Category' }}
          </Link>
        </div>
      </div>
    </div>
  </ServiceTypeLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import ServiceTypeLayout from "./ServiceTypeLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed } from 'vue';

const props = defineProps({
  serviceType: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
</script>

<style lang="scss" scoped></style>
