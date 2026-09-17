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

        <form @submit.prevent="submit" class="max-w-4xl mx-auto space-y-4">
          <StoreForm
            :form="form"
            :governorates="governorates"
            :cities="cities"
            :existing-logo="store.logo"
            :existing-header="store.header"
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
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 disabled:opacity-50"
              >
                {{ form.processing ? 'Saving…' : 'Save Store' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StoreForm from '../_components/StoreForm.vue';

const props = defineProps({
  store: { type: Object, required: true },
  governorates: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
});

const form = useForm({
  title: { ar: props.store.title?.ar || '', en: props.store.title?.en || '' },
  description: { ar: props.store.description?.ar || '', en: props.store.description?.en || '' },
  short_description: { ar: props.store.short_description?.ar || '', en: props.store.short_description?.en || '' },
  youtube_link: props.store.youtube_link || '',
  offer_percent_from: props.store.offer_percent_from ?? '',
  offer_percent_to: props.store.offer_percent_to ?? '',
  logo: null,
  logo_delete: false,
  header: null,
  header_delete: false,
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

const submit = () => {
  form.transform((data) => ({ ...data, _method: 'PUT' }))
    .post(route('admin.store.update', props.store.id), { forceFormData: true });
};
</script>
