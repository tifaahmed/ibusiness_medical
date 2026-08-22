<template>
  <NewsTickerLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View News Ticker"
        :breadcrumbs="[
          { label: 'News Ticker', link: route('admin.news-ticker.list'), active: false },
          { label: getTranslated(newsTicker.title) || 'View', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="News Ticker">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 space-y-3">
              <TranslatedField label="Title" :value="newsTicker.title" />
              <TranslatedField label="Description" :value="newsTicker.description" multiline text-class="" />

              <div class="grid grid-cols-2 md:grid-cols-3 gap-3 pt-1">
                <ShowField label="Category" :value="newsTicker.category" />
                <ShowField label="Sort order" :value="newsTicker.sort_order" />
                <ShowField label="Status">
                  <div class="mt-0.5">
                    <StatusPill :active="newsTicker.is_active" />
                  </div>
                </ShowField>
              </div>
            </div>

            <div class="space-y-3">
              <ShowField v-if="newsTicker.image_url" label="Image">
                <img
                  :src="newsTicker.image_url"
                  :alt="getTranslated(newsTicker.title)"
                  class="mt-1 w-full rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                  @click="openLightbox(newsTicker.image_url)"
                />
              </ShowField>
              <!-- Only worth its own slot when it differs; the resource falls
                   back to the desktop image when no mobile one was uploaded. -->
              <ShowField
                v-if="newsTicker.mobile_image_url && newsTicker.mobile_image_url !== newsTicker.image_url"
                label="Mobile image"
              >
                <img
                  :src="newsTicker.mobile_image_url"
                  :alt="getTranslated(newsTicker.title)"
                  class="mt-1 w-full rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                  @click="openLightbox(newsTicker.mobile_image_url)"
                />
              </ShowField>
            </div>
          </div>
        </ShowCard>

        <RecordMeta
          :id="newsTicker.id"
          :created-at="newsTicker.created_at"
          :updated-at="newsTicker.updated_at"
          :creator="newsTicker.creator"
        />

        <ShowActions
          :list-href="route('admin.news-ticker.list')"
          :edit-href="canManage('manage news tickers', 'manage own news tickers') ? route('admin.news-ticker.edit', newsTicker.id) : null"
          edit-label="Edit News Ticker"
        />
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </NewsTickerLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import NewsTickerLayout from './NewsTickerLayout.vue';
import ImageLightbox from '@/Components/ui/ImageLightbox.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, TranslatedField, StatusPill, RecordMeta } from '@/Pages/Admin/_components/Show';
import { useTranslatedField } from '@/composables/useTranslatedField';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  newsTicker: { type: Object, required: true },
});

const { getTranslated } = useTranslatedField();
const { canManage } = usePermissions();

const lightboxImages = computed(() => {
  const title = getTranslated(props.newsTicker.title);
  const urls = [props.newsTicker.image_url, props.newsTicker.mobile_image_url];

  return [...new Set(urls.filter(Boolean))].map((url) => ({ url, alt: title }));
});

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = lightboxImages.value.findIndex((img) => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};
</script>
