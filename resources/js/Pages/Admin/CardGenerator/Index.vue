<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-lg sm:text-xl font-semibold">{{ t.title || 'Card generator' }}</h1>
        <Link v-if="activeTemplate" :href="route('admin.card-templates.edit', activeTemplate.id)" class="text-sm text-muted-foreground hover:underline">
          {{ t.edit_template || 'Edit this design' }} →
        </Link>
      </div>

      <!-- Card details -->
      <div class="rounded-xl border border-border bg-card text-card-foreground p-4 sm:p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs font-medium mb-1">{{ t.partner || 'Partner' }}</label>
            <select v-model="partnerId" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" :disabled="busy">
              <option value="">{{ t.none || '— no partner —' }}</option>
              <option v-for="p in partners" :key="p.id" :value="String(p.id)">{{ p.title }}</option>
            </select>
            <p class="mt-1 text-[11px] text-muted-foreground">
              {{ t.partner_picks_design || 'The partner decides which design is used.' }}
            </p>
          </div>

          <div>
            <label class="block text-xs font-medium mb-1">{{ t.field_membership_number || 'Membership number' }}</label>
            <input v-model="values.membership_number" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" :disabled="busy" />
            <button
              v-if="numberChanged"
              type="button"
              class="mt-1 text-[11px] text-emerald-700 hover:underline cursor-pointer"
              :disabled="busy"
              @click="saveNumber"
            >
              {{ t.save_number || 'Save this number to the membership' }} (was {{ original.membership_number || '—' }})
            </button>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-xs font-medium mb-1">{{ t.design || 'Design' }}</label>
            <div class="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm">
              <template v-if="activeTemplate">
                <span class="font-medium">{{ templateName(activeTemplate) }}</span>
                <span class="text-muted-foreground">
                  — {{ activeTemplate.status === 'no_partner' ? (t.template_no_partner || 'No partners') : (t.template_with_partner || 'With partner') }}
                </span>
                <span v-if="hiddenFields.length" class="text-[11px] text-muted-foreground block">
                  {{ t.hides || 'Hides' }}: {{ hiddenFields.join(', ') }}
                </span>
              </template>
              <span v-else class="text-muted-foreground">
                {{ t.no_template || 'No card template found — using the bundled artwork.' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
        <!-- Editor stage -->
        <div class="xl:col-span-3 rounded-xl border border-border bg-card text-card-foreground p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ t.layout || 'Layout' }}</h2>
            <button type="button" class="text-xs text-red-600 hover:underline cursor-pointer" :disabled="busy" @click="resetLayout">
              {{ t.reset_positions || 'Reset positions' }}
            </button>
          </div>

          <p class="text-[11px] text-muted-foreground">
            {{ t.stage_hint || 'Drag a field to move it, or drag its bottom-right corner to resize. Positions are stored as fractions of the card and apply to this card only.' }}
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
              <span class="absolute -top-4 left-0 text-[9px] font-medium text-sky-700 whitespace-nowrap">{{ fieldLabel(key) }}</span>

              <!-- The field's own content, drawn the way the renderer draws it,
                   so the stage reads as the finished card. -->
              <div
                v-if="isTextField(key)"
                class="w-full h-full flex items-center overflow-hidden whitespace-pre pointer-events-none"
                :style="textStyle(key)"
              >{{ values[key] }}</div>
              <div v-else-if="key === 'qrcode' && qrDataUri" class="w-full h-full bg-white p-[7.8%] pointer-events-none">
                <img :src="qrDataUri" alt="" class="w-full h-full object-contain" />
              </div>
              <img v-else-if="key === 'barcode' && barcodeDataUri" :src="barcodeDataUri" alt="" class="w-full h-full pointer-events-none" />
              <img v-else-if="isImageField(key) && imageFor(key)" :src="imageFor(key)" alt="" class="w-full h-full object-contain pointer-events-none" />
              <div
                v-else
                class="w-full h-full flex items-center justify-center overflow-hidden pointer-events-none text-[9px] font-medium uppercase tracking-wide text-slate-500"
              >{{ fieldLabel(key) }}</div>

              <span
                class="absolute -bottom-1 -right-1 h-3 w-3 rounded-sm bg-primary cursor-nwse-resize"
                @pointerdown.stop="startDrag($event, key, 'resize')"
              ></span>
            </div>
          </div>

          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="key in visibleFields"
              :key="key"
              type="button"
              class="rounded-md px-2 py-1 text-[11px] font-medium cursor-pointer"
              :class="key === selected ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/70'"
              @click="selected = key"
            >
              {{ fieldLabel(key) }}
            </button>
          </div>
        </div>

        <!-- Selected field -->
        <div class="rounded-xl border border-border bg-card text-card-foreground p-4 space-y-3">
          <h2 class="text-sm font-semibold">{{ selected ? fieldLabel(selected) : (t.pick_field || 'Pick a field') }}</h2>

          <template v-if="field">
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
                {{ t.value || 'Value' }}
                <input v-model="values[selected]" type="text" dir="auto" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <div class="grid grid-cols-2 gap-2">
                <label class="text-[10px] text-muted-foreground uppercase">
                  {{ t.font_size || 'Font size' }}
                  <input v-model.number="field.font_size" type="number" step="0.5" min="1" max="200" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground uppercase">
                  {{ t.align || 'Align' }}
                  <select v-model="field.direction" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy">
                    <option value="ltr">ltr (left)</option>
                    <option value="center">center</option>
                    <option value="rtl">rtl (right)</option>
                  </select>
                </label>
                <label class="text-[10px] text-muted-foreground uppercase">
                  {{ t.layout_color || 'Colour' }}
                  <input v-model="field.color" type="color" class="w-full h-7 rounded border border-border bg-background" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground uppercase">
                  {{ t.font || 'Font' }}
                  <input v-model="field.font_family" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
                </label>
              </div>
              <p class="text-[10px] text-muted-foreground">
                {{ t.font_note || 'Font size is pixels on a 700px-wide card and scales with the render.' }}
              </p>
            </template>

            <template v-else-if="selected === 'qrcode'">
              <label class="block text-[10px] text-muted-foreground uppercase">
                {{ t.field_qrcode || 'QR payload' }}
                <input v-model="values.qrcode" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <p class="text-[10px] text-muted-foreground">{{ t.qr_help || 'Scanned cards open this address.' }}</p>
            </template>

            <template v-else-if="selected === 'barcode'">
              <label class="block text-[10px] text-muted-foreground uppercase">
                {{ t.field_barcode || 'Barcode' }}
                <input v-model="values.barcode" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <p class="text-[10px] text-muted-foreground">
                {{ t.barcode_help || 'Code 128 — numeric values encode in subset C, which gives wider, more scannable bars.' }}
              </p>
            </template>

            <template v-else-if="isImageField(selected)">
              <div class="flex items-start gap-3">
                <div class="w-16 h-16 shrink-0 rounded border border-border bg-muted/30 flex items-center justify-center overflow-hidden">
                  <img v-if="imageFor(selected)" :src="imageFor(selected)" alt="" class="w-full h-full object-contain" />
                  <span v-else class="text-[10px] text-muted-foreground">—</span>
                </div>
                <p class="text-[10px] text-muted-foreground flex-1">{{ imageNote(selected) }}</p>
              </div>
            </template>
          </template>
          <p v-else class="text-[11px] text-muted-foreground">{{ t.pick_field_hint || 'Pick a field to edit it.' }}</p>
        </div>
      </div>

      <!-- Live render -->
      <div class="rounded-xl border border-border bg-card text-card-foreground p-4 space-y-3">
        <h2 class="text-sm font-semibold">{{ t.live_render || 'Live render' }}</h2>
        <p class="text-[11px] text-muted-foreground">{{ t.live_render_hint || 'The real canvas output, exactly as this card will print.' }}</p>
        <div class="max-w-3xl">
          <CardPreview
            :membership="previewMembership"
            :template="previewTemplate"
            :partner="selectedPartner"
            :values="renderValues"
            placeholder-partner-logo
            display-width="100%"
            @rendered="onRendered"
          />
        </div>
      </div>

      <div v-if="error" class="rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">{{ error }}</div>

      <!-- Whether this card is pinned to its own look or still follows the
           design, spelled out before saving rather than after. -->
      <div
        class="rounded-md border px-3 py-2 text-xs"
        :class="isCustomised ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-border bg-muted/30 text-muted-foreground'"
      >
        <template v-if="isCustomised">
          <span class="font-medium">{{ t.custom_card || 'This card will look different from the design.' }}</span>
          {{ t.custom_card_note || 'Saving keeps this look for this member, and later changes to the design will not reach it.' }}
          <span class="block mt-0.5">{{ t.changed || 'Changed' }}: {{ changeSummary }}</span>
        </template>
        <template v-else>
          <span class="font-medium">{{ t.follows_design || 'This card follows the design.' }}</span>
          {{ t.follows_design_note || 'Nothing is changed from the template, so the card updates whenever the design does.' }}
        </template>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
          :disabled="busy || !values.membership_number"
          @click="requestSave('return')"
        >
          {{ busy ? (t.saving || 'Saving…') : (t.save_and_return || 'Save and return') }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium border border-emerald-600 text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 cursor-pointer"
          :disabled="busy || !values.membership_number"
          :title="t.save_and_stay_hint || 'Save and keep editing'"
          @click="requestSave('stay')"
        >
          {{ t.save_and_stay || 'Save and stay' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium border border-border hover:bg-muted disabled:opacity-50 cursor-pointer"
          :disabled="!renderedCanvas"
          @click="downloadCard"
        >
          {{ t.download_card || 'Download card image' }}
        </button>
        <button
          type="button"
          class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50 cursor-pointer"
          :disabled="busy || !values.membership_number"
          :title="t.return_to_default_hint || 'Drop this card\'s own look and save it following the design again'"
          @click="requestReturnToDefault"
        >
          {{ t.return_to_default || 'Return to the default' }}
        </button>
        <button type="button" class="inline-flex items-center rounded-md h-9 px-4 text-sm font-medium bg-muted hover:bg-muted/70 cursor-pointer" @click="goBack">
          {{ t.back || 'Back' }}
        </button>
        <span v-if="savedAt" class="text-[11px] text-emerald-700">{{ t.saved || 'Saved' }} {{ savedAt }}</span>
      </div>
    </div>

    <!-- Both directions are worth confirming: pinning a card to its own look,
         and giving that look up to follow the design again. -->
    <div v-if="confirmMode" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="confirmMode = null">
      <div class="w-full max-w-md rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="border-b border-border px-4 py-3">
          <h2 class="text-sm font-semibold">
            {{ confirmMode === 'custom'
              ? (t.custom_card_title || 'This member will get a different-looking card')
              : (t.default_card_title || 'Put this card back on the default design') }}
          </h2>
        </div>

        <div class="px-4 py-3 space-y-2 text-sm">
          <template v-if="confirmMode === 'custom'">
            <p>
              {{ t.custom_card_body || 'You changed this card from the' }}
              <span class="font-medium">{{ templateName(activeTemplate) }}</span>
              {{ t.custom_card_body_2 || 'design. Saving keeps this look for member' }}
              <span class="font-mono">{{ values.membership_number }}</span
              >{{ t.custom_card_body_3 || ', and later changes to the design will no longer reach this card.' }}
            </p>
            <div class="rounded-md bg-muted/40 px-3 py-2 text-xs">
              <div class="font-medium mb-1">{{ t.changed || 'Changed' }}</div>
              <ul class="list-disc list-inside space-y-0.5">
                <li v-for="entry in changedFields" :key="entry.key">
                  {{ fieldLabel(entry.key) }} — {{ entry.what.map((w) => (t[`changed_${w}`] || w)).join(', ') }}
                </li>
              </ul>
            </div>
            <p class="text-xs text-muted-foreground">
              {{ t.custom_card_undo || 'To keep it following the design instead, cancel and use “Return to the default”.' }}
            </p>
          </template>

          <template v-else>
            <p>
              {{ t.default_card_body || 'This drops every change made to this card and saves it following the' }}
              <span class="font-medium">{{ templateName(activeTemplate) }}</span>
              {{ t.default_card_body_2 || 'design, so it updates again whenever that design changes.' }}
            </p>
            <div v-if="changedFields.length" class="rounded-md bg-muted/40 px-3 py-2 text-xs">
              <div class="font-medium mb-1">{{ t.will_be_lost || 'Will be lost' }}</div>
              <ul class="list-disc list-inside space-y-0.5">
                <li v-for="entry in changedFields" :key="entry.key">{{ fieldLabel(entry.key) }}</li>
              </ul>
            </div>
          </template>
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
          <button type="button" class="inline-flex items-center rounded-md h-8 px-3 text-xs font-medium bg-muted hover:bg-muted/70 cursor-pointer" @click="confirmMode = null">
            {{ t.cancel || 'Cancel' }}
          </button>
          <button
            v-if="confirmMode === 'custom'"
            type="button"
            class="inline-flex items-center rounded-md h-8 px-3 text-xs font-medium bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 cursor-pointer"
            :disabled="busy"
            @click="confirmSave"
          >
            {{ t.save_anyway || 'Save this look' }}
          </button>
          <button
            v-else
            type="button"
            class="inline-flex items-center rounded-md h-8 px-3 text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
            :disabled="busy"
            @click="confirmReturnToDefault"
          >
            {{ t.return_to_default || 'Return to the default' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, shallowRef, reactive, computed, watch, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { useElementSize } from '@vueuse/core';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import CardPreview from '@/Pages/Admin/MembershipCard/_components/CardPreview.vue';
import { drawBarcode } from '@/Pages/Admin/MembershipCard/_components/code128.js';
import { assetUrl, FALLBACK_LAYOUT, FALLBACK_SAMPLE_DATA } from '@/Pages/Admin/MembershipCard/_components/cardRenderer.js';

const page = usePage();
const t = computed(() => page.props.translations?.admin?.card_generator || {});

const props = defineProps({
  partners: { type: Array, default: () => [] },
  /** Card designs from /admin/card-templates, keyed by status. */
  templates: { type: Object, default: () => ({}) },
  initial: { type: Object, default: () => ({}) },
});

/** Must match CardTemplateLayoutDefaults::EDITOR_WIDTH. */
const EDITOR_WIDTH = 700;

const TEXT_FIELDS = ['slogan', 'membership_number', 'facebook', 'website', 'phone'];
const CODE_FIELDS = ['qrcode', 'barcode'];
const IMAGE_FIELDS = ['logo', 'partner_logo'];
/** Every field, in the order they read down the card. */
const FIELD_ORDER = ['logo', 'slogan', 'membership_number', 'barcode', 'qrcode', 'facebook', 'website', 'phone', 'partner_logo'];

const LABELS = {
  logo: 'Logo',
  slogan: 'Slogan',
  membership_number: 'Membership number',
  facebook: 'Facebook',
  website: 'Website',
  phone: 'Phone',
  qrcode: 'QR code',
  barcode: 'Barcode',
  partner_logo: 'Partner logo',
};

const isTextField = (key) => TEXT_FIELDS.includes(key);
const isImageField = (key) => IMAGE_FIELDS.includes(key);
const fieldLabel = (key) => t.value[`field_${key}`] || LABELS[key] || key;
const round = (n) => Math.round(Number(n) * 10) / 10;

function clone(value) {
  return JSON.parse(JSON.stringify(value ?? null));
}

// --- The design in play: a partner means `with_partner`, none means
// `no_partner`, so the whole page follows /admin/card-templates.
const partnerId = ref(String(props.initial.partner || ''));

const activeTemplate = computed(() => {
  const wanted = partnerId.value ? 'with_partner' : 'no_partner';
  return props.templates[wanted] || props.templates.with_partner || props.templates.no_partner || null;
});

const selectedPartner = computed(
  () => props.partners.find((p) => String(p.id) === String(partnerId.value)) || null,
);

function templateName(tpl) {
  const name = tpl?.name;
  if (!name) return 'Untitled';
  return typeof name === 'string' ? name : (name.en || name.ar || 'Untitled');
}

const hiddenFields = computed(() => activeTemplate.value?.hidden_fields ?? []);
const artworkUrl = computed(() => activeTemplate.value?.card_empty_url || '/images/cards/deilar-card-blank.png');

const busy = ref(false);
const error = ref('');
const savedAt = ref('');
const selected = ref(null);
const stageRef = ref(null);
const aspect = ref(1.586);
const renderedCanvas = shallowRef(null);

const { width: stageWidth } = useElementSize(stageRef);

// --- This card's own layout: a copy of the template's, free to be nudged
// per card. Every field is positioned on its own, exactly as on the template
// page — nothing is grouped.
const layout = reactive(clone(activeTemplate.value?.layout || FALLBACK_LAYOUT));

// --- This card's own values, seeded from the template and the query string.
const values = reactive({});

function seedValues(template) {
  const base = { ...FALLBACK_SAMPLE_DATA, ...(template?.sample_data || {}) };
  for (const key of [...TEXT_FIELDS, ...CODE_FIELDS]) {
    if (values[key] === undefined) values[key] = base[key] ?? '';
  }
}
seedValues(activeTemplate.value);
if (props.initial.policy) values.membership_number = String(props.initial.policy);
if (props.initial.url) values.qrcode = String(props.initial.url);

// What identifies this card rather than the design — kept across a reset, so
// resetting never loses which member the card is for.
const identity = {
  membership_number: values.membership_number || '',
  qrcode: values.qrcode || '',
};

const original = reactive({ membership_number: values.membership_number || '' });
const numberChanged = computed(() => values.membership_number !== original.membership_number);

// Switching partner switches the design, so the layout starts from the new
// template's; typed values are kept, with any new field filled in.
watch(activeTemplate, (tpl) => {
  resetLayout();
  seedValues(tpl);
});

const visibleFields = computed(() => FIELD_ORDER.filter(
  (key) => layout[key] && !hiddenFields.value.includes(key),
));

// --- How far this card has drifted from its design.
//
// The membership number, the QR address and the barcode are the member's own
// data, never a deviation — only the design's own text counts.
const DESIGN_VALUE_FIELDS = ['slogan', 'facebook', 'website', 'phone'];

const templateLayout = computed(() => activeTemplate.value?.layout || FALLBACK_LAYOUT);
const templateValues = computed(() => ({ ...FALLBACK_SAMPLE_DATA, ...(activeTemplate.value?.sample_data || {}) }));

/** Key order is not a difference, so compare on a key-sorted copy. */
function canonical(value) {
  if (Array.isArray(value)) return value.map(canonical);
  if (value && typeof value === 'object') {
    return Object.keys(value).sort().reduce((out, key) => {
      out[key] = canonical(value[key]);
      return out;
    }, {});
  }
  return value;
}
const matches = (a, b) => JSON.stringify(canonical(a)) === JSON.stringify(canonical(b));

const changedFields = computed(() => {
  const changes = new Map();
  const add = (key, what) => {
    if (!changes.has(key)) changes.set(key, { key, what: [] });
    changes.get(key).what.push(what);
  };

  for (const key of visibleFields.value) {
    if (!matches(layout[key], templateLayout.value[key])) add(key, 'layout');
  }
  for (const key of DESIGN_VALUE_FIELDS) {
    if (!visibleFields.value.includes(key)) continue;
    if ((values[key] ?? '') !== (templateValues.value[key] ?? '')) add(key, 'text');
  }

  return [...changes.values()];
});

const isCustomised = computed(() => changedFields.value.length > 0);

const changeSummary = computed(() => changedFields.value.map((c) => fieldLabel(c.key)).join(', '));

const field = computed(() => (selected.value ? layout[selected.value] : null));

/** Positions only — every field back where the template puts it. */
function resetLayout() {
  const next = clone(activeTemplate.value?.layout || FALLBACK_LAYOUT);
  Object.keys(layout).forEach((key) => { delete layout[key]; });
  Object.assign(layout, next);
  selected.value = visibleFields.value.includes(selected.value) ? selected.value : (visibleFields.value[0] ?? null);
}

/** Positions and values both — the card as the template would print it. */
function resetAll() {
  resetLayout();
  const base = { ...FALLBACK_SAMPLE_DATA, ...(activeTemplate.value?.sample_data || {}) };
  for (const key of [...TEXT_FIELDS, ...CODE_FIELDS]) values[key] = base[key] ?? '';
  // The member's own number and QR address are not the template's to restore.
  values.membership_number = identity.membership_number;
  if (identity.qrcode) values.qrcode = identity.qrcode;
  error.value = '';
}

function imageFor(key) {
  if (key === 'partner_logo') {
    return assetUrl(selectedPartner.value?.image || activeTemplate.value?.sample_data?.partner_logo || '');
  }
  return assetUrl(activeTemplate.value?.sample_data?.[key] || FALLBACK_SAMPLE_DATA[key] || '');
}

function imageNote(key) {
  if (key !== 'partner_logo') return t.value.image_from_template || 'Set on the card template.';
  if (selectedPartner.value?.image) return t.value.image_from_partner || "The selected partner's own logo.";
  return t.value.image_placeholder || "The template's placeholder — this partner has no logo of its own.";
}

// --- Live render. The renderer takes a card_templates-shaped object, so the
// card's own layout is handed over as if it were the template's.
const renderValues = computed(() => {
  const out = {};
  for (const key of [...TEXT_FIELDS, ...CODE_FIELDS]) out[key] = values[key];
  return out;
});

const previewMembership = computed(() => ({
  membership_number: values.membership_number || '',
  slug: 'preview',
}));

const workingTemplate = computed(() => ({
  ...(activeTemplate.value || {}),
  layout,
  card_empty_url: artworkUrl.value,
  hidden_fields: hiddenFields.value,
}));

// A full repaint reloads the artwork and re-encodes the codes, so it must never
// run per pointer-move: the stage above follows the drag, the canvas catches up
// once movement settles.
const PREVIEW_DEBOUNCE_MS = 200;
const previewTemplate = shallowRef(clone(workingTemplate.value));
let previewTimer = null;

watch(
  () => JSON.stringify([layout, hiddenFields.value, artworkUrl.value]),
  () => {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(() => { previewTemplate.value = clone(workingTemplate.value); }, PREVIEW_DEBOUNCE_MS);
  },
);

onBeforeUnmount(() => { if (previewTimer) clearTimeout(previewTimer); });

// --- Live QR / barcode for the stage.
const qrDataUri = ref(null);
const barcodeDataUri = ref(null);

watch(
  () => values.qrcode,
  async (value) => {
    qrDataUri.value = value
      ? await QRCode.toDataURL(value, { margin: 0, width: 512, errorCorrectionLevel: 'M' }).catch(() => null)
      : null;
  },
  { immediate: true },
);

watch(
  () => values.barcode || values.membership_number,
  (value) => {
    if (!value) {
      barcodeDataUri.value = null;
      return;
    }
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

// Track the artwork's proportions so the stage matches the card.
function measureArtwork(url) {
  const img = new Image();
  img.onload = () => { if (img.width && img.height) aspect.value = img.width / img.height; };
  img.src = url;
}
measureArtwork(artworkUrl.value);
watch(artworkUrl, measureArtwork);

// --- Stage geometry, identical to the template editor's.
function boxStyle(key) {
  const f = layout[key];
  return {
    left: `${f.x * 100}%`,
    top: `${f.y * 100}%`,
    width: `${f.width * 100}%`,
    height: `${f.height * 100}%`,
  };
}

function textStyle(key) {
  const f = layout[key];
  const scale = (stageWidth.value || EDITOR_WIDTH) / EDITOR_WIDTH;
  const direction = f.direction || 'ltr';
  return {
    fontSize: `${(f.font_size || 14) * scale}px`,
    fontFamily: `${f.font_family || 'Tajawal'}, Arial, sans-serif`,
    fontWeight: 700,
    color: f.color || '#000000',
    direction: direction === 'rtl' ? 'rtl' : 'ltr',
    justifyContent: direction === 'rtl' ? 'flex-end' : (direction === 'center' ? 'center' : 'flex-start'),
    textAlign: direction === 'rtl' ? 'right' : (direction === 'center' ? 'center' : 'left'),
  };
}

function setFraction(key, percent) {
  const value = Number(percent);
  if (!Number.isFinite(value)) return;
  layout[selected.value][key] = value / 100;
}

function startDrag(event, key, mode) {
  if (busy.value) return;
  selected.value = key;

  const stage = stageRef.value;
  if (!stage) return;
  event.preventDefault();
  const rect = stage.getBoundingClientRect();
  const f = layout[key];
  const origin = {
    pointerX: event.clientX,
    pointerY: event.clientY,
    x: f.x, y: f.y, width: f.width, height: f.height,
  };

  const onMove = (e) => {
    const dx = (e.clientX - origin.pointerX) / rect.width;
    const dy = (e.clientY - origin.pointerY) / rect.height;
    const target = layout[key];
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

// --- Saving.
let renderWaiters = [];

function onRendered({ canvas }) {
  renderedCanvas.value = canvas;
  renderWaiters.splice(0).forEach((resolve) => resolve());
}

/**
 * Resolve once the canvas has repainted — the preview is debounced, so an
 * action that saves the rendered image has to wait for it to catch up. The
 * timeout keeps a paint that never comes from hanging the save.
 */
function nextRender(timeout = 3000) {
  return new Promise((resolve) => {
    const timer = setTimeout(() => {
      renderWaiters = renderWaiters.filter((w) => w !== waiter);
      resolve();
    }, timeout);
    const waiter = () => { clearTimeout(timer); resolve(); };
    renderWaiters.push(waiter);
  });
}

async function saveNumber() {
  const lookup = original.membership_number;
  if (!lookup) return;
  busy.value = true;
  error.value = '';
  try {
    await window.axios.patch(`/api/memberships/${encodeURIComponent(lookup)}/card-data`, {
      policy: values.membership_number,
      _lookup: lookup,
    });
    original.membership_number = values.membership_number;
    savedAt.value = new Date().toLocaleTimeString();
  } catch (e) {
    error.value = e?.response?.data?.message || (t.value.save_failed || 'Failed to save.');
  } finally {
    busy.value = false;
  }
}

/** null | 'custom' (pinning this look) | 'default' (giving it up). */
const confirmMode = ref(null);
let pendingMode = 'stay';

/** Ask first when the card would stop following the design. */
function requestSave(mode) {
  if (busy.value || !values.membership_number) return;
  pendingMode = mode;
  if (isCustomised.value) {
    confirmMode.value = 'custom';
    return;
  }
  saveCard(mode);
}

function confirmSave() {
  confirmMode.value = null;
  saveCard(pendingMode);
}

function requestReturnToDefault() {
  if (busy.value || !values.membership_number) return;
  confirmMode.value = 'default';
}

/** Drop this card's own look and save it following the design again. */
async function confirmReturnToDefault() {
  confirmMode.value = null;
  resetAll();
  // Let the canvas catch up so the stored image is the default one.
  await nextRender();
  await saveCard('stay');
}

async function saveCard(mode = 'stay') {
  if (busy.value || !values.membership_number) return;
  busy.value = true;
  error.value = '';
  try {
    // An untouched card is stored with no layout of its own, so it keeps
    // rendering from the template and follows every later change to it. A
    // changed one carries its own layout and values, and is frozen to them.
    const custom = isCustomised.value;

    await window.axios.post(`/api/memberships/${encodeURIComponent(values.membership_number)}/card-layout`, {
      partner_id: partnerId.value ? Number(partnerId.value) : null,
      card_template_id: activeTemplate.value?.id ?? null,
      layout: custom ? clone(layout) : null,
      field_values: custom ? clone(renderValues.value) : null,
      image: renderedCanvas.value ? renderedCanvas.value.toDataURL('image/png') : null,
    });

    savedAt.value = new Date().toLocaleTimeString();
    if (mode === 'return') goBack();
  } catch (e) {
    error.value = e?.response?.status === 404
      ? (t.value.no_membership || 'No membership with that number.')
      : (e?.response?.data?.message || e?.message || (t.value.save_card_failed || 'Could not save the card.'));
  } finally {
    busy.value = false;
  }
}

function downloadCard() {
  const canvas = renderedCanvas.value;
  if (!canvas) return;
  const name = String(values.membership_number || 'card').replace(/[^A-Za-z0-9_-]+/g, '_');
  const a = document.createElement('a');
  a.download = `${name}.png`;
  a.href = canvas.toDataURL('image/png');
  a.click();
}

function goBack() {
  if (window.history.length > 1) window.history.back();
}

selected.value = visibleFields.value[0] ?? null;
</script>
