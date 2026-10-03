<template>
  <AppLayout :title="getName(store.title)">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-3 shadow-sm w-full">
          <div class="flex items-center justify-between px-4 gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <img v-if="store.logo" :src="store.logo" class="h-12 w-12 rounded object-cover border border-border flex-shrink-0" />
              <h2 class="text-base font-semibold truncate">{{ getName(store.title) }}</h2>
              <span v-for="(c, i) in store.categories || []" :key="i" class="inline-flex items-center gap-1 rounded-full bg-sky-500/15 text-sky-600 px-2 py-0.5 text-xs font-medium flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle></svg>
                {{ getName(c) }}
              </span>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <Link :href="route('admin.store.edit', store.id)" class="h-8 px-3 inline-flex items-center rounded-md border border-border text-xs sm:text-sm hover:bg-muted">Edit</Link>
              <Link :href="route('admin.store.list')" class="h-8 px-3 inline-flex items-center rounded-md border border-border text-xs sm:text-sm hover:bg-muted">Back</Link>
            </div>
          </div>
        </div>

        <div v-if="store.header" class="rounded-xl overflow-hidden border border-border">
          <img :src="store.header" class="w-full h-48 sm:h-64 object-cover" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
          <div class="lg:col-span-2 space-y-3">
            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-3">
              <h3 class="text-sm font-semibold">Details</h3>
              <p v-if="getName(store.short_description)" class="text-sm text-muted-foreground">{{ getName(store.short_description) }}</p>
              <div v-if="getName(store.description)" class="text-sm whitespace-pre-line">{{ getName(store.description) }}</div>
              <a v-if="store.youtube_link" :href="store.youtube_link" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-blue-500 hover:underline">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"></path>
                  <path d="m10 15 5-3-5-3z"></path>
                </svg>
                Watch on YouTube
              </a>
              <div v-if="store.offer_percent_from !== null || store.offer_percent_to !== null" class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 text-emerald-600 px-2 py-1 text-xs font-semibold w-fit">
                Offer: {{ store.offer_percent_from ?? 0 }}% – {{ store.offer_percent_to ?? 0 }}% off
              </div>
            </div>

            <div v-if="(store.websites?.length || store.social_links?.length || store.app_store_url || store.google_play_url)" data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-2">
              <h3 class="text-sm font-semibold">Links</h3>
              <a v-if="store.app_store_url" :href="store.app_store_url" target="_blank" rel="noopener" class="flex items-center gap-2 truncate text-sm text-blue-500 hover:underline" dir="ltr">
                <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-foreground">App Store</span>{{ store.app_store_url }}
              </a>
              <a v-if="store.google_play_url" :href="store.google_play_url" target="_blank" rel="noopener" class="flex items-center gap-2 truncate text-sm text-blue-500 hover:underline" dir="ltr">
                <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-foreground">Google Play</span>{{ store.google_play_url }}
              </a>
              <a v-for="site in store.websites" :key="site" :href="site" target="_blank" rel="noopener" class="block truncate text-sm text-blue-500 hover:underline" dir="ltr">{{ site }}</a>
              <a v-for="link in store.social_links" :key="link.url" :href="link.url" target="_blank" rel="noopener" class="flex items-center gap-2 truncate text-sm text-blue-500 hover:underline" dir="ltr">
                <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] capitalize text-foreground">{{ link.platform }}</span>{{ link.url }}
              </a>
            </div>

            <div v-if="store.coupons?.length" data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-2">
              <h3 class="text-sm font-semibold">Coupons</h3>
              <div v-for="(c, i) in store.coupons" :key="i" class="flex items-center justify-between gap-3 rounded-lg border border-dashed border-border p-2 text-sm">
                <div class="min-w-0">
                  <p class="truncate font-medium">{{ getName(c.title) || c.code }}</p>
                  <p class="text-xs text-muted-foreground">{{ c.expires_at ? `Expires ${c.expires_at}` : 'No expiry' }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <span v-if="c.expires_at && new Date(c.expires_at + 'T23:59:59') < new Date()" class="rounded-full bg-destructive/15 px-2 py-0.5 text-[11px] text-destructive">Expired</span>
                  <code class="rounded bg-muted px-2 py-1 font-mono text-xs" dir="ltr">{{ c.code }}</code>
                </div>
              </div>
            </div>

            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-3">
              <h3 class="text-sm font-semibold">Gallery</h3>
              <div v-if="store.gallery?.length" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                <button
                  v-for="item in store.gallery"
                  :key="item.id"
                  type="button"
                  @click="openLightbox(item)"
                  class="relative aspect-square rounded-lg overflow-hidden border border-border group"
                >
                  <img v-if="item.type === 'image'" :src="item.url" class="w-full h-full object-cover" loading="lazy" />
                  <video v-else :src="item.url" class="w-full h-full object-cover" muted preload="metadata" />
                  <span v-if="item.type === 'video'" class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/40 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="white" class="drop-shadow">
                      <path d="M8 5v14l11-7z"></path>
                    </svg>
                  </span>
                </button>
              </div>
              <p v-else class="text-sm text-muted-foreground">No images or videos yet.</p>
            </div>
          </div>

          <div class="space-y-3">
            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-3">
              <h3 class="text-sm font-semibold">Overview</h3>
              <dl class="text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-muted-foreground">Products</dt><dd class="font-medium">{{ store.products_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Type</dt><dd class="font-medium">{{ store.online_only ? 'Online only' : 'Online & physical' }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Branches</dt><dd class="font-medium">{{ store.branches.length }}</dd></div>
                <div v-if="store.creator_name" class="flex justify-between"><dt class="text-muted-foreground">Created by</dt><dd class="font-medium">{{ store.creator_name }}</dd></div>
                <div v-if="store.created_at" class="flex justify-between"><dt class="text-muted-foreground">Created at</dt><dd class="font-medium">{{ store.created_at }}</dd></div>
              </dl>
            </div>

            <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-4 space-y-3">
              <h3 class="text-sm font-semibold">Branches</h3>
              <p v-if="store.online_only" class="text-sm text-muted-foreground">Online store only — no physical branches.</p>
              <p v-else-if="!store.branches.length" class="text-sm text-muted-foreground">No branches yet.</p>
              <div v-for="branch in store.branches" :key="branch.id" class="rounded-lg border border-border p-3 space-y-1.5 text-sm">
                <div class="font-medium">{{ getName(branch.name) || 'Branch' }}</div>
                <div v-if="getName(branch.address)" class="text-muted-foreground">{{ getName(branch.address) }}</div>
                <div v-if="getName(branch.area)" class="text-muted-foreground">{{ getName(branch.area) }}</div>
                <div class="text-muted-foreground">
                  <span v-if="branch.governorate">{{ getName(branch.governorate) }}</span>
                  <span v-if="branch.city"> — {{ getName(branch.city) }}</span>
                </div>
                <a v-if="branch.google_location_url" :href="branch.google_location_url" target="_blank" rel="noopener" class="text-blue-500 hover:underline inline-block">View on map</a>
                <div v-if="branch.phone?.length" class="flex flex-wrap gap-1.5 pt-1">
                  <span v-for="(p, idx) in branch.phone" :key="idx" dir="ltr" class="rounded bg-muted px-1.5 py-0.5 text-xs">{{ p.number }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="lightboxItem"
      class="fixed inset-0 z-[1000] bg-black/80 flex items-center justify-center p-4"
      @click="lightboxItem = null"
    >
      <img v-if="lightboxItem.type === 'image'" :src="lightboxItem.url" class="max-h-[85vh] max-w-full rounded-lg" @click.stop />
      <video v-else :src="lightboxItem.url" class="max-h-[85vh] max-w-full rounded-lg" controls autoplay @click.stop />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
  store: { type: Object, required: true },
});

const lightboxItem = ref(null);
const openLightbox = (item) => { lightboxItem.value = item; };

const getName = (value) => {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') return value['ar'] || value['en'] || Object.values(value)[0] || '';
  return '';
};
</script>
