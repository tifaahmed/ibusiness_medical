<template>
  <PartnerLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Partner"
        :breadcrumbs="[
          { label: 'Partners', link: route('admin.partner.list'), active: false },
          { label: partner.title || 'View', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Partner Information">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 space-y-3">
              <ShowField label="Title" :value="partner.title" />
              <ShowField label="Short description" :value="partner.short_description">
                <p class="text-sm mt-0.5 whitespace-pre-wrap">{{ partner.short_description || '—' }}</p>
              </ShowField>
              <ShowField label="Description">
                <p class="text-sm mt-0.5 whitespace-pre-wrap">{{ partner.description || '—' }}</p>
              </ShowField>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-1">
                <ShowField label="Offers">
                  <p class="text-sm font-medium mt-0.5">
                    <Link
                      v-if="partner.offers_count"
                      :href="route('admin.partner-offer.list', { partner_id: partner.id })"
                      class="text-primary hover:underline"
                    >
                      {{ partner.offers_count }}
                    </Link>
                    <span v-else>0</span>
                  </p>
                </ShowField>
              </div>

              <!-- Where this partner's logo sits on a generated card. Only
                   meaningful once someone has positioned it. -->
              <div v-if="hasCardPlacement" class="grid grid-cols-3 gap-3 pt-1">
                <ShowField label="Card X" :value="partner.card_x" />
                <ShowField label="Card Y" :value="partner.card_y" />
                <ShowField label="Card scale" :value="partner.card_scale" />
              </div>
            </div>

            <div class="space-y-3">
              <ShowField v-if="partner.image" label="Logo">
                <img
                  :src="partner.image"
                  :alt="partner.title"
                  class="mt-1 w-32 h-32 rounded-lg border border-border object-contain bg-muted/30 cursor-zoom-in transition hover:opacity-90"
                  @click="openLightbox(partner.image)"
                />
              </ShowField>
              <ShowField v-if="partner.header_image" label="Header image">
                <img
                  :src="partner.header_image"
                  :alt="partner.title"
                  class="mt-1 w-full rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                  @click="openLightbox(partner.header_image)"
                />
              </ShowField>
            </div>
          </div>
        </ShowCard>

        <ShowCard v-if="galleryImages.length" title="Gallery">
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <img
              v-for="img in galleryImages"
              :key="img.url"
              :src="img.url"
              :alt="img.alt"
              class="w-full h-32 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
              @click="openLightbox(img.url)"
            />
          </div>
        </ShowCard>

        <RecordMeta
          :id="partner.id"
          :created-at="partner.created_at"
          :updated-at="partner.updated_at"
          :creator="partner.creator"
        />

        <ShowActions
          :list-href="route('admin.partner.list')"
          :edit-href="canManage('manage partners', 'manage own partners') ? route('admin.partner.edit', partner.id) : null"
          edit-label="Edit Partner"
        />
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </PartnerLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import PartnerLayout from './PartnerLayout.vue';
import ImageLightbox from '@/Components/ui/ImageLightbox.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, RecordMeta } from '@/Pages/Admin/_components/Show';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  partner: { type: Object, required: true },
});

const { canManage } = usePermissions();

const hasCardPlacement = computed(() =>
  [props.partner.card_x, props.partner.card_y, props.partner.card_scale]
    .some((value) => value !== null && value !== undefined)
);

/* The gallery accessor answers { id, url } rows, so the src has to reach for
   .url — binding the row itself renders "[object Object]". */
const galleryImages = computed(() =>
  (props.partner.gallery || [])
    .map((img, i) => ({
      url: typeof img === 'string' ? img : img?.url,
      alt: `${props.partner.title || 'Partner'} — ${i + 1}`,
    }))
    .filter((img) => img.url)
);

// One viewer for every picture on the page, so paging walks the logo and the
// header alongside the gallery.
const lightboxImages = computed(() => {
  const title = props.partner.title || 'Partner';
  const main = [
    props.partner.image ? { url: props.partner.image, alt: `${title} — logo` } : null,
    props.partner.header_image ? { url: props.partner.header_image, alt: `${title} — header` } : null,
  ].filter(Boolean);

  return [...main, ...galleryImages.value];
});

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = lightboxImages.value.findIndex((img) => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};
</script>
