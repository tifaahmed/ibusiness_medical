<template>
  <PartnerLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Partner Offer"
        :breadcrumbs="[
          { label: 'Partner Offers', link: route('admin.partner-offer.list'), active: false },
          { label: partnerOffer.title || 'View', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Offer Information">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 space-y-3">
              <ShowField label="Title" :value="partnerOffer.title" />
              <ShowField label="Short description">
                <p class="text-sm mt-0.5 whitespace-pre-wrap">{{ partnerOffer.short_description || '—' }}</p>
              </ShowField>
              <ShowField label="Description">
                <p class="text-sm mt-0.5 whitespace-pre-wrap">{{ partnerOffer.description || '—' }}</p>
              </ShowField>

              <div class="flex gap-4 flex-wrap pt-1">
                <ShowField v-if="partnerOffer.new_price" label="New price" :value="formatPrice(partnerOffer.new_price)" value-class="value-positive" />
                <ShowField v-if="partnerOffer.old_price" label="Old price" :value="formatPrice(partnerOffer.old_price)" value-class="value-muted line-through" />
                <ShowField label="Requests">
                  <p class="text-sm font-medium mt-0.5">
                    <Link
                      v-if="partnerOffer.requests_count"
                      :href="route('admin.partner-offer-request.list', { partner_offer_id: partnerOffer.id })"
                      class="text-primary hover:underline"
                    >
                      {{ partnerOffer.requests_count }}
                    </Link>
                    <span v-else>0</span>
                  </p>
                </ShowField>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
                <ShowField v-if="partnerOffer.partner" label="Partner">
                  <p class="text-sm font-medium mt-0.5">
                    <Link
                      :href="route('admin.partner.show', partnerOffer.partner.id)"
                      class="text-primary hover:underline"
                    >
                      {{ partnerOffer.partner.title }}
                    </Link>
                  </p>
                </ShowField>
                <ShowField label="Phone number" :value="partnerOffer.phone_number" value-class="font-mono" />
                <ShowField v-if="partnerOffer.operator" label="Operator">
                  <div class="flex items-center gap-2 mt-0.5">
                    <img
                      v-if="partnerOffer.operator_logo"
                      :src="partnerOffer.operator_logo"
                      :alt="partnerOffer.operator_title || partnerOffer.operator"
                      class="w-6 h-6 rounded object-contain"
                    />
                    <span class="text-sm font-medium">{{ partnerOffer.operator_title || partnerOffer.operator_name || partnerOffer.operator }}</span>
                  </div>
                </ShowField>
              </div>
            </div>

            <div class="space-y-3">
              <ShowField v-for="image in imageSlots" :key="image.label" :label="image.label">
                <img
                  :src="image.url"
                  :alt="`${partnerOffer.title || 'Offer'} — ${image.label}`"
                  class="mt-1 w-full rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                  @click="openLightbox(image.url)"
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
          :id="partnerOffer.id"
          :created-at="partnerOffer.created_at"
          :updated-at="partnerOffer.updated_at"
          :creator="partnerOffer.creator"
        />

        <ShowActions
          :list-href="route('admin.partner-offer.list')"
          :edit-href="canManage('manage partner offers', 'manage own partner offers') ? route('admin.partner-offer.edit', partnerOffer.id) : null"
          edit-label="Edit Offer"
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
import { usePriceFormat } from '@/composables/usePriceFormat';

const props = defineProps({
  partnerOffer: { type: Object, required: true },
});

const { canManage } = usePermissions();
const { formatPrice } = usePriceFormat();

// The four artwork slots, minus the ones with nothing uploaded.
const imageSlots = computed(() =>
  [
    { label: 'Header image', url: props.partnerOffer.header_image },
    { label: 'Mobile header image', url: props.partnerOffer.mobile_header_image },
    { label: 'Small image', url: props.partnerOffer.small_image },
    { label: 'Mobile small image', url: props.partnerOffer.mobile_small_image },
  ].filter((slot) => slot.url)
);

const galleryImages = computed(() =>
  (props.partnerOffer.gallery || [])
    .map((img, i) => ({
      url: typeof img === 'string' ? img : img?.url,
      alt: `${props.partnerOffer.title || 'Offer'} — ${i + 1}`,
    }))
    .filter((img) => img.url)
);

const lightboxImages = computed(() => [
  ...imageSlots.value.map((slot) => ({ url: slot.url, alt: slot.label })),
  ...galleryImages.value,
]);

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = lightboxImages.value.findIndex((img) => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};
</script>
