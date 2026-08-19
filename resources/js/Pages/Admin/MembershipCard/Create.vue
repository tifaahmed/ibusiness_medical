<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-lg sm:text-xl font-semibold flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
          Create Membership Card Patches Batch
        </h1>
        <Link :href="route('admin.membership-card-patches.list')" class="text-sm text-muted-foreground hover:underline">
          ← Back to batches
        </Link>
      </div>

      <!-- Form -->
      <div class="rounded-xl border border-border bg-card text-card-foreground p-4 sm:p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 sm:gap-4">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium mb-1">Batch name (optional)</label>
            <input
              v-model="form.batch_name"
              type="text"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm placeholder:text-white"
              placeholder="e.g. May 2026 — first run"
              :disabled="busy"
            />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">
              Display prefix
              <span class="text-muted-foreground font-normal">(printed only, not stored)</span>
            </label>
            <input
              v-model="form.display_prefix"
              type="text"
              maxlength="32"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm font-mono placeholder:text-white"
              placeholder="e.g. ASH-"
              :disabled="busy"
            />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">
              Number prefix
              <span class="text-muted-foreground font-normal">(stored with number)</span>
            </label>
            <input
              v-model="form.prefix"
              type="text"
              maxlength="32"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm font-mono placeholder:text-white"
              placeholder="e.g. MEM-"
              :disabled="busy"
            />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">
              Number grouping
              <span class="text-muted-foreground font-normal">(printed only, not stored)</span>
            </label>
            <input
              v-model="form.display_groups"
              type="text"
              maxlength="32"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm font-mono placeholder:text-white"
              placeholder="e.g. 3-3-3"
              :disabled="busy"
            />
            <p class="mt-1 text-[11px] text-muted-foreground">
              Group lengths, written the way the result reads — type
              <button type="button" class="font-mono underline hover:no-underline cursor-pointer" @click="form.display_groups = '3-3-3'">3-3-3</button>
              or
              <button type="button" class="font-mono underline hover:no-underline cursor-pointer" @click="form.display_groups = '1-3-3-2'">1-3-3-2</button>.
              Leave empty for no dashes.
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Quantity *</label>
            <input
              v-model.number="form.quantity"
              type="number"
              min="1"
              max="1000"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              :disabled="busy"
            />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Start number *</label>
            <input
              v-model.number="form.start_number"
              type="number"
              min="1"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              :disabled="busy"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium mb-1">
              Partner
              <span v-if="partnerLocked" class="text-destructive">*</span>
            </label>
            <input
              v-if="partnerLocked"
              type="text"
              :value="lockedPartnerLabel"
              disabled
              class="w-full rounded-md border border-border bg-muted/40 px-3 py-2 text-sm cursor-not-allowed opacity-75"
            />
            <select
              v-else
              v-model="form.partner_id"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              :disabled="busy"
            >
              <option :value="null">— no partner —</option>
              <option v-for="p in partners" :key="p.id" :value="p.id">{{ p.title }}</option>
            </select>
            <p v-if="partnerLocked" class="mt-1 text-[11px] text-muted-foreground">
              Locked to your assigned partner.
            </p>
            <!-- The partner choice picks the design, so say which one. -->
            <p class="mt-1 text-[11px] text-muted-foreground">
              <template v-if="activeTemplate">
                Design:
                <Link :href="route('admin.card-templates.edit', activeTemplate.id)" class="underline hover:no-underline">
                  {{ templateName(activeTemplate) }}
                </Link>
                — {{ activeTemplate.status === 'no_partner' ? 'no partner logo' : 'with a partner logo' }}.
              </template>
              <template v-else>
                No card template exists yet — cards fall back to the bundled artwork.
              </template>
            </p>
          </div>

        </div>

        <div class="text-xs text-muted-foreground space-y-0.5">
          <div>
            Printed on the card:
            <span class="font-mono font-semibold">{{ firstCode }}</span>
            →
            <span class="font-mono font-semibold">{{ lastCode }}</span>
            ({{ form.quantity }} cards · {{ pageCount }} PDF page{{ pageCount === 1 ? '' : 's' }} + {{ form.quantity }} single-card PDFs zipped)
          </div>
          <!-- What actually lands in the database, spelled out next to the
               decorated form so the two are never confused. -->
          <div>
            Stored in the database (and encoded in the barcode):
            <span class="font-mono font-semibold">{{ firstStored }}</span>
            →
            <span class="font-mono font-semibold">{{ lastStored }}</span>
          </div>
        </div>

        <div v-if="serverError" class="rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm">
          {{ serverError }}
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-golden inline-flex items-center justify-center rounded-md h-9 px-3 text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 cursor-pointer"
            @click="togglePreview"
            :disabled="!canPreview || busy"
          >
            {{ previewOpen ? 'Hide preview' : 'Preview cards' }}
          </button>

          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md h-9 px-3 text-sm font-medium bg-slate-700 text-white hover:bg-slate-800 disabled:opacity-50 cursor-pointer"
            @click="toggleLayoutPanel"
            :disabled="busy"
          >
            {{ layoutPanelOpen ? 'Hide layout controls' : 'Edit layout (size & position)' }}
          </button>

          <button
            type="button"
            class="inline-flex items-center justify-center rounded-md h-9 px-3 text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer"
            @click="submit"
            :disabled="!canPreview || busy"
          >
            <span v-if="!busy">Create batch &amp; generate PDF</span>
            <span v-else>{{ progressLabel }}</span>
          </button>
        </div>

        <!-- Layout controls (hidden until toggled) -->
        <div
          v-if="layoutPanelOpen"
          class="rounded-md border border-border bg-muted/30 p-3 sm:p-4 space-y-3"
        >
          <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
              <span class="text-xs font-medium">Apply to:</span>
              <label class="text-xs flex items-center gap-1 cursor-pointer">
                <input type="radio" value="all" v-model="layoutMode" :disabled="busy" />
                All cards
              </label>
              <label class="text-xs flex items-center gap-1 cursor-pointer">
                <input type="radio" value="single" v-model="layoutMode" :disabled="busy" />
                Single card
              </label>
            </div>
            <div v-if="layoutMode === 'single'" class="flex items-center gap-2">
              <label class="text-xs">Card #</label>
              <input
                v-model.number="layoutSingleIndex"
                type="number"
                min="1"
                :max="form.quantity || 1"
                class="w-20 rounded-md border border-border bg-background px-2 py-1 text-xs"
                :disabled="busy"
              />
              <span class="text-xs text-muted-foreground">of {{ form.quantity || 0 }}</span>
            </div>
            <button
              type="button"
              class="ml-auto text-xs text-red-600 hover:underline cursor-pointer"
              @click="resetLayout"
              :disabled="busy"
            >
              Reset to the design
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <fieldset
              v-for="el in layoutElements"
              :key="el.key"
              class="rounded-md border border-border bg-background p-2 space-y-1"
            >
              <legend class="text-xs font-semibold px-1">{{ el.label }}</legend>
              <div class="grid grid-cols-3 gap-1">
                <label class="text-[10px] text-muted-foreground">X
                  <input v-model.number="layoutOverrides[el.key].x" type="number" class="w-full rounded border border-border bg-background px-1 py-0.5 text-xs" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground">Y
                  <input v-model.number="layoutOverrides[el.key].y" type="number" class="w-full rounded border border-border bg-background px-1 py-0.5 text-xs" :disabled="busy" />
                </label>
                <label class="text-[10px] text-muted-foreground">Scale
                  <input v-model.number="layoutOverrides[el.key].scale" type="number" step="0.05" class="w-full rounded border border-border bg-background px-1 py-0.5 text-xs" :disabled="busy" />
                </label>
              </div>
              <p class="text-[10px] text-muted-foreground leading-tight">{{ el.hint }}</p>
            </fieldset>
          </div>

          <fieldset class="rounded-md border border-border bg-background p-2 space-y-1">
            <legend class="text-xs font-semibold px-1">Contact lines (printed next to the card icons)</legend>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <label class="text-[10px] text-muted-foreground">Facebook
                <input v-model="contactText.facebook" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <label class="text-[10px] text-muted-foreground">Website
                <input v-model="contactText.website" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
              <label class="text-[10px] text-muted-foreground">Phone
                <input v-model="contactText.phone" type="text" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :disabled="busy" />
              </label>
            </div>
          </fieldset>

          <p class="text-[11px] text-muted-foreground">
            Coordinates are pixels on the {{ CARD_W }} × {{ CARD_H }} blank card artwork. Preview updates live.
          </p>
          <!-- Whether this batch prints the design or its own thing, said
               before the cards are cut rather than after. -->
          <p
            class="text-[11px]"
            :class="layoutChanged || contactChanged ? 'text-amber-700' : 'text-muted-foreground'"
          >
            <template v-if="layoutChanged || contactChanged">
              These cards will print differently from
              <span class="font-medium">{{ templateName(activeTemplate) }}</span
              >. Reset to go back to the design.
            </template>
            <template v-else>
              These fields start from
              <span class="font-medium">{{ templateName(activeTemplate) }}</span
              >, so the batch prints exactly what that design says. Nothing is overridden until you change one.
            </template>
          </p>
        </div>
      </div>

      <!-- Preview -->
      <div v-if="previewOpen" class="rounded-xl border border-border bg-card text-card-foreground p-4 sm:p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold">Preview ({{ previewItems.length }} cards)</h2>
          <div class="text-xs text-muted-foreground">Each card: 9cm × 5.6cm — 10 per A4 page</div>
        </div>

        <div class="overflow-x-auto rounded-md border border-border max-h-72 overflow-y-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 sticky top-0">
              <tr>
                <th class="px-3 py-2 text-left font-medium">#</th>
                <th class="px-3 py-2 text-left font-medium">Printed on card</th>
                <th class="px-3 py-2 text-left font-medium">Stored number</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in previewItems" :key="item.membership_number" class="border-t border-border">
                <td class="px-3 py-1.5 text-muted-foreground">{{ idx + 1 }}</td>
                <td class="px-3 py-1.5 font-mono">{{ item.display_number }}</td>
                <td class="px-3 py-1.5 font-mono text-muted-foreground">{{ item.membership_number }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>
          <div class="text-xs text-muted-foreground mb-2">Visual preview (first {{ Math.min(previewItems.length, 10) }} of {{ previewItems.length }}):</div>
          <div class="flex flex-wrap gap-3">
            <div
              v-for="(item, idx) in previewItems.slice(0, 10)"
              :key="item.display_number + ':' + (form.partner_id || '') + ':' + layoutKey(idx)"
              class="cursor-pointer group"
              @click="openCardModal(item, idx)"
            >
              <div class="relative">
                <CardPreview
                  :membership="{ membership_number: item.display_number, stored_number: item.membership_number, slug: item.slug_preview }"
                  :template="activeTemplate"
                  :partner="selectedPartner"
                  :overrides="overridesForIndex(idx)"
                  :contact="contactForRender"
                />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors rounded-[0.3cm] flex items-center justify-center opacity-0 group-hover:opacity-100">
                  <span class="bg-black/70 text-white text-[11px] px-2 py-1 rounded font-medium">Click to open</span>
                </div>
              </div>
              <div class="mt-1.5 px-1 text-[10px] space-y-0.5">
                <div class="flex items-center gap-1">
                  <span class="text-muted-foreground shrink-0">Barcode:</span>
                  <span class="font-mono break-all" dir="ltr">{{ cardBarcodeValue({ membership_number: item.display_number, stored_number: item.membership_number }) }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <span class="text-muted-foreground shrink-0">QR:</span>
                  <span class="font-mono break-all text-blue-600 truncate" dir="ltr">{{ cardQrValue({ slug: item.slug_preview }) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card popup modal -->
    <Modal :show="cardModal !== null" max-width="2xl" @close="cardModal = null">
      <div class="p-4 flex flex-col items-center gap-4">
        <div class="flex items-center justify-between w-full">
          <h2 class="text-sm font-semibold">
            Card <span class="font-mono">{{ cardModal?.display_number }}</span>
          </h2>
          <button
            type="button"
            class="text-muted-foreground hover:text-foreground text-lg leading-none cursor-pointer"
            @click="cardModal = null"
          >✕</button>
        </div>

        <CardPreview
          :membership="modalMembership()"
          :template="activeTemplate"
          :partner="selectedPartner"
          :overrides="overridesForIndex(cardModal?.idx ?? 0)"
          :contact="contactForRender"
          display-width="100%"
        />

        <div class="w-full">
          <CardCodes :membership="modalMembership()" />
        </div>

        <div class="flex justify-end w-full">
          <button
            type="button"
            class="rounded-md h-9 px-3 text-sm border border-border hover:bg-muted cursor-pointer"
            @click="cardModal = null"
          >
            Close
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import CardPreview from './_components/CardPreview.vue';
import CardCodes from './_components/CardCodes.vue';
import Modal from '@/Components/Modal.vue';
import { buildBatchAndZip } from './_components/PdfBuilder.js';
import { cardPrintedNumber, overridePanelDefaults, templateContactText, cardQrValue, cardBarcodeValue, CARD_W, CARD_H } from './_components/cardRenderer.js';

const props = defineProps({
  suggested_start: { type: Number, default: 1000 },
  partners: { type: Array, default: () => [] },
  partnerLocked: { type: Boolean, default: false },
  lockedPartnerId: { type: Number, default: null },
  /** Card designs from /admin/card-templates, keyed by status. */
  cardTemplates: { type: Object, default: () => ({}) },
});

const form = ref({
  batch_name: '',
  display_prefix: '',
  display_groups: '',
  prefix: '',
  quantity: 10,
  start_number: props.suggested_start,
  partner_id: props.partnerLocked ? props.lockedPartnerId : null,
});

const lockedPartnerLabel = computed(() => {
  if (!props.partnerLocked) return '';
  const id = props.lockedPartnerId;
  const match = props.partners.find((p) => p.id === id);
  return match?.title ?? '';
});

const busy = ref(false);
const serverError = ref('');
const progressLabel = ref('Working…');
const previewOpen = ref(false);

const selectedPartner = computed(() => {
  if (!form.value.partner_id) return null;
  return props.partners.find((p) => p.id === form.value.partner_id) || null;
});

// The design the batch is cut from: a batch with a partner uses the
// `with_partner` template, one without uses `no_partner`. The chosen template's
// id is what gets stored on the batch, so a card's own page renders it back
// with the same design.
const activeTemplate = computed(() => {
  const wanted = form.value.partner_id ? 'with_partner' : 'no_partner';
  return props.cardTemplates[wanted]
    || props.cardTemplates.with_partner
    || props.cardTemplates.no_partner
    || null;
});

/** `name` is a translatable, so it arrives as {ar, en} unless it was a string. */
function templateName(tpl) {
  const name = tpl?.name;
  if (!name) return 'Untitled';
  return typeof name === 'string' ? name : (name.en || name.ar || 'Untitled');
}

/**
 * How a stored number reads on the card: grouped for the eye, behind the
 * display prefix. Neither mark is stored — the database keeps the number whole.
 */
function printedNumber(stored) {
  return cardPrintedNumber(stored, {
    displayPrefix: form.value.display_prefix,
    groups: form.value.display_groups,
  });
}

const firstStored = computed(() => `${form.value.prefix || ''}${form.value.start_number}`);
const lastStored = computed(() => {
  const q = Number(form.value.quantity) || 0;
  const s = Number(form.value.start_number) || 0;
  return `${form.value.prefix || ''}${s + Math.max(0, q - 1)}`;
});

const firstCode = computed(() => printedNumber(firstStored.value));
const lastCode = computed(() => printedNumber(lastStored.value));

const pageCount = computed(() => {
  const q = Number(form.value.quantity) || 0;
  return Math.max(0, Math.ceil(q / 10));
});

const canPreview = computed(() => {
  const q = Number(form.value.quantity);
  const s = Number(form.value.start_number);
  return Number.isFinite(q) && q >= 1 && q <= 1000 && Number.isFinite(s) && s >= 1;
});

const previewItems = computed(() => {
  if (!canPreview.value) return [];
  const items = [];
  const prefix = form.value.prefix || '';
  for (let i = 0; i < form.value.quantity; i++) {
    const stored = `${prefix}${form.value.start_number + i}`;
    items.push({
      membership_number: stored,
      display_number: printedNumber(stored),
      slug_preview: stored.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
    });
  }
  return items;
});

function togglePreview() {
  previewOpen.value = !previewOpen.value;
}

function triggerDownload(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// --- Layout overrides (size/position of QR, policy text, partner logo) ---
const layoutPanelOpen = ref(false);
const layoutMode = ref('all'); // 'all' | 'single'
const layoutSingleIndex = ref(1); // 1-based card index within batch

const layoutElements = [
  { key: 'qr', label: 'QR code', hint: 'Top-left of the white QR frame.' },
  { key: 'barcode', label: 'Barcode', hint: 'Top-left of the bars; the number prints underneath.' },
  { key: 'partner', label: 'Partner logo', hint: 'Top-left of the logo box, above "Health Care".' },
  { key: 'contact', label: 'Contact rows', hint: 'Text left edge + first baseline; rows are pitched to the printed icons.' },
];

// The panel starts from the selected design, so an untouched batch is the
// design itself — the same card a member gets anywhere else in the admin.
const layoutDefaults = computed(() => overridePanelDefaults(activeTemplate.value));
const contactDefaults = computed(() => templateContactText(activeTemplate.value));

const layoutOverrides = ref(structuredClone(layoutDefaults.value));
const contactText = ref({ ...contactDefaults.value });

function resetLayout() {
  layoutOverrides.value = structuredClone(layoutDefaults.value);
  contactText.value = { ...contactDefaults.value };
}

// Picking a partner switches the design, so the panel starts over from the new
// one rather than carrying the old design's numbers onto it.
watch(activeTemplate, resetLayout);

const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

/**
 * Nothing touched means nothing to override: the batch is rendered straight
 * from the design, with no rounded pixel positions or copied contact rows
 * standing between it and the template.
 */
const layoutChanged = computed(() => !same(layoutOverrides.value, layoutDefaults.value));
const contactChanged = computed(() => !same(contactText.value, contactDefaults.value));
const contactForRender = computed(() => (contactChanged.value ? contactText.value : null));

function toggleLayoutPanel() {
  layoutPanelOpen.value = !layoutPanelOpen.value;
}

function overridesForIndex(idx0) {
  if (!layoutChanged.value) return null;
  if (layoutMode.value === 'all') return layoutOverrides.value;
  if (layoutMode.value === 'single' && idx0 === (Number(layoutSingleIndex.value) - 1)) {
    return layoutOverrides.value;
  }
  return null;
}

function layoutKey(idx0) {
  const o = overridesForIndex(idx0);
  return o ? JSON.stringify(o) : 'def';
}

// --- Card popup modal ---
const cardModal = ref(null);

function openCardModal(item, idx) {
  cardModal.value = { ...item, idx };
}

function modalMembership() {
  if (!cardModal.value) return {};
  return {
    membership_number: cardModal.value.display_number,
    stored_number: cardModal.value.membership_number,
    slug: cardModal.value.slug_preview,
  };
}

async function submit() {
  if (!canPreview.value || busy.value) return;
  serverError.value = '';
  busy.value = true;

  try {
    progressLabel.value = 'Creating memberships…';
    const { data } = await axios.post(route('admin.membership-card-patches.store'), {
      batch_name: form.value.batch_name || null,
      prefix: form.value.prefix || null,
      display_prefix: form.value.display_prefix || null,
      display_groups: form.value.display_groups || null,
      quantity: form.value.quantity,
      start_number: form.value.start_number,
      partner_id: form.value.partner_id,
      card_template_id: activeTemplate.value?.id ?? null,
      // Stored only when this batch actually departs from its design; a null
      // keeps the saved batch rendering from the template itself.
      layout_overrides: layoutChanged.value || contactChanged.value
        ? {
          ...(layoutChanged.value ? layoutOverrides.value : {}),
          ...(contactChanged.value ? { contact_text: contactText.value } : {}),
        }
        : null,
    });

    // The prefix and the dashes are printed, never stored — the bars keep the
    // stored number so a scan still finds the membership.
    const printedMemberships = data.memberships.map((m) => ({
      ...m,
      membership_number: printedNumber(m.membership_number),
      stored_number: m.membership_number,
    }));

    progressLabel.value = 'Rendering (0%)…';
    const { batchPdf, zip } = await buildBatchAndZip(
      printedMemberships,
      selectedPartner.value,
      activeTemplate.value,
      (p) => {
        progressLabel.value = `Rendering (${Math.round(p * 100)}%)…`;
      },
      overridesForIndex,
      contactForRender.value,
    );

    triggerDownload(zip, `batch-${data.card.id}.zip`);

    progressLabel.value = 'Uploading PDF…';
    const fd = new FormData();
    fd.append('pdf', new File([batchPdf], `batch-${data.card.id}.pdf`, { type: 'application/pdf' }));
    await axios.post(data.upload_url, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    router.visit(data.show_url);
  } catch (e) {
    if (e?.response?.status === 422) {
      const errs = e.response.data?.errors || {};
      serverError.value = Object.values(errs).flat().join(' ') || 'Validation failed.';
    } else {
      serverError.value = e?.response?.data?.message || e?.message || 'Something went wrong.';
    }
    busy.value = false;
    progressLabel.value = 'Working…';
  }
}
</script>
