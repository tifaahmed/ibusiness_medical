<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <!-- Header -->
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <h1 class="text-lg sm:text-xl font-semibold">
            {{ card.batch_name || `Batch #${card.id}` }}
          </h1>
          <div class="text-xs text-muted-foreground mt-1">
            {{ card.quantity }} cards · numbers
            <span class="font-mono">{{ card.start_number }}</span>
            –
            <span class="font-mono">{{ card.end_number }}</span>
            · created by {{ card.creator?.name || '—' }} on {{ card.created_at }}
          </div>
          <div v-if="card.prefix || card.display_prefix" class="text-xs text-muted-foreground mt-0.5">
            <template v-if="card.prefix">
              stored prefix: <span class="font-mono font-medium">{{ card.prefix }}</span>
            </template>
            <template v-if="card.prefix && card.display_prefix"> · </template>
            <template v-if="card.display_prefix">
              display prefix (printed only): <span class="font-mono font-medium">{{ card.display_prefix }}</span>
            </template>
          </div>
        </div>
        <div class="flex gap-2 flex-wrap">
          <a
            v-if="card.has_pdf"
            :href="card.pdf_url"
            :download="batchPdfDownloadName"
            class="inline-flex items-center gap-1.5 rounded-md h-9 px-3 text-sm bg-blue-600 text-white hover:bg-blue-700"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
            Download PDF
          </a>
          <Link :href="route('admin.membership-card-patches.list')" class="inline-flex items-center gap-1.5 rounded-md h-9 px-3 text-sm border border-border hover:bg-muted">
            ← Back
          </Link>
          <button
            type="button"
            @click="confirmDelete"
            class="inline-flex items-center gap-1.5 rounded-md h-9 px-3 text-sm bg-red-600 text-white hover:bg-red-700 cursor-pointer"
          >
            Delete batch
          </button>
        </div>
      </div>

      <!-- Memberships table -->
      <div class="rounded-xl border border-border bg-card overflow-hidden">
        <div class="px-3 py-2 border-b border-border flex flex-col sm:flex-row sm:items-center gap-2">
          <div class="text-sm font-medium">
            Memberships in this batch
            ({{ filteredMemberships.length }}<template v-if="filteredMemberships.length !== card.memberships.length"> of {{ card.memberships.length }}</template>)
          </div>
          <div class="ml-auto flex items-center gap-2 w-full sm:w-auto">
            <input
              v-model="numberFilter"
              type="text"
              placeholder="Filter by membership number…"
              class="w-full sm:w-72 rounded-md border border-border bg-background px-3 py-1.5 text-xs font-mono"
            />
            <button
              v-if="numberFilter"
              type="button"
              class="text-xs rounded-md bg-red-600 text-white border border-red-700 hover:bg-red-700 px-2 py-1 transition-colors cursor-pointer"
              @click="numberFilter = ''"
            >
              Clear
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted/40">
              <tr>
                <th class="px-3 py-2 text-left font-medium">#</th>
                <th class="px-3 py-2 text-left font-medium">Code</th>
                <th class="px-3 py-2 text-left font-medium">Member</th>
                <th class="px-3 py-2 text-center font-medium">Family</th>
                <th class="px-3 py-2 text-center font-medium">Completed</th>
                <th class="px-3 py-2 text-center font-medium">Active</th>
                <th class="px-3 py-2 text-center font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filteredMemberships.length">
                <td colspan="7" class="px-3 py-6 text-center text-sm text-muted-foreground italic">
                  No memberships match this filter.
                </td>
              </tr>
              <tr v-for="(m, idx) in filteredMemberships" :key="m.id" class="border-t border-border">
                <td class="px-3 py-1.5 text-muted-foreground">{{ idx + 1 }}</td>
                <td class="px-3 py-1.5 font-mono">{{ m.membership_number }}</td>
                <td class="px-3 py-1.5">{{ m.user?.name || '—' }}</td>
                <td class="px-3 py-1.5 text-center">{{ m.family_members?.length || 0 }}</td>
                <td class="px-3 py-1.5 text-center">
                  <span :class="[
                    'inline-block rounded px-1.5 py-0.5 text-xs',
                    m.completed_at ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white',
                  ]">{{ m.completed_at ? 'Yes' : 'No' }}</span>
                </td>
                <td class="px-3 py-1.5 text-center">
                  <span :class="[
                    'inline-block rounded px-1.5 py-0.5 text-xs',
                    m.is_active ? 'bg-emerald-500 text-white' : 'bg-gray-500 text-white',
                  ]">{{ m.is_active ? 'Yes' : 'No' }}</span>
                </td>
                <td class="px-3 py-1.5 text-center">
                  <div class="inline-flex items-center gap-2 flex-wrap justify-center">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                      @click="openDetail(m)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                      View
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded-md bg-purple-600 text-white border border-purple-700 hover:bg-purple-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer"
                      @click="openCard(m)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                      Card
                    </button>
                    <Link
                      v-if="m.user?.slug"
                      :href="route('admin.user.membership.edit', m.user.slug)"
                      class="inline-flex items-center gap-1 rounded-md bg-emerald-600 text-white border border-emerald-700 hover:bg-emerald-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                      Edit
                    </Link>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 rounded-md bg-slate-400 text-white border border-slate-500 px-2.5 py-1 text-xs font-medium italic cursor-not-allowed opacity-70"
                      title="No user attached to this membership"
                    >
                      Edit
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Card visuals -->
      <div class="rounded-xl border border-border bg-card p-3 sm:p-4">
        <div class="text-sm font-medium mb-3 flex items-center gap-2">
          <span>Cards preview</span>
          <span v-if="partner" class="text-xs text-muted-foreground">
            · partner: <span class="font-medium">{{ partner.title }}</span>
          </span>
        </div>
        <div v-if="!filteredMemberships.length" class="text-sm text-muted-foreground italic">
          No memberships match this filter.
        </div>
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 justify-items-center">
          <div v-for="m in filteredMemberships" :key="m.id" class="flex flex-col items-center gap-1.5">
            <button
              type="button"
              class="cursor-zoom-in"
              title="Click to see the card full size"
              @click="zoomed = m"
            >
              <CardPreview
                :membership="printedMembership(m)"
                :partner="effectivePartner"
                :template="cardTemplate"
                :overrides="card.layout_overrides"
                :contact="card.layout_overrides?.contact_text"
              />
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-2.5 py-1 text-xs font-medium shadow-sm transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-wait"
              :disabled="downloadingId === m.id"
              @click="downloadCardPdf(m)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
              {{ downloadingId === m.id ? 'Preparing…' : 'Download PDF' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail modal -->
    <div
      v-if="detail"
      class="fixed inset-0 z-50 flex items-start justify-center bg-black/70 p-4 overflow-y-auto"
      @click.self="detail = null"
    >
      <div class="relative bg-popover text-popover-foreground rounded-xl border border-border w-full max-w-3xl my-8 shadow-2xl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-border">
          <h2 class="text-sm font-semibold">
            Membership <span class="font-mono">{{ detail.membership_number }}</span>
          </h2>
          <button type="button" class="text-muted-foreground hover:text-foreground" @click="detail = null">✕</button>
        </div>

        <div class="p-4 space-y-4 text-sm">
          <!-- Member -->
          <section>
            <h3 class="text-xs uppercase font-semibold text-muted-foreground mb-1">Member</h3>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
              <div><span class="text-muted-foreground">Name:</span> {{ detail.user?.name || '—' }}</div>
              <div><span class="text-muted-foreground">Email:</span> {{ detail.user?.email || '—' }}</div>
              <div><span class="text-muted-foreground">Phone:</span> {{ detail.user?.phone || '—' }}</div>
              <div><span class="text-muted-foreground">Job title:</span> {{ jobTitleText(detail.job_title) }}</div>
              <div><span class="text-muted-foreground">Slug:</span> <span class="font-mono">{{ detail.slug }}</span></div>
              <div>
                <span class="text-muted-foreground">Public URL:</span>
                <a :href="detail.public_url" target="_blank" class="text-blue-600 hover:underline ml-1">open</a>
              </div>
            </div>
          </section>

          <!-- Status -->
          <section>
            <h3 class="text-xs uppercase font-semibold text-muted-foreground mb-1">Status</h3>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
              <div><span class="text-muted-foreground">Completed at:</span> {{ detail.completed_at || '—' }}</div>
              <div><span class="text-muted-foreground">Active:</span> {{ detail.is_active ? 'Yes' : 'No' }}</div>
              <div><span class="text-muted-foreground">Visible:</span> {{ detail.is_visible ? 'Yes' : 'No' }}</div>
              <div><span class="text-muted-foreground">Deleted:</span> {{ detail.is_deleted ? 'Yes' : 'No' }}</div>
              <div><span class="text-muted-foreground">Registered:</span> {{ detail.registration_date || '—' }}</div>
              <div><span class="text-muted-foreground">Expires:</span> {{ detail.expiration_date || '—' }}</div>
              <div><span class="text-muted-foreground">Created:</span> {{ detail.created_at || '—' }}</div>
            </div>
          </section>

          <!-- Location / Partner / Company -->
          <section>
            <h3 class="text-xs uppercase font-semibold text-muted-foreground mb-1">Affiliations</h3>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
              <div><span class="text-muted-foreground">Partner:</span> {{ detail.partner?.title || '—' }}</div>
              <div><span class="text-muted-foreground">Company:</span> {{ detail.company?.name || '—' }}</div>
              <div><span class="text-muted-foreground">Governorate:</span> {{ detail.governorate?.name || '—' }}</div>
              <div><span class="text-muted-foreground">City:</span> {{ detail.city?.name || '—' }}</div>
            </div>
          </section>

          <!-- What the card's codes encode -->
          <section>
            <h3 class="text-xs uppercase font-semibold text-muted-foreground mb-1">Card codes</h3>
            <CardCodes :membership="printedMembership(detail)" />
          </section>

          <!-- Family -->
          <section>
            <h3 class="text-xs uppercase font-semibold text-muted-foreground mb-1">
              Family members ({{ detail.family_members?.length || 0 }})
            </h3>
            <div v-if="!detail.family_members?.length" class="text-muted-foreground italic">
              No family members.
            </div>
            <div v-else class="overflow-x-auto rounded-md border border-border">
              <table class="w-full text-xs">
                <thead class="bg-muted/40">
                  <tr>
                    <th class="px-2 py-1 text-left font-medium">Name</th>
                    <th class="px-2 py-1 text-left font-medium">Relationship</th>
                    <th class="px-2 py-1 text-left font-medium">Date of birth</th>
                    <th class="px-2 py-1 text-left font-medium">Phone</th>
                    <th class="px-2 py-1 text-left font-medium">Email</th>
                    <th class="px-2 py-1 text-center font-medium">Active</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="f in detail.family_members" :key="f.id" class="border-t border-border">
                    <td class="px-2 py-1">{{ f.name }}</td>
                    <td class="px-2 py-1 capitalize">{{ f.relationship || '—' }}</td>
                    <td class="px-2 py-1">{{ f.date_of_birth || '—' }}</td>
                    <td class="px-2 py-1">{{ f.phone || '—' }}</td>
                    <td class="px-2 py-1">{{ f.email || '—' }}</td>
                    <td class="px-2 py-1 text-center">{{ f.is_active ? 'Yes' : 'No' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <div class="px-4 py-3 border-t border-border flex justify-end">
          <button
            type="button"
            class="rounded-md h-9 px-3 text-sm border border-border hover:bg-muted"
            @click="detail = null"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Card modal -->
    <div
      v-if="cardModal"
      class="fixed inset-0 z-50 flex items-start justify-center bg-black/70 p-4 overflow-y-auto"
      @click.self="cardModal = null"
    >
      <div class="relative bg-popover text-popover-foreground rounded-xl border border-border w-full max-w-2xl my-8 shadow-2xl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-border">
          <h2 class="text-sm font-semibold">
            Card <span class="font-mono">{{ cardModal.membership_number }}</span>
          </h2>
          <button type="button" class="text-muted-foreground hover:text-foreground" @click="cardModal = null">✕</button>
        </div>

        <div class="p-4 flex flex-col items-center gap-4">
          <button
            type="button"
            class="cursor-zoom-in"
            title="Click to see the card full size"
            @click="zoomed = cardModal"
          >
            <CardPreview
              :membership="printedMembership(cardModal)"
              :partner="effectivePartner"
              :template="cardTemplate"
              :overrides="card.layout_overrides"
              :contact="card.layout_overrides?.contact_text"
            />
          </button>
          <p class="-mt-2 text-[11px] text-muted-foreground">Click the card to see it full size.</p>

          <div class="w-full">
            <CardCodes :membership="printedMembership(cardModal)" />
          </div>

          <div class="flex flex-wrap items-center justify-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 text-white border border-blue-700 hover:bg-blue-700 px-3 py-1.5 text-xs font-medium shadow-sm transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-wait"
              :disabled="downloadingId === cardModal.id"
              @click="downloadCardPdf(cardModal)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
              {{ downloadingId === cardModal.id ? 'Preparing…' : 'Download PDF' }}
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 text-white border border-emerald-700 hover:bg-emerald-700 px-3 py-1.5 text-xs font-medium shadow-sm transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-wait"
              :disabled="downloadingImageId === cardModal.id"
              @click="downloadCardImage(cardModal)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.586-4.586a2 2 0 0 0-2.828 0L3 21"/></svg>
              {{ downloadingImageId === cardModal.id ? 'Preparing…' : 'Download image (PNG)' }}
            </button>
          </div>
        </div>

        <div class="px-4 py-3 border-t border-border flex justify-end">
          <button
            type="button"
            class="rounded-md h-9 px-3 text-sm border border-border hover:bg-muted"
            @click="cardModal = null"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Full-size card. Sits above the card modal it can be opened from, so
         closing it drops back to that modal rather than out of both. -->
    <div
      v-if="zoomed"
      class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-3 bg-black/85 p-4"
      @click.self="zoomed = null"
    >
      <!-- Sized so the whole card fits the viewport: the card's own aspect
           ratio turns a height limit into a width one. -->
      <div class="flex flex-col gap-3" :style="{ width: 'min(1400px, 92vw, 120vh)' }" @click.self="zoomed = null">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-white">
            Card <span class="font-mono">{{ printedMembership(zoomed).membership_number }}</span>
          </h2>
          <button
            type="button"
            class="rounded-md border border-white/30 px-2 py-1 text-xs text-white hover:bg-white/10 cursor-pointer"
            @click="zoomed = null"
          >
            Close ✕
          </button>
        </div>

        <CardPreview
          :membership="printedMembership(zoomed)"
          :partner="effectivePartner"
          :template="cardTemplate"
          :overrides="card.layout_overrides"
          :contact="card.layout_overrides?.contact_text"
          display-width="100%"
        />

        <div class="bg-popover text-popover-foreground rounded-md">
          <CardCodes :membership="printedMembership(zoomed)" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { jsPDF } from 'jspdf';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import CardPreview from './_components/CardPreview.vue';
import CardCodes from './_components/CardCodes.vue';
import { renderCardCanvas } from './_components/cardRenderer.js';

const props = defineProps({
  card: { type: Object, required: true },
  partner: { type: Object, default: null },
  cardTemplate: { type: Object, default: null },
});

// If a per-batch logo override was uploaded at creation, prefer it over the
// partner's stored logo so the show page mirrors the saved PDF exactly.
const effectivePartner = computed(() => {
  const override = props.card?.partner_logo_url;
  if (override) {
    return { ...(props.partner || {}), image: override };
  }
  return props.partner;
});

const batchPdfDownloadName = computed(() => {
  const raw = props.card?.batch_name?.trim() || `batch-${props.card?.id}`;
  const safe = raw.replace(/[^A-Za-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '') || `batch-${props.card?.id}`;
  return `${safe}.pdf`;
});

function confirmDelete() {
  if (!confirm(`Delete batch "${props.card.batch_name || `#${props.card.id}`}"? The ${props.card.memberships.length} generated memberships will stay; only the batch row + PDF will be removed.`)) return;
  router.delete(route('admin.membership-card-patches.destroy', props.card.id));
}

const detail = ref(null);
function openDetail(m) {
  detail.value = m;
}

function jobTitleText(jt) {
  if (!jt) return '—';
  if (typeof jt === 'string') return jt;
  // Spatie translatable: an object like { en: '...', ar: '...' }
  return jt.en || jt.ar || Object.values(jt)[0] || '—';
}

const numberFilter = ref('');
const filteredMemberships = computed(() => {
  const q = numberFilter.value.trim().toLowerCase();
  if (!q) return props.card?.memberships || [];
  return (props.card?.memberships || []).filter((m) => {
    const stored = String(m.membership_number || '').toLowerCase();
    const printed = String(`${props.card?.display_prefix || ''}${m.membership_number || ''}`).toLowerCase();
    return stored.includes(q) || printed.includes(q);
  });
});

function printedMembership(m) {
  const dPrefix = props.card?.display_prefix || '';
  return {
    ...m,
    membership_number: `${dPrefix}${m.membership_number}`,
  };
}

const cardModal = ref(null);
function openCard(m) {
  cardModal.value = m;
}

/** The card being shown full size, opened from a preview or the card modal. */
const zoomed = ref(null);

// Escape closes the topmost layer only, so zooming out of a card leaves the
// card modal it was opened from still standing.
function onKeydown(event) {
  if (event.key !== 'Escape') return;
  if (zoomed.value) zoomed.value = null;
  else if (cardModal.value) cardModal.value = null;
  else if (detail.value) detail.value = null;
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const downloadingImageId = ref(null);
async function downloadCardImage(m) {
  if (downloadingImageId.value) return;
  downloadingImageId.value = m.id;
  try {
    const canvas = await renderCardCanvas({
      membership: printedMembership(m),
      partner: effectivePartner.value,
      template: props.cardTemplate,
      overrides: props.card?.layout_overrides ?? null,
    contact: props.card?.layout_overrides?.contact_text ?? null,
    });

    const rawName = String(m.membership_number ?? `card-${m.id}`);
    const safeName = rawName.replace(/[^A-Za-z0-9_-]+/g, '_') || `card-${m.id}`;

    canvas.toBlob((blob) => {
      if (!blob) return;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${safeName}.png`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    }, 'image/png');
  } finally {
    downloadingImageId.value = null;
  }
}

const downloadingId = ref(null);
async function downloadCardPdf(m) {
  if (downloadingId.value) return;
  downloadingId.value = m.id;
  try {
    const canvas = await renderCardCanvas({
      membership: printedMembership(m),
      partner: effectivePartner.value,
      template: props.cardTemplate,
      overrides: props.card?.layout_overrides ?? null,
    contact: props.card?.layout_overrides?.contact_text ?? null,
    });
    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    const pdf = new jsPDF({ unit: 'cm', format: [9.0, 5.6], orientation: 'landscape' });
    pdf.addImage(dataUrl, 'JPEG', 0, 0, 9.0, 5.6, undefined, 'FAST');
    const blob = pdf.output('blob');

    const rawName = String(m.membership_number ?? `card-${m.id}`);
    const safeName = rawName.replace(/[^A-Za-z0-9_-]+/g, '_') || `card-${m.id}`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${safeName}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } finally {
    downloadingId.value = null;
  }
}
</script>
