<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-lg sm:text-xl font-semibold">
          {{ isNew ? 'New card template' : 'Edit card template' }}
        </h1>
        <Link :href="route('admin.card-templates.index')" class="text-sm text-muted-foreground hover:underline">
          ← Back to templates
        </Link>
      </div>

      <!-- Details -->
      <div class="rounded-xl border border-border bg-card text-card-foreground p-4 sm:p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1">Name (English) *</label>
            <input v-model="form.name.en" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" :disabled="busy" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">الاسم (عربي) *</label>
            <input v-model="form.name.ar" type="text" dir="rtl" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" :disabled="busy" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Status</label>
            <Select
              v-model="form.status"
              :options="statuses"
            />
            <p v-if="forcedHidden.length" class="mt-1 text-[11px] text-muted-foreground">
              Hides: {{ forcedHidden.join(', ') }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Kind</label>
            <Select v-model="form.kind" :options="KINDS" data-testid="template-kind" />
            <p class="mt-1 text-[11px] text-muted-foreground">
              {{ form.kind === 'soft'
                ? 'Soft card: the one the member sees on screen. It also draws the full name and the membership expiry date — drag both where you want them.'
                : 'Physical card: the printed one. It has no full name or expiry date.' }}
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Blank artwork (card_empty)</label>
            <input type="file" accept="image/*" class="w-full text-xs" :disabled="busy" @change="onArtwork($event, 'card_empty')" />
            <p class="mt-1 text-[11px] text-muted-foreground truncate">{{ artworkLabel }}</p>
          </div>
        </div>

        <!-- Sample card: the picture of this design shown on the templates list. -->
        <div class="border-t border-border pt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
          <div class="sm:col-span-2 space-y-2">
            <label class="block text-xs font-medium">Sample card (sample_card)</label>
            <p class="text-[11px] text-muted-foreground">
              The picture of this design shown on the templates list. Upload one, or capture the live
              render below so the sample always matches the current layout.
            </p>
            <div class="flex flex-wrap items-center gap-2">
              <input type="file" accept="image/*" class="text-xs" :disabled="busy" @change="onArtwork($event, 'sample_card')" />
              <button
                type="button"
                class="inline-flex items-center rounded-md h-8 px-2.5 text-xs font-medium bg-slate-700 text-white hover:bg-slate-800 disabled:opacity-50 cursor-pointer"
                :disabled="busy || !renderedCanvas"
                @click="captureSample"
              >
                Use the live render
              </button>
              <button
                v-if="sampleCardFile || sampleCardPreview"
                type="button"
                class="text-xs text-red-600 hover:underline cursor-pointer"
                :disabled="busy"
                @click="clearSample"
              >
                Clear
              </button>
            </div>
            <p class="text-[11px] text-muted-foreground truncate">{{ sampleLabel }}</p>
          </div>
          <div class="rounded-md border border-border bg-muted/30 aspect-[8.5/5.5] flex items-center justify-center overflow-hidden">
            <img v-if="sampleUrl" :src="sampleUrl" alt="" class="w-full h-full object-contain" />
            <span v-else class="text-[11px] text-muted-foreground">No sample yet</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
        <!-- Editor stage -->
        <div class="xl:col-span-3 rounded-xl border border-border bg-card text-card-foreground p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">Layout</h2>
            <button type="button" class="text-xs text-red-600 hover:underline cursor-pointer" :disabled="busy" @click="resetLayout">
              Reset to defaults
            </button>
          </div>

          <p class="text-[11px] text-muted-foreground">
            Drag a field to move it, or drag its bottom-right corner to resize. Positions are stored as
            fractions of the card, so the layout holds at any print size. Any field can be hidden from the
            card with the switch in its panel; a hidden field keeps its place.
          </p>

          <div
            ref="stageRef"
            class="relative w-full select-none overflow-hidden rounded-md border border-border bg-muted/30"
            :style="{ aspectRatio: `${aspect}` }"
          >
            <img v-if="artworkUrl" :src="artworkUrl" alt="" class="absolute inset-0 w-full h-full object-contain pointer-events-none" />

            <div
              v-for="key in visibleFields"
              :key="key"
              class="absolute border-2 cursor-move"
              :class="key === selected ? 'border-primary bg-primary/10 z-10' : 'border-sky-400/70 bg-sky-400/5 hover:bg-sky-400/15'"
              :style="boxStyle(key)"
              @pointerdown="startDrag($event, key, 'move')"
            >
              <span class="absolute -top-4 left-0 text-[9px] font-medium text-sky-700 whitespace-nowrap">{{ label(key) }}</span>

              <!-- The field's actual content, styled the way the renderer draws
                   it, so the stage reads as the finished card rather than a
                   set of empty boxes. -->
              <div
                v-if="isTextField(key)"
                class="w-full h-full flex items-center overflow-hidden whitespace-pre pointer-events-none"
                :style="textStyle(key)"
              >{{ stageText(key) }}</div>
              <!-- Real QR / barcode, generated from the value below the stage. -->
              <div v-else-if="key === 'qrcode' && qrDataUri" class="w-full h-full bg-white p-[7.8%] pointer-events-none">
                <img :src="qrDataUri" alt="" class="w-full h-full object-contain" />
              </div>
              <img
                v-else-if="key === 'barcode' && barcodeDataUri"
                :src="barcodeDataUri"
                alt=""
                class="w-full h-full pointer-events-none"
              />
              <img
                v-else-if="isImageField(key) && sampleImageUrl(key)"
                :src="sampleImageUrl(key)"
                alt=""
                class="w-full h-full object-contain pointer-events-none"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center overflow-hidden pointer-events-none text-[9px] font-medium uppercase tracking-wide text-slate-500"
              >{{ label(key) }}</div>

              <span
                class="absolute -bottom-1 -right-1 h-3 w-3 rounded-sm bg-primary cursor-nwse-resize"
                @pointerdown.stop="startDrag($event, key, 'resize')"
              ></span>
            </div>
          </div>

          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="key in availableFields"
              :key="key"
              type="button"
              class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium cursor-pointer"
              :class="[
                key === selected ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/70',
                isHidden(key) ? 'line-through opacity-70' : '',
              ]"
              :title="isHidden(key) ? 'Hidden from the card' : ''"
              :data-testid="`field-chip-${key}`"
              @click="selected = key"
            >
              <svg v-if="isHidden(key)" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                <line x1="2" x2="22" y1="2" y2="22"/>
              </svg>
              {{ label(key) }}
            </button>
          </div>
          <p v-if="adminHidden.length" class="text-[11px] value-warning" data-testid="hidden-fields-note">
            Hidden from the card: {{ adminHidden.map(label).join(', ') }}
          </p>
        </div>

        <!-- Selected field -->
        <div class="rounded-xl border border-border bg-card text-card-foreground p-4 space-y-3">
          <h2 class="text-sm font-semibold">{{ label(selected) }}</h2>

          <template v-if="field">
            <label class="flex items-center justify-between gap-2 rounded-md border border-border px-2.5 py-2 cursor-pointer">
              <span class="min-w-0">
                <span class="block text-xs font-medium">Show on the card</span>
                <span class="block text-[10px] text-muted-foreground">
                  {{ field.hidden ? 'Hidden: not drawn on any card from this template.' : 'Drawn on every card from this template.' }}
                </span>
              </span>
              <input
                type="checkbox"
                class="h-4 w-4 shrink-0 cursor-pointer"
                :checked="!field.hidden"
                :disabled="busy"
                data-testid="field-visible-toggle"
                @change="setHidden(selected, !$event.target.checked)"
              />
            </label>
            <span v-if="field.hidden" class="chip chip-warning" data-testid="field-hidden-chip">Hidden</span>

            <div class="grid grid-cols-2 gap-2">
              <label v-for="k in ['x', 'y', 'width', 'height']" :key="k" class="text-[10px] text-muted-foreground uppercase">
                {{ k }} (%)
                <input
                  type="number"
                  step="0.1"
                  class="w-full rounded border border-border bg-background px-2 py-1 text-xs"
                  :value="round(field[k] * 100)"
                  :disabled="busy"
                  @input="setFraction(k, $event.target.value)"
                />
              </label>
            </div>

            <template v-if="isTextField(selected)">
              <label class="block text-[10px] text-muted-foreground uppercase">
                Value
                <input v-model="form.sample_data[selected]" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <div class="grid grid-cols-2 gap-2">
                <label class="text-[10px] text-muted-foreground uppercase">
                  Font size
                  <input v-model.number="field.font_size" type="number" step="0.5" min="1" max="200" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground uppercase">
                  Align
                  <Select v-model="field.direction" :options="directionOptions" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground uppercase">
                  Colour
                  <input v-model="field.color" type="color" class="w-full h-7 rounded border border-border bg-background" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground uppercase">
                  Font
                  <input v-model="field.font_family" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
                </label>
              </div>
              <fieldset v-if="selected === 'expiry_date'" class="rounded border border-border p-2">
                <legend class="px-1 text-[11px] font-medium">اللي يظهر من التاريخ</legend>
                <div class="flex flex-wrap gap-4">
                  <label v-for="part in DATE_PARTS" :key="part.key" class="flex items-center gap-2 text-xs font-medium cursor-pointer">
                    <input
                      type="checkbox"
                      class="h-4 w-4 cursor-pointer"
                      :data-testid="`date-${part.key}`"
                      :checked="field[part.key] !== false"
                      :disabled="busy || isLastDatePart(part.key)"
                      @change="field[part.key] = $event.target.checked"
                    />
                    <span>{{ part.label }}</span>
                  </label>
                </div>
              </fieldset>

              <label class="flex items-center gap-2 text-xs font-medium cursor-pointer">
                <input
                  type="checkbox"
                  class="h-4 w-4 cursor-pointer"
                  data-testid="field-bold"
                  :checked="field.bold !== false"
                  :disabled="busy"
                  @change="field.bold = $event.target.checked"
                />
                <span class="font-bold">Bold — خط عريض</span>
              </label>
              <p class="text-[10px] text-muted-foreground">
                Font size is pixels on a {{ defaults.editor_width }}px-wide card and scales with the render.
              </p>
            </template>

            <template v-else-if="selected === 'qrcode'">
              <label class="block text-[10px] text-muted-foreground uppercase">
                Sample payload
                <input v-model="form.sample_data.qrcode" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <p class="text-[10px] text-muted-foreground">
                Generated cards encode the member's own page URL; this value is only for previewing.
              </p>
            </template>

            <template v-else-if="selected === 'barcode'">
              <label class="block text-[10px] text-muted-foreground uppercase">
                Sample value
                <input v-model="form.sample_data.barcode" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <p class="text-[10px] text-muted-foreground">
                Code 128 — numeric values encode in subset C, which gives wider, more scannable bars.
                Generated cards encode the membership number.
              </p>
            </template>

            <template v-else-if="isUploadableImageField(selected)">
              <span class="block text-[10px] text-muted-foreground uppercase">Image</span>

              <div class="flex items-start gap-3">
                <div class="w-16 h-16 shrink-0 rounded border border-border bg-muted/30 flex items-center justify-center overflow-hidden">
                  <img v-if="sampleImageUrl(selected)" :src="sampleImageUrl(selected)" alt="" class="w-full h-full object-contain" />
                  <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground">
                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                    <circle cx="9" cy="9" r="2"/>
                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                  </svg>
                </div>

                <div class="min-w-0 flex-1 space-y-1">
                  <div class="flex flex-wrap gap-2">
                    <label
                      class="inline-flex items-center gap-1 rounded border border-border px-2 py-1 text-[11px] cursor-pointer hover:bg-muted"
                      :class="busy ? 'opacity-50 pointer-events-none' : ''"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                      </svg>
                      {{ sampleImageUrl(selected) ? 'Replace' : 'Upload' }}
                      <input type="file" accept="image/*" class="hidden" :disabled="busy" @change="onSampleImage($event, selected)" />
                    </label>
                    <button
                      v-if="sampleImageUrl(selected)"
                      type="button"
                      class="text-[11px] text-red-600 hover:underline cursor-pointer"
                      :disabled="busy"
                      @click="clearSampleImage(selected)"
                    >
                      Remove
                    </button>
                  </div>
                  <p class="text-[10px] text-muted-foreground truncate" :title="sampleImageLabel(selected)">
                    {{ sampleImageLabel(selected) }}
                  </p>
                </div>
              </div>

              <p v-if="sampleImageFiles[selected]" class="text-[10px] text-amber-600">
                Uploads when you save the template.
              </p>
              <p class="text-[10px] text-muted-foreground">
                PNG or JPG, any size.
                <template v-if="selected === 'partner_logo'">
                  A generated card uses the member's own partner logo; this image is the template's
                  placeholder, shown in previews and on cards whose partner has no logo of its own.
                </template>
                <template v-else>
                  Fixed for the whole template — every card cut from it prints this logo.
                </template>
              </p>
            </template>

            <p v-else class="text-[11px] text-muted-foreground">
              Filled per member when the card is generated.
            </p>
          </template>
          <p v-else class="text-[11px] text-muted-foreground">Pick a field to edit it.</p>
        </div>
      </div>

      <!-- Live render -->
      <div class="rounded-xl border border-border bg-card text-card-foreground p-4 space-y-3">
        <h2 class="text-sm font-semibold">Live render</h2>
        <p class="text-[11px] text-muted-foreground">
          The real canvas output, exactly as a generated card will print.
        </p>
        <div class="max-w-3xl">
          <CardPreview
            :membership="previewMembership"
            :template="previewTemplate"
            placeholder-partner-logo
            display-width="100%"
            @rendered="onRendered"
          />
        </div>
      </div>

      <div v-if="error" class="rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">{{ error }}</div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
          :disabled="busy"
          @click="save({ stay: false })"
        >
          {{ busy ? 'Saving…' : (isNew ? 'Create template' : 'Save changes') }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium border border-emerald-600 text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 cursor-pointer"
          :disabled="busy"
          title="Save and keep editing"
          @click="save({ stay: true })"
        >
          Save and stay
        </button>
        <Link :href="route('admin.card-templates.index')" class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium bg-muted hover:bg-muted/70">
          Cancel
        </Link>
        <span v-if="savedAt" class="text-[11px] text-emerald-700">Saved {{ savedAt }}</span>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, shallowRef, computed, reactive, watch, onBeforeUnmount } from 'vue';
import Select from '@/Components/ui/Select.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import QRCode from 'qrcode';
import { useElementSize } from '@vueuse/core';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import CardPreview from '@/Pages/Admin/MembershipCard/_components/CardPreview.vue';
import { drawBarcode } from '@/Pages/Admin/MembershipCard/_components/code128.js';
import { assetUrl } from '@/Pages/Admin/MembershipCard/_components/cardRenderer.js';
import { applyDateParts } from '@/Pages/Admin/MembershipCard/_components/dateParts.js';

// How a field's text sits in its box.
const directionOptions = [
  { value: 'ltr', label: 'ltr (left)' },
  { value: 'center', label: 'center' },
  { value: 'rtl', label: 'rtl (right)' },
];

// Soft = the card shown on screen (adds the full name + expiry date); physical = the printed card.
const KINDS = [
  { value: 'physical', label: 'Physical card' },
  { value: 'soft', label: 'Soft card (on screen)' },
];
const SOFT_ONLY_FIELDS = ['full_name', 'expiry_date'];

const props = defineProps({
  template: { type: Object, default: null },
  statuses: { type: Array, default: () => [] },
  defaults: { type: Object, required: true },
});

// The row as the server last confirmed it. Starts as the Inertia prop and is
// refreshed by "Save and stay", so creating a template then carrying on editing
// updates that same row instead of creating a new one each save.
const serverTemplate = ref(props.template ? { ...props.template } : null);
const savedId = ref(props.template?.id ?? null);
const savedAt = ref('');

const isNew = computed(() => !savedId.value);

const LABELS = {
  logo: 'Logo',
  slogan: 'Slogan',
  membership_number: 'Number',
  facebook: 'Facebook',
  website: 'Website',
  phone: 'Phone',
  full_name: 'Full name',
  expiry_date: 'Expiry date',
  partner_logo: 'Partner logo',
  qrcode: 'QR code',
  barcode: 'Barcode',
};

// Inertia props arrive wrapped in Vue reactive Proxies, which structuredClone
// refuses to touch — layout/sample_data are plain JSON, so round-trip instead.
function clone(value) {
  return JSON.parse(JSON.stringify(value ?? null));
}

function nameOf(value) {
  if (!value) return { ar: '', en: '' };
  if (typeof value === 'string') return { ar: value, en: value };
  return { ar: value.ar ?? '', en: value.en ?? '' };
}

const form = reactive({
  name: nameOf(props.template?.name),
  status: props.template?.status ?? props.statuses[0]?.value ?? 'with_partner',
  kind: props.template?.kind ?? 'physical',
  // Defaults first, saved values second: a template stored before a field was
  // added still picks the new field up, while everything it already positioned
  // keeps its saved values.
  layout: { ...clone(props.defaults.layout), ...clone(props.template?.layout || {}) },
  sample_data: { ...clone(props.defaults.sample_data), ...clone(props.template?.sample_data || {}) },
});

const busy = ref(false);
const error = ref('');
const selected = ref(null);
const stageRef = ref(null);
const artworkFile = ref(null);
const artworkPreview = ref(null);
const sampleCardFile = ref(null);
const sampleCardPreview = ref(null);
const sampleCleared = ref(false);
const aspect = ref(8.5 / 5.5);
// shallowRef so Vue doesn't deep-watch the canvas pixel buffer.
const renderedCanvas = shallowRef(null);

// The stage's real on-screen width, so stored font sizes (measured at
// defaults.editor_width) can be rescaled to whatever the panel is showing.
const { width: stageWidth } = useElementSize(stageRef);

const artworkUrl = computed(() => artworkPreview.value || serverTemplate.value?.card_empty_url || '/images/cards/deilar-card-blank.png');
const artworkLabel = computed(() => (artworkFile.value ? artworkFile.value.name : (serverTemplate.value?.card_empty || 'Using the bundled Deilar artwork')));

const sampleUrl = computed(() => {
  if (sampleCardPreview.value) return sampleCardPreview.value;
  if (sampleCleared.value) return null;
  return serverTemplate.value?.sample_card_url || null;
});
const sampleLabel = computed(() => {
  if (sampleCardFile.value) return `New: ${sampleCardFile.value.name}`;
  if (sampleCleared.value) return 'Cleared — save to remove it.';
  return serverTemplate.value?.sample_card || 'None yet.';
});

// Hidden by the status / kind: these fields do not exist on this card at all.
const forcedHidden = computed(() => [
  ...(props.statuses.find((s) => s.value === form.status)?.hidden_fields ?? []),
  // The full name and expiry date exist on soft cards only; a soft card has no barcode (the expiry date takes its place).
  ...(form.kind === 'soft' ? ['barcode'] : SOFT_ONLY_FIELDS),
]);
// Fields this card can carry — the ones the admin may show or hide.
const availableFields = computed(() => Object.keys(form.layout).filter((k) => !forcedHidden.value.includes(k)));
// Hidden by the admin with the switch (`layout.<field>.hidden`): the box keeps its place.
const isHidden = (key) => !!form.layout[key]?.hidden;
const adminHidden = computed(() => availableFields.value.filter(isHidden));
// Everything the renderer must skip.
const hiddenFields = computed(() => [...forcedHidden.value, ...adminHidden.value]);
// What the stage draws.
const visibleFields = computed(() => availableFields.value.filter((k) => !isHidden(k)));

function setHidden(key, hidden) {
  if (!key || !form.layout[key]) return;
  form.layout[key].hidden = !!hidden;
}
const field = computed(() => (selected.value ? form.layout[selected.value] : null));

const isTextField = (key) => props.defaults.text_fields.includes(key);
const isImageField = (key) => props.defaults.image_fields.includes(key);
// Image slots the admin can upload a template-level picture for. The backend
// accepts these as `sample_{field}` files (CardTemplateRequest).
const isUploadableImageField = (key) => props.defaults.image_fields.includes(key);
const label = (key) => LABELS[key] ?? key ?? '—';
const round = (n) => Math.round(Number(n) * 10) / 10;

// The renderer takes the same shape a card_templates row has, so the preview
// re-renders straight from the in-progress form.
const workingTemplate = computed(() => ({
  layout: form.layout,
  sample_data: form.sample_data,
  card_empty_url: artworkUrl.value,
  hidden_fields: hiddenFields.value,
}));

// A full canvas repaint reloads the artwork, re-encodes the QR and redraws the
// barcode, so it must never run per pointer-move. Dragging updates the HTML
// stage above (cheap, plain positioned divs) and the canvas catches up once
// movement settles. CardPreview repaints itself off these props, so there is
// deliberately no :key here — remounting it on every change was what made the
// page lock up while dragging a field.
const PREVIEW_DEBOUNCE_MS = 200;

const previewMembership = computed(() => ({
  membership_number: form.sample_data.membership_number || 'MEM-1000',
  slug: 'preview',
}));

const previewTemplate = shallowRef(clone(workingTemplate.value));
let previewTimer = null;

watch(
  () => JSON.stringify([form.layout, form.sample_data, artworkUrl.value, hiddenFields.value]),
  () => {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(() => {
      previewTemplate.value = clone(workingTemplate.value);
    }, PREVIEW_DEBOUNCE_MS);
  },
);

onBeforeUnmount(() => {
  if (previewTimer) clearTimeout(previewTimer);
});

// --- Live QR / barcode for the stage, rebuilt whenever their value changes.
const qrDataUri = ref(null);
const barcodeDataUri = ref(null);

const barcodeValue = computed(
  () => form.sample_data.barcode || form.sample_data.membership_number || '',
);

watch(
  () => form.sample_data.qrcode,
  async (value) => {
    qrDataUri.value = value
      ? await QRCode.toDataURL(value, { margin: 0, width: 512, errorCorrectionLevel: 'M' }).catch(() => null)
      : null;
  },
  { immediate: true },
);

watch(
  barcodeValue,
  (value) => {
    if (!value) {
      barcodeDataUri.value = null;
      return;
    }
    // Drawn oversized then stretched into the field box, so the bars stay crisp
    // however wide the stage is.
    const canvas = document.createElement('canvas');
    canvas.width = 1000;
    canvas.height = 200;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    drawBarcode(ctx, value, { x: 0, y: 0, width: canvas.width, height: canvas.height, color: '#000000' });
    barcodeDataUri.value = canvas.toDataURL();
  },
  { immediate: true },
);

// --- Sample images (the logo slot) are uploaded, not pasted as a URL.
// The file rides along with the template save as `sample_{field}`; the backend
// stores it and writes the resulting path into sample_data. Until then the
// stage and preview show a local object URL so the change is visible at once.
const sampleImageFiles = reactive({});   // field -> File pending upload
const sampleImagePreviews = reactive({}); // field -> blob: URL for that File

function sampleImageUrl(field) {
  return sampleImagePreviews[field] || assetUrl(form.sample_data[field]) || '';
}

function sampleImageLabel(field) {
  if (sampleImageFiles[field]) return sampleImageFiles[field].name;
  return form.sample_data[field] || 'No image yet.';
}

function onSampleImage(event, field) {
  const file = event.target.files?.[0];
  // Let the same file be picked again after a Remove.
  event.target.value = '';
  if (!file) return;

  revokeSampleImage(field);
  sampleImageFiles[field] = file;
  sampleImagePreviews[field] = URL.createObjectURL(file);
  // Point sample_data at the local preview so the canvas render picks it up
  // immediately; save() swaps in the stored path the server returns.
  form.sample_data[field] = sampleImagePreviews[field];
}

function clearSampleImage(field) {
  revokeSampleImage(field);
  delete sampleImageFiles[field];
  form.sample_data[field] = '';
}

function revokeSampleImage(field) {
  if (sampleImagePreviews[field]) {
    URL.revokeObjectURL(sampleImagePreviews[field]);
    delete sampleImagePreviews[field];
  }
}

onBeforeUnmount(() => {
  Object.keys(sampleImagePreviews).forEach(revokeSampleImage);
});

// Track the artwork's own proportions so the drag stage matches the card.
function measureArtwork(url) {
  const img = new Image();
  img.onload = () => {
    /* card is always 8.5 × 5.5 cm */
  };
  img.src = url;
}
measureArtwork(artworkUrl.value);

function onArtwork(event, column) {
  const file = event.target.files?.[0];
  if (!file) return;
  if (column === 'card_empty') {
    artworkFile.value = file;
    artworkPreview.value = URL.createObjectURL(file);
    measureArtwork(artworkPreview.value);
  } else {
    sampleCardFile.value = file;
    sampleCardPreview.value = URL.createObjectURL(file);
    sampleCleared.value = false;
  }
}

function onRendered({ canvas }) {
  renderedCanvas.value = canvas;
}

/** Snapshot the live render and use it as this template's sample card. */
function captureSample() {
  const canvas = renderedCanvas.value;
  if (!canvas) return;
  canvas.toBlob((blob) => {
    if (!blob) return;
    sampleCardFile.value = new File([blob], 'sample-card.png', { type: 'image/png' });
    sampleCardPreview.value = URL.createObjectURL(blob);
    sampleCleared.value = false;
  }, 'image/png');
}

function clearSample() {
  sampleCardFile.value = null;
  sampleCardPreview.value = null;
  sampleCleared.value = true;
}

const DATE_PARTS = [
  { key: 'show_day', label: 'اليوم' },
  { key: 'show_month', label: 'الشهر' },
  { key: 'show_year', label: 'السنة' },
];

/** The only part still shown cannot be switched off: a date field prints something. */
function isLastDatePart(key) {
  const shown = DATE_PARTS.filter((part) => field.value?.[part.key] !== false);
  return shown.length === 1 && shown[0].key === key;
}

function stageText(key) {
  const text = form.sample_data[key];
  return key === 'expiry_date' ? applyDateParts(text, form.layout[key]) : text;
}

function boxStyle(key) {
  const f = form.layout[key];
  return {
    left: `${f.x * 100}%`,
    top: `${f.y * 100}%`,
    width: `${f.width * 100}%`,
    height: `${f.height * 100}%`,
  };
}

/**
 * Font/colour/alignment for a text field on the stage. `font_size` is stored in
 * pixels at `defaults.editor_width`, so it is rescaled to the stage's real
 * on-screen width — the same rescale the renderer does against the card width.
 */
function textStyle(key) {
  const f = form.layout[key];
  const scale = (stageWidth.value || props.defaults.editor_width) / props.defaults.editor_width;
  const direction = f.direction || 'ltr';
  return {
    fontSize: `${(f.font_size || 14) * scale}px`,
    fontFamily: `${f.font_family || 'Tajawal'}, Arial, sans-serif`,
    fontWeight: f.bold === false ? 400 : 700,
    color: f.color || '#000000',
    direction: direction === 'rtl' ? 'rtl' : 'ltr',
    justifyContent: direction === 'rtl' ? 'flex-end' : (direction === 'center' ? 'center' : 'flex-start'),
    textAlign: direction === 'rtl' ? 'right' : (direction === 'center' ? 'center' : 'left'),
  };
}

function setFraction(key, percent) {
  const value = Number(percent);
  if (!Number.isFinite(value)) return;
  form.layout[selected.value][key] = value / 100;
}

function startDrag(event, key, mode) {
  if (busy.value) return;
  selected.value = key;

  const stage = stageRef.value;
  if (!stage) return;
  event.preventDefault();
  const rect = stage.getBoundingClientRect();
  const f = form.layout[key];
  // Snapshot both the pointer position and the field's starting fractions, so
  // every move is measured from where the drag began rather than accumulating.
  const origin = {
    pointerX: event.clientX,
    pointerY: event.clientY,
    x: f.x,
    y: f.y,
    width: f.width,
    height: f.height,
  };

  const onMove = (e) => {
    const dx = (e.clientX - origin.pointerX) / rect.width;
    const dy = (e.clientY - origin.pointerY) / rect.height;
    const target = form.layout[key];
    if (mode === 'move') {
      target.x = clamp(origin.x + dx);
      target.y = clamp(origin.y + dy);
    } else {
      target.width = clamp(origin.width + dx, 0.01);
      target.height = clamp(origin.height + dy, 0.01);
    }
  };

  const onUp = () => {
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
  };
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
}

function clamp(value, min = -1) {
  return Math.min(2, Math.max(min, Number(value.toFixed(4))));
}

function resetLayout() {
  form.layout = clone(props.defaults.layout);
  form.sample_data = clone(props.defaults.sample_data);
}

async function save({ stay = false } = {}) {
  if (busy.value) return;
  busy.value = true;
  error.value = '';

  try {
    const fd = new FormData();
    fd.append('name', JSON.stringify(form.name));
    fd.append('status', form.status);
    fd.append('kind', form.kind);
    fd.append('layout', JSON.stringify(form.layout));
    // A pending upload's value is a local blob: URL, which must never be
    // persisted — send the field blank and let the server fill it in from the
    // uploaded file it stores.
    const sampleData = { ...form.sample_data };
    Object.keys(sampleImageFiles).forEach((field) => { sampleData[field] = ''; });
    fd.append('sample_data', JSON.stringify(sampleData));
    Object.entries(sampleImageFiles).forEach(([field, file]) => fd.append(`sample_${field}`, file));

    if (artworkFile.value) fd.append('card_empty', artworkFile.value);
    if (sampleCardFile.value) fd.append('sample_card', sampleCardFile.value);
    if (sampleCleared.value && !sampleCardFile.value) fd.append('clear_sample_card', '1');

    const url = savedId.value
      ? route('admin.card-templates.update', savedId.value)
      : route('admin.card-templates.store');

    const { data } = await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });

    if (!stay) {
      router.visit(route('admin.card-templates.index'));
      return;
    }

    // Staying put: adopt whatever the server actually stored, so the next save
    // updates this row instead of creating another one, and uploaded files are
    // replaced by their stored paths rather than dead blob: URLs.
    const wasNew = isNew.value;
    const saved = data?.data;
    if (saved?.id) {
      savedId.value = saved.id;
      // A create becomes an edit — keep the address bar honest so a refresh
      // reopens the row that was just created.
      if (wasNew) {
        window.history.replaceState({}, '', route('admin.card-templates.edit', saved.id));
      }
    }
    if (saved?.sample_data) {
      Object.keys(sampleImageFiles).forEach((field) => {
        revokeSampleImage(field);
        delete sampleImageFiles[field];
        form.sample_data[field] = saved.sample_data[field] ?? '';
      });
    }
    // The artwork/sample card are now on disk; drop the pending-upload state.
    artworkFile.value = null;
    if (saved?.card_empty_url) artworkPreview.value = null;
    sampleCardFile.value = null;
    sampleCardPreview.value = null;
    sampleCleared.value = false;
    serverTemplate.value = saved ?? serverTemplate.value;

    savedAt.value = new Date().toLocaleTimeString();
    busy.value = false;
  } catch (e) {
    error.value = saveErrorMessage(e);
    busy.value = false;
  }
}

/** Turn an axios failure into something the admin can act on. */
function saveErrorMessage(e) {
  const status = e?.response?.status;
  if (status === 422) {
    return Object.values(e.response.data?.errors || {}).flat().join(' ') || 'Validation failed.';
  }
  if (status === 419) {
    return 'Your session expired while this page was open. Reload the page and save again.';
  }
  if (status === 401 || status === 403) {
    return 'You are no longer signed in, or lack permission to save card templates. Reload the page and sign in again.';
  }
  if (status === 404) {
    return 'The save endpoint could not be reached. Reload the page and try again — if it persists, the app may have been restarted mid-request.';
  }
  if (status === 413) {
    return 'The upload is too large for the server — check the server upload limit (post_max_size / nginx client_max_body_size).';
  }
  return e?.response?.data?.message || e?.message || 'Could not save the template.';
}

selected.value = visibleFields.value[0] ?? availableFields.value[0] ?? null;
</script>
