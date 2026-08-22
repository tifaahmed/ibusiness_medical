<template>
  <MembershipUsageLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Membership Usage"
        :breadcrumbs="[
          { label: 'Membership Usage', link: route('admin.membership-usage.list'), active: false },
          { label: `#${usage.id}`, link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Usage Information">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <ShowField label="Membership">
              <p class="text-sm font-medium mt-0.5">
                <Link
                  v-if="usage.membership?.user"
                  :href="route('admin.user.membership.show', usage.membership.user.id)"
                  class="text-primary hover:underline"
                >
                  {{ usage.user_name || usage.membership_number || `#${usage.membership_id}` }}
                </Link>
                <span v-else>{{ usage.user_name || usage.membership_number || '—' }}</span>
              </p>
              <p v-if="usage.membership_number" class="text-xs text-muted-foreground font-mono">
                {{ usage.membership_number }}
              </p>
            </ShowField>
            <ShowField label="Amount" :value="formatPrice(usage.amount)" value-class="value-positive font-semibold" />
            <ShowField label="Recorded" :value="usage.created_at" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
            <ShowField label="Facility">
              <p class="text-sm font-medium mt-0.5">{{ usage.facility_name || '—' }}</p>
            </ShowField>
            <ShowField label="Branch" :value="usage.facility_branch_name" />
            <ShowField label="Facility type" :value="usage.facility_type_name" />
          </div>

          <ShowField v-if="usage.description" label="Description" class="mt-3">
            <p class="text-sm mt-0.5 whitespace-pre-wrap">{{ usage.description }}</p>
          </ShowField>
        </ShowCard>

        <ShowCard v-if="galleryImages.length" title="Attachments">
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
          :id="usage.id"
          :created-at="usage.created_at"
          :updated-at="usage.updated_at"
        />

        <ShowActions
          :list-href="route('admin.membership-usage.list')"
          :edit-href="canManage('manage membership usages', 'manage own membership usages') ? route('admin.membership-usage.edit', usage.id) : null"
          edit-label="Edit Usage"
        />
      </div>
    </div>

    <ImageLightbox :images="galleryImages" v-model:index="lightboxIndex" />
  </MembershipUsageLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import MembershipUsageLayout from './MembershipUsageLayout.vue';
import ImageLightbox from '@/Components/ui/ImageLightbox.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, RecordMeta } from '@/Pages/Admin/_components/Show';
import { usePermissions } from '@/composables/usePermissions';
import { usePriceFormat } from '@/composables/usePriceFormat';

const props = defineProps({
  usage: { type: Object, required: true },
});

const { canManage } = usePermissions();
const { formatPrice } = usePriceFormat();

const galleryImages = computed(() =>
  (props.usage.gallery || [])
    .map((img, i) => ({
      url: typeof img === 'string' ? img : img?.url,
      alt: `Usage #${props.usage.id} — ${i + 1}`,
    }))
    .filter((img) => img.url)
);

const lightboxIndex = ref(null);

const openLightbox = (url) => {
  const at = galleryImages.value.findIndex((img) => img.url === url);
  if (at !== -1) lightboxIndex.value = at;
};
</script>
