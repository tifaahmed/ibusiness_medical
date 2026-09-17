<template>
  <AppLayout title="Stores">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-2 sm:py-3 md:py-4 shadow-sm w-full">
          <div class="flex flex-row items-center justify-between py-2 px-3 sm:px-4 md:px-6 gap-2 sm:gap-4">
            <div class="leading-none font-semibold min-w-0 flex-1">
              <div class="title-golden flex items-center gap-2 min-w-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="title-icon flex-shrink-0 text-orange-400">
                  <path d="M2 3h20l-2 8H4L2 3Z"></path>
                  <path d="M4 11v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9"></path>
                  <path d="M9 21v-6h6v6"></path>
                </svg>
                <span class="text-sm sm:text-base truncate min-w-0">Stores</span>
              </div>
            </div>
            <Link
              v-if="canManage"
              :href="route('admin.store.create')"
              class="inline-flex items-center gap-1.5 h-8 sm:h-9 px-3 rounded-md bg-primary text-primary-foreground text-xs sm:text-sm font-medium hover:bg-primary/90"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"></path><path d="M12 5v14"></path>
              </svg>
              Add Store
            </Link>
          </div>
        </div>

        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm">
          <div class="p-3 sm:p-4">
            <input
              v-model="search"
              @input="handleSearch"
              type="text"
              placeholder="Search by title or slug…"
              class="flex h-9 w-full max-w-sm rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
          </div>

          <div v-if="stores.data.length > 0" class="overflow-x-auto">
            <table class="w-full caption-bottom text-sm">
              <thead class="[&_tr]:border-b [&_tr]:border-border">
                <tr>
                  <th class="h-10 px-3 text-left align-middle font-medium">Logo</th>
                  <th class="h-10 px-3 text-left align-middle font-medium">Title</th>
                  <th class="h-10 px-3 text-center align-middle font-medium">Branches</th>
                  <th class="h-10 px-3 text-center align-middle font-medium">Products</th>
                  <th class="h-10 px-3 text-left align-middle font-medium">Created</th>
                  <th class="h-10 px-3 text-center align-middle font-medium">Actions</th>
                </tr>
              </thead>
              <tbody class="[&_tr:last-child]:border-0">
                <tr v-for="store in stores.data" :key="store.id" class="border-b border-border hover:bg-muted/50">
                  <td class="p-3 align-middle">
                    <img v-if="store.logo" :src="store.logo" class="h-9 w-9 rounded object-cover border border-border" />
                    <div v-else class="h-9 w-9 rounded bg-muted"></div>
                  </td>
                  <td class="p-3 align-middle font-medium">{{ getTranslatedName(store.title) }}</td>
                  <td class="p-3 align-middle text-center">{{ store.branches_count }}</td>
                  <td class="p-3 align-middle text-center">{{ store.products_count }}</td>
                  <td class="p-3 align-middle">{{ store.created_at }}</td>
                  <td class="p-3 align-middle text-center">
                    <div class="flex items-center justify-center gap-1.5">
                      <Link :href="route('admin.store.show', store.id)" class="h-7 px-2 inline-flex items-center rounded-md border border-border text-xs hover:bg-muted">View</Link>
                      <Link v-if="canManage" :href="route('admin.store.edit', store.id)" class="h-7 px-2 inline-flex items-center rounded-md border border-border text-xs hover:bg-muted">Edit</Link>
                      <button v-if="canManage" @click="destroyStore(store)" class="h-7 px-2 inline-flex items-center rounded-md border border-destructive/40 text-destructive text-xs hover:bg-destructive/10">Delete</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="p-12 text-center text-muted-foreground text-sm">No stores yet.</div>

          <div class="border-t border-border px-4 py-3">
            <Pagination v-if="stores.links?.length > 0" :links="stores.links" />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Pages/_components/Pagination.vue';

const props = defineProps({
  stores: { type: Object, required: true },
  filters: { type: Object, default: () => ({ search: '' }) },
  canManage: { type: Boolean, default: false },
});

const search = ref(props.filters.search || '');

const getTranslatedName = (title) => {
  if (typeof title === 'string') return title;
  if (title && typeof title === 'object') return title['ar'] || title['en'] || Object.values(title)[0] || '';
  return '';
};

let searchTimeout = null;
const handleSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(route('admin.store.list'), { search: search.value }, { preserveState: true, preserveScroll: true, replace: true });
  }, 300);
};

const destroyStore = (store) => {
  if (!confirm(`Delete "${getTranslatedName(store.title)}"? This cannot be undone.`)) return;
  router.delete(route('admin.store.destroy', store.id), { preserveScroll: true });
};
</script>
