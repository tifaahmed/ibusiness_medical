<template>
  <TagLayout>
    <Breadcrumb
      :title="t.tag?.view || 'View Tag'"
      :breadcrumbs="[
        { label: t.tag?.title || 'Tags', link: route('admin.tag.list'), active: false },
        { label: t.tag?.view || 'View Tag', link: '#', active: true },
      ]"
    />

    <div class="max-w-7xl mx-auto mt-2">
      <div class="space-y-3">
        <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 border-b border-border">
            <h2 class="text-lg font-semibold text-white">{{ t.tag?.information || 'Tag Information' }}</h2>
          </div>
          <div class="p-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.tag?.name_en || 'Name (English)' }}</label>
                <p dir="ltr" class="text-sm font-medium mt-0.5 text-white break-words">{{ nameIn('en') || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.tag?.name_ar || 'Name (Arabic)' }}</label>
                <p dir="rtl" class="text-sm font-medium mt-0.5 text-white break-words">{{ nameIn('ar') || t.common?.n_a || 'N/A' }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.common?.id || 'ID' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">#{{ tag.id }}</p>
              </div>
              <div>
                <label class="text-xs font-medium text-muted-foreground">{{ t.tag?.services_count || 'Services' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white">{{ tag.services_count || 0 }}</p>
              </div>
            </div>
            <div v-if="tag.icon || tag.color" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
              <div v-if="tag.icon">
                <label class="text-xs font-medium text-muted-foreground">{{ t.tag?.icon || 'Icon' }}</label>
                <p class="text-sm font-medium mt-0.5 text-white text-2xl">{{ tag.icon }}</p>
              </div>
              <div v-if="tag.color">
                <label class="text-xs font-medium text-muted-foreground">{{ t.tag?.color || 'Color' }}</label>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="w-6 h-6 rounded-full border border-border" :style="{ backgroundColor: tag.color }"></span>
                  <span class="text-sm font-medium text-white font-mono">{{ tag.color }}</span>
                </div>
              </div>
            </div>
            <div class="mt-3">
              <label class="text-xs font-medium text-muted-foreground">{{ t.tag?.preview || 'Preview' }}</label>
              <div class="mt-1">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset"
                  :style="previewStyle"
                >
                  <span v-if="tag.icon">{{ tag.icon }}</span>
                  {{ nameIn(locale) || nameIn('ar') || nameIn('en') }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 justify-end pt-2">
          <Link
            :href="route('admin.tag.list')"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M15 18l-6-6 6-6"></path>
            </svg>
            {{ t.common?.back_to_list || 'Back to List' }}
          </Link>
          <Link
            v-if="tag.id"
            :href="route('admin.tag.edit', tag.id)"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 px-3 py-1.5"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            {{ t.tag?.edit || 'Edit Tag' }}
          </Link>
        </div>
      </div>
    </div>
  </TagLayout>
</template>

<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import TagLayout from "./TagLayout.vue";
import { Breadcrumb } from "@/Pages/Admin/Layout/Layout.js";
import { computed } from 'vue';

const props = defineProps({
  tag: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const locale = page.props.locale || 'ar';
const t = computed(() => page.props.translations?.admin || {});

// One specific locale, with no fallback: both names are shown, so a missing
// translation must read as missing rather than echo the other language.
const nameIn = (lang) => {
  const name = props.tag.name;
  if (typeof name === 'string') return lang === locale ? name : '';
  if (typeof name === 'object' && name !== null) return name[lang] || '';
  return '';
};

const previewStyle = computed(() => {
  const color = props.tag.color || '#6B7280';
  return {
    backgroundColor: `${color}1A`,
    color,
    borderColor: `${color}33`,
  };
});
</script>

<style lang="scss" scoped></style>
