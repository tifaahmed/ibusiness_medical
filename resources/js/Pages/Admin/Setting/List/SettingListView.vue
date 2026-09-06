<template>
  <SettingLayout>
    <div class="flex flex-col h-full lg:h-auto w-full max-w-full overflow-x-hidden">
      <div class="flex-shrink-0 space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 pb-2 sm:pb-3 md:pb-4 lg:pb-4 w-full max-w-full overflow-hidden">
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-2 sm:gap-3 md:gap-4 rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm overflow-hidden w-full max-w-full">
          <div data-slot="card-header" class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 w-full overflow-hidden gap-2 sm:gap-4">
            <div data-slot="card-title" class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden min-w-0 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sm:w-6 sm:h-6 flex-shrink-0">
                  <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <span class="text-sm sm:text-base truncate block min-w-0">{{ t.setting?.management || 'Site Settings' }}</span>
              </div>
            </div>
            <Link
              v-if="canWrite"
              :href="route('admin.setting.create')"
              data-slot="button"
              class="inline-flex items-center cursor-pointer justify-center gap-1.5 sm:gap-2 whitespace-nowrap rounded-md text-xs sm:text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-8 sm:h-9 px-2 sm:px-3 md:px-4 py-2 flex-shrink-0 btn-golden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 sm:h-4 sm:w-4">
                <path d="M5 12h14"></path>
                <path d="M12 5v14"></path>
              </svg>
              <span class="hidden sm:inline">{{ t.setting?.add_new || 'Add New Setting' }}</span>
              <span class="sm:hidden">{{ t.common?.add || 'Add' }}</span>
            </Link>
          </div>

          <div data-slot="card-content" class="px-2 sm:px-4 md:px-6 space-y-2 sm:space-y-3 md:space-y-4 w-full max-w-full overflow-hidden min-w-0">
            <p class="text-xs text-muted-foreground">
              {{ t.setting?.intro || 'The details the site shows about itself — name, phone, address, logo and links. Each row is read by its key, so adding a new detail is adding a row.' }}
            </p>
            <SettingListFilterContent
              :initial-filters="filters"
              :value-types="valueTypes"
              @filter-change="handleFilterChange"
            />
          </div>
        </div>
      </div>

      <div class="flex-1 min-h-0 lg:min-h-fit w-full max-w-full px-2 sm:px-3 md:px-4 lg:px-6 pb-2 sm:pb-3 md:pb-4 lg:pb-6 overflow-hidden lg:overflow-visible">
        <SettingListTable :settings="settings" @delete="handleDelete" />
      </div>
    </div>
  </SettingLayout>
</template>

<script setup>
import SettingLayout from "../SettingLayout.vue";
import SettingListFilterContent from "./SettingListFilterContent.vue";
import SettingListTable from "./SettingListTable.vue";
import { useSettingStore } from "../Stores/SettingStore";
import { Link, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();
const canWrite = computed(() => can('manage settings'));

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const props = defineProps({
  settings: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({
      search: '',
      value_type: '',
      sort: 'key'
    })
  },
  valueTypes: {
    type: Array,
    default: () => []
  }
});

const settingStore = useSettingStore();
settingStore.setSettings(props.settings);

const settings = computed(() => props.settings);

const filters = ref(props.filters || { search: '', value_type: '', sort: 'key' });

const handleDelete = (id) => {
  settingStore.confirmDelete(id);
};

const handleFilterChange = (newFilters) => {
  filters.value = newFilters;
};
</script>
