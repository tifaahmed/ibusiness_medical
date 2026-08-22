<template>
  <AppLayout>
    <div class="space-y-3 p-3 sm:p-4 lg:p-6 w-full max-w-full">
      <Breadcrumb
        title="View Card Template"
        :breadcrumbs="[
          { label: 'Card Templates', link: route('admin.card-templates.index'), active: false },
          { label: getTranslated(template.name) || 'View', link: '#', active: true },
        ]"
      />

      <div class="max-w-7xl mx-auto mt-2 space-y-3">
        <ShowCard title="Template Information">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
              <TranslatedField label="Name" :value="template.name" />
              <div class="grid grid-cols-2 gap-3">
                <ShowField label="Status">
                  <p class="text-sm font-medium mt-0.5">
                    <span
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                      :class="template.status === 'with_partner'
                        ? 'bg-emerald-500/10 text-emerald-500 ring-1 ring-inset ring-emerald-500/20'
                        : 'bg-muted text-muted-foreground ring-1 ring-inset ring-border'"
                    >
                      {{ template.status_label || template.status || '—' }}
                    </span>
                  </p>
                </ShowField>
                <ShowField label="Positioned fields" :value="layoutRows.length" />
              </div>
              <ShowField v-if="template.hidden_fields?.length" label="Hidden by status">
                <div class="flex flex-wrap gap-1.5 mt-1">
                  <span
                    v-for="field in template.hidden_fields"
                    :key="field"
                    class="inline-flex items-center rounded-md border border-border bg-muted/40 px-2 py-0.5 text-xs font-mono"
                  >
                    {{ field }}
                  </span>
                </div>
              </ShowField>
            </div>

            <div>
              <label class="text-xs font-medium text-muted-foreground">Artwork</label>
              <div class="mt-1 rounded-lg border border-border bg-muted/40 aspect-[1.586/1] flex items-center justify-center overflow-hidden">
                <img
                  v-if="artwork"
                  :src="artwork"
                  :alt="getTranslated(template.name)"
                  class="w-full h-full object-contain cursor-zoom-in transition hover:opacity-90"
                  @click="lightboxIndex = 0"
                />
                <span v-else class="text-xs text-muted-foreground">No artwork uploaded</span>
              </div>
            </div>
          </div>
        </ShowCard>

        <ShowCard v-if="layoutRows.length" title="Field Layout">
          <!-- Positions are stored as fractions of the card (0..1); shown as
               percentages so they can be read against the artwork above. -->
          <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
              <thead>
                <tr class="border-b border-border text-left">
                  <th class="p-2 font-medium text-muted-foreground">Field</th>
                  <th class="p-2 font-medium text-muted-foreground">X</th>
                  <th class="p-2 font-medium text-muted-foreground">Y</th>
                  <th class="p-2 font-medium text-muted-foreground">Width</th>
                  <th class="p-2 font-medium text-muted-foreground">Height</th>
                  <th class="p-2 font-medium text-muted-foreground">Type</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in layoutRows" :key="row.field" class="border-b border-border/50 last:border-0">
                  <td class="p-2 font-mono text-xs">{{ row.field }}</td>
                  <td class="p-2 tabular-nums">{{ row.x }}</td>
                  <td class="p-2 tabular-nums">{{ row.y }}</td>
                  <td class="p-2 tabular-nums">{{ row.width }}</td>
                  <td class="p-2 tabular-nums">{{ row.height }}</td>
                  <td class="p-2">
                    <span v-if="row.color" class="inline-flex items-center gap-1.5">
                      <span class="w-3 h-3 rounded-sm border border-border" :style="{ backgroundColor: row.color }"></span>
                      <span class="text-xs text-muted-foreground">{{ row.fontSize }}px · {{ row.direction }}</span>
                    </span>
                    <span v-else class="text-xs text-muted-foreground">image / code</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </ShowCard>

        <RecordMeta
          :id="template.id"
          :slug="template.slug"
          :created-at="template.created_at"
          :updated-at="template.updated_at"
        />

        <ShowActions
          :list-href="route('admin.card-templates.index')"
          :edit-href="canManage('manage card templates') ? route('admin.card-templates.edit', template.id) : null"
          edit-label="Edit Layout"
        />
      </div>
    </div>

    <ImageLightbox :images="lightboxImages" v-model:index="lightboxIndex" />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import ImageLightbox from '@/Components/ui/ImageLightbox.vue';
import { Breadcrumb } from '@/Pages/Admin/Layout/Layout.js';
import { ShowCard, ShowField, ShowActions, TranslatedField, RecordMeta } from '@/Pages/Admin/_components/Show';
import { useTranslatedField } from '@/composables/useTranslatedField';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  template: { type: Object, required: true },
  statuses: { type: Array, default: () => [] },
});

const { getTranslated } = useTranslatedField();
const { canManage } = usePermissions();

// The sample card shows the layout in use; the blank artwork is the fallback.
const artwork = computed(() => props.template.sample_card_url || props.template.card_empty_url);

const percent = (value) =>
  typeof value === 'number' ? `${(value * 100).toFixed(1)}%` : '—';

const layoutRows = computed(() =>
  Object.entries(props.template.layout || {}).map(([field, box]) => ({
    field,
    x: percent(box?.x),
    y: percent(box?.y),
    width: percent(box?.width),
    height: percent(box?.height),
    color: box?.color || null,
    fontSize: box?.font_size ?? '—',
    direction: box?.direction || 'ltr',
  }))
);

const lightboxImages = computed(() =>
  artwork.value ? [{ url: artwork.value, alt: getTranslated(props.template.name) }] : []
);

const lightboxIndex = ref(null);
</script>
