<template>
  <SalesLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Sales Rep"
        :breadcrumbs="[
          { label: 'Sales', link: route('admin.sales.list'), active: false },
          { label: getTranslated(sale.name) || 'View', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Sales Information">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 space-y-3">
              <TranslatedField label="Name" :value="sale.name" />
              <ShowField label="Facilities" :value="sale.facilities_count ?? 0" />
            </div>
            <div v-if="sale.image">
              <label class="text-xs font-medium text-muted-foreground">Image</label>
              <img
                :src="sale.image"
                :alt="getTranslated(sale.name)"
                class="mt-1 w-32 h-32 rounded-lg border border-border object-cover cursor-zoom-in transition hover:opacity-90"
                @click="lightboxIndex = 0"
              />
            </div>
          </div>
        </ShowCard>

        <ShowCard v-if="sale.facilities?.length" title="Assigned Facilities">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border text-left">
                  <th class="p-2 font-medium text-muted-foreground">Facility</th>
                  <th class="p-2 font-medium text-muted-foreground">Type</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="facility in sale.facilities" :key="facility.id" class="border-b border-border/50 last:border-0">
                  <td class="p-2">
                    <Link :href="route('admin.facility.show', facility.slug)" class="text-primary hover:underline">
                      {{ getTranslated(facility.name) || `#${facility.id}` }}
                    </Link>
                  </td>
                  <td class="p-2 text-muted-foreground">{{ getTranslated(facility.facility_type) || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </ShowCard>

        <RecordMeta
          :id="sale.id"
          :created-at="sale.created_at"
          :updated-at="sale.updated_at"
          :creator="sale.creator"
        />

        <ShowActions
          :list-href="route('admin.sales.list')"
          :edit-href="canManage('manage sales', 'manage own sales') ? route('admin.sales.edit', sale.id) : null"
          edit-label="Edit Sales Rep"
        />
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </SalesLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SalesLayout from './SalesLayout.vue';
import ImageLightbox from '@/Components/ui/ImageLightbox.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, TranslatedField, RecordMeta } from '@/Pages/Admin/_components/Show';
import { useTranslatedField } from '@/composables/useTranslatedField';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  sale: { type: Object, required: true },
});

const { getTranslated } = useTranslatedField();
const { canManage } = usePermissions();

const lightboxImages = computed(() =>
  props.sale.image ? [{ url: props.sale.image, alt: getTranslated(props.sale.name) }] : []
);

const lightboxIndex = ref(null);
</script>
