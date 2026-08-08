<template>
  <AppLayout :title="t.title || 'Database Backups'">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <!-- Header -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0 text-blue-400">
                  <ellipse cx="12" cy="5" rx="9" ry="3" />
                  <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                  <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">{{ t.title || 'Database Backups' }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Backups Table -->
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div v-if="backups.length > 0">
            <div class="overflow-x-auto">
              <table class="w-full caption-bottom text-xs sm:text-sm">
                <thead class="[&_tr]:border-b [&_tr]:border-border">
                  <tr class="border-b border-border transition-colors">
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.file_name || 'File' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.size || 'Size' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-left align-middle font-medium whitespace-nowrap">{{ t.date || 'Created' }}</th>
                    <th class="text-foreground h-9 sm:h-10 px-2 sm:px-3 text-center align-middle font-medium whitespace-nowrap">{{ t.download || 'Download' }}</th>
                  </tr>
                </thead>
                <tbody class="[&_tr:last-child]:border-0">
                  <tr v-for="backup in backups" :key="backup.name" class="border-b border-border transition-colors hover:bg-muted/50">
                    <td class="p-2 sm:p-3 align-middle">
                      <span class="text-foreground text-xs font-mono">{{ backup.name }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <span class="text-foreground text-xs">{{ formatSize(backup.size) }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle whitespace-nowrap">
                      <span class="text-foreground text-xs">{{ formatDate(backup.created_at) }}</span>
                    </td>
                    <td class="p-2 sm:p-3 align-middle text-center">
                      <a
                        :href="route('admin.database-backup.download', backup.name)"
                        class="cursor-pointer inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-xs font-medium transition-all border bg-background shadow-xs hover:bg-primary hover:text-primary-foreground h-7 px-2"
                        :title="t.download || 'Download'"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                          <path d="M7 10l5 5 5-5" /><path d="M12 15V3" />
                        </svg>
                        {{ t.download || 'Download' }}
                      </a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else class="p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-500/10 border border-blue-500/20 mb-6">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-400">
                <ellipse cx="12" cy="5" rx="9" ry="3" />
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-1 text-foreground">{{ t.not_found || 'No backup available' }}</h3>
            <p class="text-muted-foreground text-sm">{{ t.not_found_message || 'No database backup file was found in storage.' }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.database_backup || {});

defineProps({
  backups: { type: Array, default: () => [] },
});

const formatSize = (bytes) => {
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
};

const formatDate = (timestamp) => {
  return new Date(timestamp * 1000).toLocaleString();
};
</script>
