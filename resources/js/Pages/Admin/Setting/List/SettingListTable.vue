<template>
  <div data-slot="card" class="bg-card text-card-foreground flex flex-col h-full lg:h-auto rounded-xl border border-border shadow-sm">
    <div v-if="settings?.data?.length > 0" class="flex flex-col h-full lg:h-auto min-h-0 lg:min-h-fit">
      <div class="flex-1 min-h-0 lg:min-h-fit overflow-y-auto lg:overflow-y-visible overflow-x-auto">
        <div data-slot="table-container" class="relative w-full py-3 sm:py-4">
          <table data-slot="table" class="w-full caption-bottom text-xs sm:text-sm min-w-full">
            <thead data-slot="table-header" class="[&_tr]:border-b [&_tr]:border-border">
              <tr data-slot="table-row" class="hover:bg-muted/50 border-b border-border transition-colors">
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-start align-middle font-medium whitespace-nowrap min-w-[180px] sm:min-w-[240px]">
                  {{ t.setting?.item || 'Setting' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-start align-middle font-medium whitespace-nowrap min-w-[200px]">
                  {{ t.setting?.value || 'Value' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-24 sm:w-32 text-center hidden md:table-cell">
                  {{ t.setting?.value_type || 'Type' }}
                </th>
                <th data-slot="table-head" class="text-foreground h-9 sm:h-10 px-2 sm:px-3 align-middle font-medium whitespace-nowrap w-20 sm:w-28 text-center">
                  {{ t.common?.actions || 'Actions' }}
                </th>
              </tr>
            </thead>
            <tbody data-slot="table-body" class="[&_tr:last-child]:border-0">
              <tr
                v-for="setting in settings.data"
                :key="setting.id"
                data-slot="table-row"
                class="border-b border-border transition-colors hover:bg-muted/50"
              >
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle">
                  <div class="flex flex-col gap-1 min-w-0">
                    <component
                      :is="canWrite ? Link : 'span'"
                      v-bind="canWrite ? { href: getEditRoute(setting.id) } : {}"
                      class="font-medium truncate"
                      :class="canWrite ? 'hover:text-primary' : ''"
                    >
                      {{ nameIn(setting.name, locale) || setting.slug }}
                    </component>
                    <!-- The key is what code reads the row by, so it is shown
                         as prominently as the label. -->
                    <code class="text-[11px] text-muted-foreground font-mono truncate" dir="ltr">{{ setting.slug }}</code>
                  </div>
                </td>
                <td data-slot="table-cell" class="p-2 sm:p-3 align-middle max-w-[320px]">
                  <img
                    v-if="setting.value_type === 'image' && setting.image_url"
                    :src="setting.image_url"
                    :alt="nameIn(setting.name, locale) || setting.slug"
                    class="h-10 w-auto max-w-[120px] object-contain rounded border border-border bg-white/5 p-1"
                    loading="lazy"
                  />
                  <a
                    v-else-if="setting.value_type === 'url' && setting.value"
                    :href="setting.value"
                    target="_blank"
                    rel="noopener"
                    class="text-primary hover:underline truncate block"
                    dir="ltr"
                  >{{ setting.value }}</a>
                  <span
                    v-else-if="setting.value"
                    class="truncate block text-muted-foreground"
                    :dir="isLtrValue(setting.value_type) ? 'ltr' : undefined"
                    :title="setting.value"
                  >{{ displayValue(setting) }}</span>
                  <span v-else class="text-xs text-muted-foreground/60 italic">{{ t.setting?.empty || 'Not set' }}</span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center hidden md:table-cell">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                    :style="typeBadgeStyle(setting.value_type)"
                  >
                    {{ typeLabel(setting.value_type) }}
                  </span>
                </td>
                <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap text-center">
                  <div class="flex items-center justify-center gap-2">
                    <Link
                      v-if="canWrite"
                      :href="getEditRoute(setting.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-emerald-bright hover:!bg-emerald-bright/10 hover:!text-emerald-bright"
                      :title="t.common?.edit || 'Edit'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
                      </svg>
                    </Link>
                    <button
                      v-if="canWrite"
                      @click="$emit('delete', setting.id)"
                      class="inline-flex items-center cursor-pointer justify-center whitespace-nowrap text-sm font-medium transition-all border bg-background shadow-xs hover:bg-destructive hover:text-white dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 text-destructive hover:!bg-destructive/10 hover:!text-destructive"
                      :title="t.common?.delete || 'Delete'"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                      </svg>
                    </button>
                    <span v-if="!canWrite" class="text-xs text-muted-foreground">—</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="border-t border-border flex-shrink-0">
        <div class="border-t border-border/50 px-3 sm:px-4 md:px-6 py-2.5 sm:py-3 md:py-4 w-full">
          <div class="flex flex-row items-center justify-between gap-2 sm:gap-3 lg:gap-4 w-full flex-wrap">
            <div class="text-xs sm:text-sm text-muted-foreground order-1 flex-shrink-0 min-w-0">
              <span class="hidden sm:inline">{{ (t.common?.showing_results || 'Showing :from to :to of :total results').replace(':from', settings.meta?.from || 0).replace(':to', settings.meta?.to || 0).replace(':total', settings.meta?.total || 0) }}</span>
              <span class="sm:hidden">{{ settings.meta?.from || 0 }}-{{ settings.meta?.to || 0 }}/{{ settings.meta?.total || 0 }}</span>
            </div>
            <div class="flex items-center gap-2 order-2 flex-shrink-0">
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap hidden sm:inline">{{ t.common?.rows_per_page || 'Rows per page' }}</p>
              <p class="text-xs sm:text-sm font-medium whitespace-nowrap sm:hidden">{{ t.common?.per_page || 'Per page' }}</p>
              <PerPageSelect
                :model-value="settings.meta?.per_page || 15"
                @update:model-value="handlePerPageChange"
              />
            </div>
            <div class="order-3 flex-shrink-0 min-w-0">
              <Pagination
                v-if="settings?.meta?.links?.length > 0"
                :links="settings?.meta?.links"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else data-slot="card-content" class="p-12">
      <div class="text-center max-w-md mx-auto space-y-6">
        <div class="inline-flex items-center justify-center w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-golden-yellow/10 border border-golden-yellow/20 mb-6 shadow-lg shadow-golden-yellow/10">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 sm:w-12 sm:h-12 text-golden-yellow subtle-float">
            <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold mb-1 text-foreground">{{ t.setting?.not_found || 'No Settings Found' }}</h3>
        <p class="text-muted-foreground text-sm sm:text-base leading-relaxed">{{ t.setting?.not_found_message || 'No settings match your current filters.' }}</p>
        <Link
          v-if="canWrite"
          :href="route('admin.setting.create')"
          data-slot="button"
          class="inline-flex items-center cursor-pointer justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2 btn-golden"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
            <path d="M5 12h14"></path>
            <path d="M12 5v14"></path>
          </svg>
          {{ t.setting?.add || 'Add Setting' }}
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import Pagination from "@/Pages/_components/Pagination.vue";
import PerPageSelect from '@/Components/ui/PerPageSelect.vue';
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();
// Editing and deleting are writes: hidden from read-only accounts, and
// refused by the routes behind them either way.
const canWrite = computed(() => can('manage settings'));

defineProps({
  settings: {
    type: Object,
    required: true
  }
});

defineEmits(['delete']);

const page = usePage();
const locale = page.props.locale || 'ar';
const t = computed(() => page.props.translations?.admin || {});

const nameIn = (name, lang) => {
  if (typeof name === 'string') return name;
  if (typeof name === 'object' && name !== null) return name[lang] || name.ar || name.en || '';
  return '';
};

// Keys, links, numbers and addresses read left-to-right whatever the page
// direction is; a phone number reversed by RTL layout is unreadable.
const LTR_TYPES = ['url', 'email', 'phone', 'number', 'json', 'image'];
const isLtrValue = (type) => LTR_TYPES.includes(type);

const typeLabel = (type) => t.value.setting?.types?.[type] || type;

const TYPE_COLORS = {
  string: '#6B7280',
  text: '#6B7280',
  number: '#8B5CF6',
  boolean: '#F59E0B',
  url: '#3B82F6',
  email: '#06B6D4',
  phone: '#10B981',
  image: '#EC4899',
  json: '#EF4444',
};

const typeBadgeStyle = (type) => {
  const c = TYPE_COLORS[type] || '#6B7280';
  return { backgroundColor: `${c}1A`, color: c, borderColor: `${c}33` };
};

// Booleans read as a word rather than the "1" / "0" actually stored, and a
// long text or JSON blob is cut so one row cannot swallow the table.
const displayValue = (setting) => {
  if (setting.value_type === 'boolean') {
    const on = setting.value === '1' || setting.value === 'true';
    return on ? (t.value.common?.yes || 'Yes') : (t.value.common?.no || 'No');
  }
  const value = String(setting.value ?? '');
  return value.length > 120 ? `${value.slice(0, 120)}…` : value;
};

const getEditRoute = (id) => {
  if (!id) return route('admin.setting.list');
  try {
    return route('admin.setting.edit', id);
  } catch (error) {
    console.error('Error generating edit route:', error);
    return route('admin.setting.list');
  }
};

const handlePerPageChange = (event) => {
  const currentUrl = new URL(window.location.href);
  // The shared picker emits the value; a native select would send an event.
  currentUrl.searchParams.set('per_page', event?.target?.value ?? event);
  currentUrl.searchParams.set('page', '1');
  router.visit(currentUrl.toString(), {
    preserveState: false,
    preserveScroll: false,
  });
};
</script>
