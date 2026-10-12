<template>
  <AppLayout title="Edit Store">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-3 shadow-sm w-full">
          <div class="flex items-center justify-between px-4">
            <h2 class="text-base font-semibold">Edit Store</h2>
            <Link :href="route('admin.store.list')" class="text-sm text-muted-foreground hover:text-foreground">Back to stores</Link>
          </div>
        </div>

        <form @submit.prevent="submit()" class="max-w-4xl mx-auto space-y-4">
          <StoreForm
            :form="form"
            :categories="categories"
            :tags="tags"
            :tag-icon-options="tagIconOptions"
            :tag-color-options="tagColorOptions"
            :governorates="governorates"
            :cities="cities"
            :ai-enabled="aiEnabled"
            :existing-logo="store.logo"
            :existing-header="store.header"
            :existing-seo-image="store.seo_image"
            :existing-gallery="store.gallery"
          />

          <div class="sticky bottom-0 z-10 bg-card border border-border rounded-lg shadow-sm">
            <div class="flex justify-end gap-2 p-3">
              <Link
                :href="route('admin.store.list')"
                class="inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium border bg-background h-9 px-4"
              >
                Cancel
              </Link>
              <button
                type="button"
                :disabled="form.processing"
                @click="submit(true)"
                class="inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium border bg-background hover:bg-muted h-9 px-4 disabled:opacity-50"
              >
                {{ form.processing ? 'Saving…' : 'Save & stay' }}
              </button>
              <div class="relative inline-flex">
                <button
                  type="submit"
                  :disabled="form.processing"
                  class="inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 disabled:opacity-50"
                >
                  {{ form.processing ? 'Saving…' : 'Save Store' }}
                </button>
                <ErrorTrackButton :errors="form.errors" :debug-log="debugLog" />
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import axios from 'axios';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useNotification } from '@/composables/useNotification';
import ErrorTrackButton from '@/Components/ui/ErrorTrackButton.vue';
import { buildDebugLog, recordResponse } from '@/utils/errorTrack';
import AppLayout from '@/Layouts/AppLayout.vue';
import StoreForm from '../_components/StoreForm.vue';

const props = defineProps({
  store: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  tagIconOptions: { type: Array, default: () => [] },
  tagColorOptions: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  aiEnabled: { type: Boolean, default: false },
});

const form = useForm({
  title: { ar: props.store.title?.ar || '', en: props.store.title?.en || '' },
  description: { ar: props.store.description?.ar || '', en: props.store.description?.en || '' },
  short_description: { ar: props.store.short_description?.ar || '', en: props.store.short_description?.en || '' },
  youtube_link: props.store.youtube_link || '',
  online_only: !!props.store.online_only,
  editor_gallery_paths: [],
  websites: [...(props.store.websites || [])],
  app_store_url: props.store.app_store_url || '',
  google_play_url: props.store.google_play_url || '',
  social_links: (props.store.social_links || []).map(l => ({ platform: l.platform || 'other', url: l.url || '' })),
  coupons: (props.store.coupons || []).map(c => ({ code: c.code || '', title: { ar: c.title?.ar || '', en: c.title?.en || '' }, expires_at: c.expires_at || '' })),
  meta_title: { ar: props.store.meta_title?.ar || '', en: props.store.meta_title?.en || '' },
  meta_description: { ar: props.store.meta_description?.ar || '', en: props.store.meta_description?.en || '' },
  meta_keywords: { ar: props.store.meta_keywords?.ar || '', en: props.store.meta_keywords?.en || '' },
  category_ids: [...(props.store.category_ids || [])],
  tag_ids: [...(props.store.tag_ids || [])],
  supports_shipping: props.store.supports_shipping ?? false,
  ships_everywhere: props.store.ships_everywhere ?? true,
  shipping_governorate_ids: [...(props.store.shipping_governorate_ids || [])],
  offer_percent_from: props.store.offer_percent_from ?? '',
  offer_percent_to: props.store.offer_percent_to ?? '',
  logo: null,
  logo_delete: false,
  header: null,
  header_delete: false,
  seo_image: null,
  seo_image_delete: false,
  gallery_images: [],
  gallery_videos: [],
  gallery_delete: [],
  branches: props.store.branches.map(branch => ({
    governorate_id: branch.governorate_id || '',
    city_id: branch.city_id || '',
    name: { ar: branch.name?.ar || '', en: branch.name?.en || '' },
    address: { ar: branch.address?.ar || '', en: branch.address?.en || '' },
    area: { ar: branch.area?.ar || '', en: branch.area?.en || '' },
    latitude: branch.latitude ?? '',
    longitude: branch.longitude ?? '',
    google_location_url: branch.google_location_url || '',
    phone: branch.phone || [],
  })),
});

const debugLog = ref(null);

const submit = (stay = false) => {
  debugLog.value = buildDebugLog({ method: 'PUT', url: route('admin.store.update', props.store.id), fields: form.data() });
  form.transform((data) => ({ ...data, _method: 'PUT', stay: stay === true }))
    .post(route('admin.store.update', props.store.id), { forceFormData: true, onSuccess: () => { debugLog.value = null; }, onError: onSaveError });
};

// A rejected save comes back as validation errors; StoreForm lists them all at
// the top, the red badge on the submit button opens the full request/response
// trace, and the client-error log hears of it.
function onSaveError(errors) {
  const keys = Object.keys(errors || {});
  // The exception behind a failed save (App\Support\ErrorTrace), for the badge's Advanced tab.
  debugLog.value = recordResponse(debugLog.value, errors, null, usePage().props?.flash?.error_debug || null);
  useNotification().error(errors?.error || `The store was not saved: ${keys.length} field(s) need fixing.`);
  window.scrollTo({ top: 0, behavior: 'smooth' });
  axios.post('/api/v1/client-errors', {
    message: `Store save rejected: ${keys.join(', ') || 'no field named'}`,
    fatal: false,
    route: window.location.pathname,
    extra: { feature: 'store-form', step: 'update', errors },
  }).catch(() => {});
}
</script>
