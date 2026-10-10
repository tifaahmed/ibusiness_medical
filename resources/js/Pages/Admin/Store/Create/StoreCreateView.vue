<template>
  <AppLayout title="Create Store">
    <div class="w-full max-w-full">
      <div class="space-y-2 sm:space-y-3 md:space-y-4 p-2 sm:p-3 md:p-4 lg:p-6 w-full max-w-full">
        <div data-slot="card" class="bg-card text-card-foreground rounded-xl border border-border py-3 shadow-sm w-full">
          <div class="flex items-center justify-between px-4">
            <h2 class="text-base font-semibold">Create Store</h2>
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
                  {{ form.processing ? 'Creating…' : 'Create Store' }}
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

defineProps({
  categories: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  tagIconOptions: { type: Array, default: () => [] },
  tagColorOptions: { type: Array, default: () => [] },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  aiEnabled: { type: Boolean, default: false },
});

const form = useForm({
  title: { ar: '', en: '' },
  description: { ar: '', en: '' },
  short_description: { ar: '', en: '' },
  youtube_link: '',
  online_only: false,
  editor_gallery_paths: [],
  websites: [],
  app_store_url: '',
  google_play_url: '',
  social_links: [],
  coupons: [],
  meta_title: { ar: '', en: '' },
  meta_description: { ar: '', en: '' },
  meta_keywords: { ar: '', en: '' },
  category_ids: [],
  tag_ids: [],
  supports_shipping: false,
  ships_everywhere: true,
  shipping_governorate_ids: [],
  offer_percent_from: '',
  offer_percent_to: '',
  logo: null,
  header: null,
  seo_image: null,
  gallery_images: [],
  gallery_videos: [],
  gallery_delete: [],
  branches: [],
});

const debugLog = ref(null);

const submit = (stay = false) => {
  debugLog.value = buildDebugLog({ method: 'POST', url: route('admin.store.store'), fields: form.data() });
  form.transform((data) => ({ ...data, stay: stay === true })).post(route('admin.store.store'), { forceFormData: true, onSuccess: () => { debugLog.value = null; }, onError: onSaveError });
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
    extra: { feature: 'store-form', step: 'create', errors },
  }).catch(() => {});
}
</script>
