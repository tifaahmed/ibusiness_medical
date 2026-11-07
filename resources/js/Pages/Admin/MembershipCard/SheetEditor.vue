<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4" data-testid="sheet-editor">
      <!-- Header -->
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <h1 class="text-lg sm:text-xl font-semibold">{{ t.title || 'Print sheet editor' }}</h1>
          <p class="text-xs text-muted-foreground mt-1">
            {{ card.batch_name || `Batch #${card.id}` }} · {{ t.hint || 'Drag a card, a cut line or the pin mark with the mouse, or type centimetres. One Save keeps everything.' }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('admin.membership-card-patches.show', card.id)" class="inline-flex h-10 items-center rounded-md border border-border px-3 text-sm hover:bg-muted" data-testid="sheet-back">← {{ t.back || 'Back to the batch' }}</Link>
          <button type="button" class="h-10 rounded-md border border-border px-3 text-sm hover:bg-muted cursor-pointer" data-testid="sheet-reset" @click="resetAll">{{ t.reset || 'Back to the standard layout' }}</button>
          <button type="button" :disabled="saving || !dirty || !canSave" :title="canSave ? '' : (t.no_permission || 'You can view the layout but not save it')" class="h-10 min-w-[8rem] rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50 cursor-pointer" data-testid="sheet-save" @click="save">
            {{ saving ? (t.saving || 'Saving…') : (t.save || 'Save layout') }}
          </button>
        </div>
      </div>

      <!-- FIRST: the card's real size (front and back). Everything below is measured from these. -->
      <div class="rounded-xl border border-border bg-card p-3 sm:p-4 space-y-3" data-testid="sheet-sizes">
        <h2 class="text-sm font-semibold">{{ t.sizes || 'Card size (cm)' }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="side_ in SIZES" :key="side_.key" class="space-y-2" :data-testid="'size-' + side_.key">
            <div class="text-xs font-medium text-muted-foreground">{{ side_.key === 'front' ? (t.front || 'Front') : (t.back_side || 'Back') }}</div>
            <div class="flex flex-wrap items-end gap-2">
              <label class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.width || 'Width' }}</span>
                <input v-model.number="print[side_.w]" type="number" step="0.05" min="1" class="h-11 w-28 rounded-md border border-border bg-background px-2 text-base tabular-nums" :data-testid="'size-' + side_.key + '-w'" /></label>
              <span class="pb-3 text-muted-foreground">×</span>
              <label class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.height || 'Height' }}</span>
                <input v-model.number="print[side_.h]" type="number" step="0.05" min="1" class="h-11 w-28 rounded-md border border-border bg-background px-2 text-base tabular-nums" :data-testid="'size-' + side_.key + '-h'" /></label>
              <button type="button" :disabled="!aspect[side_.key]" class="h-11 rounded-md border border-border px-3 text-xs hover:bg-muted disabled:opacity-40 cursor-pointer" :data-testid="'size-' + side_.key + '-match'" @click="matchHeight(side_)">{{ t.match || 'Match the artwork' }}</button>
            </div>
            <p class="text-xs text-muted-foreground tabular-nums" dir="ltr">
              <template v-if="aspect[side_.key]">artwork {{ aspect[side_.key].w }}×{{ aspect[side_.key].h }} px · ratio {{ aspect[side_.key].ratio.toFixed(3) }} · now {{ ratioNow(side_).toFixed(3) }}
                <span :class="Math.abs(ratioNow(side_) / aspect[side_.key].ratio - 1) > 0.01 ? 'value-warning' : 'value-positive'">({{ stretchLabel(side_) }})</span></template>
              <template v-else>…</template>
            </p>
          </div>
        </div>
      </div>

      <!-- The rule, always visible -->
      <div class="flex flex-wrap items-center gap-2 text-xs" data-testid="sheet-count">
        <span class="chip tabular-nums" :class="L.perPage === 10 ? 'chip-positive' : 'chip-negative'">{{ L.perPage }} {{ t.per_sheet || 'cards per sheet' }}</span>
        <span v-if="L.perPage !== 10" class="value-negative">{{ t.rule_broken || 'The sheet must hold 10 cards — reduce the top strip or the card size.' }}</span>
        <span v-if="dirty" class="chip chip-warning">{{ t.unsaved || 'Not saved yet' }}</span>
        <span v-else class="chip chip-neutral">{{ t.saved || 'Saved' }}</span>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_26rem] gap-4 items-start">
        <!-- The A4 sheet -->
        <div class="rounded-xl border border-border bg-card p-3 space-y-3">
          <!-- Which side of the paper: the back is the same card turned over, so moving one moves the other -->
          <div class="flex flex-wrap items-center gap-2" data-testid="sheet-side">
            <div class="inline-flex overflow-hidden rounded-md border border-border">
              <button type="button" class="h-10 px-4 text-sm" :class="side === 'front' ? 'bg-primary text-primary-foreground' : 'bg-background'" data-testid="side-front" @click="side = 'front'">{{ t.front || 'Front' }}</button>
              <button type="button" class="h-10 px-4 text-sm" :class="side === 'back' ? 'bg-primary text-primary-foreground' : 'bg-background'" data-testid="side-back" @click="side = 'back'">{{ t.back_side || 'Back' }}</button>
            </div>
            <!-- How big the sheet is on screen: "Real size" shows 1 cm as 1 cm (96 dpi), so the cards measure what the settings say -->
            <div class="inline-flex overflow-hidden rounded-md border border-border" data-testid="sheet-zoom">
              <button v-for="z in ZOOMS" :key="z.k" type="button" class="h-10 px-3 text-sm" :class="zoom === z.k ? 'bg-primary text-primary-foreground' : 'bg-background'" :data-testid="'zoom-' + z.k" @click="zoom = z.k">{{ z.l }}</button>
            </div>
            <span class="chip chip-neutral tabular-nums" dir="ltr" data-testid="sheet-sizes-now">{{ L.w }} × {{ L.h }} cm · {{ zoomLabel }}</span>
            <span v-if="side === 'back'" class="text-xs text-muted-foreground">{{ t.back_hint || 'Each back is centred in its card and moves with it — drag a back and the card moves the same way.' }}</span>
          </div>
          <div ref="stage" class="w-full overflow-auto" data-testid="sheet-stage">
            <div class="relative select-none bg-white shadow" :class="zoom === 'fit' ? 'mx-auto' : ''" :style="{ width: pageW * scale + 'px', height: pageH * scale + 'px' }" data-testid="sheet-page" @pointerdown.self="selected = null">
              <!-- cut lines: drag one (a 12 px strip around each is the handle) -->
              <template v-if="cfg.guides">
                <div v-for="(y, i) in shapes.hLines" :key="'h' + i" class="absolute left-0 right-0 cursor-row-resize touch-none" :style="{ top: y * scale - 6 + 'px', height: '12px' }" :data-testid="'line-h-' + i"
                     @pointerdown.stop="startLine('h', i, $event)" @pointermove="onLine" @pointerup="endLine" @pointercancel="endLine">
                  <div class="absolute left-0 right-0 top-1/2 pointer-events-none" style="border-top: 1px solid #b0b0b0"></div>
                </div>
                <div v-for="(x, i) in shapes.vLines" :key="'v' + i" class="absolute top-0 bottom-0 cursor-col-resize touch-none" :style="{ left: x * scale - 6 + 'px', width: '12px' }" :data-testid="'line-v-' + i"
                     @pointerdown.stop="startLine('v', i, $event)" @pointermove="onLine" @pointerup="endLine" @pointercancel="endLine">
                  <div class="absolute top-0 bottom-0 left-1/2 pointer-events-none" style="border-left: 1px solid #b0b0b0"></div>
                </div>
              </template>
              <!-- pin mark: drag it too -->
              <div class="absolute cursor-move touch-none rounded-full border border-gray-500" :style="{ left: (shapes.pin.x - shapes.pin.r) * scale + 'px', top: (shapes.pin.y - shapes.pin.r) * scale + 'px', width: shapes.pin.r * 2 * scale + 'px', height: shapes.pin.r * 2 * scale + 'px' }" data-testid="sheet-pin"
                   @pointerdown.stop="startPin($event)" @pointermove="onPin" @pointerup="endPin" @pointercancel="endPin">
                <div class="absolute left-1/2 top-[20%] bottom-[20%] border-l border-gray-500 pointer-events-none"></div>
                <div class="absolute top-1/2 left-[20%] right-[20%] border-t border-gray-500 pointer-events-none"></div>
              </div>
              <!-- the FRONTS -->
              <template v-if="side === 'front'">
                <div v-for="(r, i) in rects" :key="'c' + i" class="absolute overflow-hidden cursor-move touch-none" :class="selected === i ? 'ring-2 ring-primary' : 'ring-1 ring-black/10'" :style="boxStyle(r)" :data-testid="'sheet-card-' + i"
                     @pointerdown="startDrag(i, $event)" @pointermove="onDrag" @pointerup="endDrag" @pointercancel="endDrag">
                  <div class="pointer-events-none h-full w-full">
                    <CardPreview v-if="printed[i]" :membership="printed[i]" :template="template" :partner="partner" :overrides="cardOverrides" :contact="contact" display-width="100%" />
                    <div v-else class="flex h-full items-center justify-center bg-muted/40 text-[10px] text-muted-foreground">{{ i + 1 }}</div>
                  </div>
                  <span class="absolute left-1 top-1 rounded bg-black/60 px-1 text-[10px] leading-4 text-white pointer-events-none">{{ i + 1 }}</span>
                </div>
              </template>
              <!-- the BACKS: the small back picture centred in the (dashed) footprint of its card, exactly behind its front -->
              <template v-else>
                <div v-for="(b, i) in backs" :key="'k' + i" class="absolute cursor-move touch-none" :style="boxStyle(b.outer)" :data-testid="'sheet-back-' + i"
                     @pointerdown="startDrag(i, $event)" @pointermove="onDrag" @pointerup="endDrag" @pointercancel="endDrag">
                  <div class="absolute inset-0 border border-dashed" :class="selected === i ? 'border-primary bg-primary/5' : 'border-sky-500/70 bg-sky-500/5'"></div>
                  <img :src="backSrc" alt="" draggable="false" class="absolute object-fill pointer-events-none ring-1 ring-black/20" :style="{ left: (b.x - b.outer.x) * scale + 'px', top: (b.y - b.outer.y) * scale + 'px', width: b.w * scale + 'px', height: b.h * scale + 'px' }" />
                  <span class="absolute left-1 top-1 rounded bg-black/60 px-1 text-[10px] leading-4 text-white pointer-events-none">{{ i + 1 }}</span>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div class="space-y-4">
          <div class="rounded-xl border border-border bg-card p-3 space-y-3" data-testid="sheet-globals">
            <h2 class="text-sm font-semibold">{{ t.sheet || 'Sheet' }}</h2>
            <div class="grid grid-cols-2 gap-3">
              <label class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.top_reserve || 'Top strip (pin) cm' }}</span>
                <input v-model.number="cfg.top_reserve" type="number" step="0.05" min="0.2" max="6" class="h-11 w-full rounded-md border border-border bg-background px-2 text-base tabular-nums" data-testid="g-top" /></label>
              <label class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.side_margin || 'Side margin cm' }}</span>
                <input v-model.number="cfg.side_margin" type="number" step="0.05" min="0" max="5" class="h-11 w-full rounded-md border border-border bg-background px-2 text-base tabular-nums" data-testid="g-side" /></label>
              <label class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.pin_x || 'Pin mark X cm' }}</span>
                <input :value="round(frontShapes.pin.x)" type="number" step="0.05" class="h-11 w-full rounded-md border border-border bg-background px-2 text-base tabular-nums" data-testid="g-pin-x" @change="setPinX($event.target.value)" /></label>
              <label class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.pin_y || 'Pin mark Y cm' }}</span>
                <input v-model.number="cfg.pin_y" type="number" step="0.05" min="0.1" max="5" class="h-11 w-full rounded-md border border-border bg-background px-2 text-base tabular-nums" data-testid="g-pin" /></label>
            </div>
            <label class="flex items-center gap-2 text-sm min-h-[40px]"><input v-model="cfg.guides" type="checkbox" class="h-5 w-5" /> {{ t.guides || 'Cut lines' }}</label>
            <label class="flex items-center gap-2 text-sm min-h-[40px]"><input v-model="moveAll" type="checkbox" class="h-5 w-5" data-testid="move-all" /> {{ t.move_all || 'Drag / nudge moves ALL cards together' }}</label>
          </div>

          <!-- Cut lines: typed positions -->
          <div v-if="cfg.guides" class="rounded-xl border border-border bg-card p-3 space-y-2" data-testid="sheet-lines">
            <h2 class="text-sm font-semibold">{{ t.lines || 'Cut lines (cm)' }}</h2>
            <div class="grid grid-cols-2 gap-2">
              <label v-for="(y, i) in frontShapes.hLines" :key="'hl' + i" class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.line_h || 'Row line' }} {{ i + 1 }} · Y</span>
                <input :value="round(y)" type="number" step="0.05" class="h-10 w-full rounded-md border border-border bg-background px-2 tabular-nums" :data-testid="'lf-h-' + i" @change="setLine('h', i, $event.target.value)" /></label>
              <label v-for="(x, i) in frontShapes.vLines" :key="'vl' + i" class="text-xs space-y-1"><span class="block text-muted-foreground">{{ t.line_v || 'Column line' }} {{ i + 1 }} · X</span>
                <input :value="round(x)" type="number" step="0.05" class="h-10 w-full rounded-md border border-border bg-background px-2 tabular-nums" :data-testid="'lf-v-' + i" @change="setLine('v', i, $event.target.value)" /></label>
            </div>
          </div>

          <!-- Selected card: nudge -->
          <div v-if="selected !== null" class="rounded-xl border border-border bg-card p-3 space-y-2" data-testid="sheet-selected">
            <div class="text-sm font-semibold">{{ t.card || 'Card' }} {{ selected + 1 }}<span v-if="printed[selected]" class="ms-2 text-xs text-muted-foreground tabular-nums" dir="ltr">{{ printed[selected].membership_number }}</span></div>
            <div class="grid grid-cols-3 gap-2 w-40">
              <span></span><button type="button" class="h-11 rounded-md border border-border" @click="nudge(0, -STEP)">↑</button><span></span>
              <button type="button" class="h-11 rounded-md border border-border" @click="nudge(-STEP, 0)">←</button>
              <button type="button" class="h-11 rounded-md border border-border text-xs" @click="resetCard(selected)">{{ t.reset_short || 'reset' }}</button>
              <button type="button" class="h-11 rounded-md border border-border" @click="nudge(STEP, 0)">→</button>
              <span></span><button type="button" class="h-11 rounded-md border border-border" @click="nudge(0, STEP)">↓</button><span></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Every card: position and size, in cm -->
      <div class="rounded-xl border border-border bg-card overflow-x-auto" data-testid="sheet-table">
        <table class="w-full text-sm">
          <thead class="bg-muted/40">
            <tr>
              <th class="px-3 py-2 text-left font-medium">#</th>
              <th class="px-3 py-2 text-left font-medium">{{ t.number || 'Number' }}</th>
              <th class="px-3 py-2 text-left font-medium">X (cm)</th>
              <th class="px-3 py-2 text-left font-medium">Y (cm)</th>
              <th class="px-3 py-2 text-left font-medium">{{ t.width || 'Width' }} (cm)</th>
              <th class="px-3 py-2 text-left font-medium">{{ t.height || 'Height' }} (cm)</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in rects" :key="'row' + i" class="border-t border-border" :class="selected === i ? 'bg-primary/5' : ''" @click="selected = i">
              <td class="px-3 py-1.5 tabular-nums">{{ i + 1 }}</td>
              <td class="px-3 py-1.5 font-mono text-xs" dir="ltr">{{ printed[i]?.membership_number || '—' }}</td>
              <td v-for="f in FIELDS" :key="f" class="px-2 py-1">
                <input :value="round(r[f])" type="number" step="0.05" class="h-10 w-24 rounded-md border border-border bg-background px-2 tabular-nums" :data-testid="'f-' + f + '-' + i" @change="setField(i, f, $event.target.value)" @focus="selected = i" />
              </td>
              <td class="px-2 py-1"><button type="button" class="text-xs underline" @click.stop="resetCard(i)">{{ t.reset_short || 'reset' }}</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="error" class="panel-danger p-2 text-sm">{{ error }}</p>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';
import CardPreview from './_components/CardPreview.vue';
import { cardPrintedNumber, loadImage } from './_components/cardRenderer.js';
import { PAGE_W, PAGE_H, normalizeSheet, sheetLayout, slotRect, backRect, guideShapes } from './_components/sheetGeometry.js';
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  card: { type: Object, required: true },
  partner: { type: Object, default: null },
  cardTemplate: { type: Object, default: null },
  cardPrint: { type: Object, default: () => ({ width: 8.5, height: 5.5, back_width: 8, back_height: 5, sheet: {} }) },
  canSave: { type: Boolean, default: false },
});

const page = usePage();
const t = computed(() => page.props.translations?.admin?.sheet_editor || {});

const pageW = PAGE_W;
const pageH = PAGE_H;
const STEP = 0.05; // dragging and nudging snap to half a millimetre
const FIELDS = ['x', 'y', 'w', 'h'];
const SIZES = [{ key: 'front', w: 'width', h: 'height' }, { key: 'back', w: 'back_width', h: 'back_height' }];

// ---- the layout being edited (a copy of what is saved) -----------------------------------------------------------
const SLOTS = 12;
const padArr = (arr, n) => Array.from({ length: n }, (_, i) => Number(arr?.[i]) || 0);
const fromSaved = (sheet) => {
  const n = normalizeSheet(sheet);
  const cards = Array.from({ length: SLOTS }, (_, i) => ({ dx: 0, dy: 0, w: null, h: null, ...(n.cards[i] || {}) }));
  return { top_reserve: n.top_reserve, side_margin: n.side_margin, pin_y: n.pin_y, pin_dx: n.pin_dx, guides: n.guides, h_lines: padArr(n.h_lines, 12), v_lines: padArr(n.v_lines, 4), cards };
};
const printFrom = (p) => ({ width: Number(p?.width) || 8.5, height: Number(p?.height) || 5.5, back_width: Number(p?.back_width) || 8, back_height: Number(p?.back_height) || 5 });
const cfg = reactive(fromSaved(props.cardPrint.sheet));
const print = reactive(printFrom(props.cardPrint)); // the card sizes — the FIRST thing on the page
const snapshot = () => ({ top_reserve: cfg.top_reserve, side_margin: cfg.side_margin, pin_y: cfg.pin_y, pin_dx: cfg.pin_dx, guides: cfg.guides, h_lines: [...cfg.h_lines], v_lines: [...cfg.v_lines], cards: cfg.cards.map((c) => ({ dx: c.dx, dy: c.dy, w: c.w, h: c.h })) });
const payload = () => ({ ...normalizeSheet(snapshot()), print: { ...print } });
const saved = ref(JSON.stringify({ ...normalizeSheet(snapshot()), print: { ...print } }));
const dirty = computed(() => JSON.stringify(payload()) !== saved.value);

// The same maths the PDF uses (sheetGeometry.js): what you see here is what prints.
const printNow = () => ({ ...props.cardPrint, ...print });
const L = computed(() => sheetLayout({ ...printNow(), sheet: snapshot() }));
const rects = computed(() => Array.from({ length: L.value.perPage }, (_, i) => slotRect(L.value, i, false)));
// The back of each card, exactly as the PDF places it (sheetGeometry.backRect): the small back centred in its card's mirrored footprint.
const backs = computed(() => Array.from({ length: L.value.perPage }, (_, i) => backRect(L.value, i, printNow())));
const backSrc = computed(() => props.cardTemplate?.back_url || '/images/cards/card-backside.png');
const shapes = computed(() => guideShapes(L.value, side.value === 'back')); // what is drawn on the side on screen
const frontShapes = computed(() => guideShapes(L.value, false)); // the typed fields always speak "front"
const baseLayout = computed(() => sheetLayout({ ...printNow(), sheet: { ...snapshot(), cards: [], h_lines: [], v_lines: [], pin_dx: 0 } }));
const baseShapes = computed(() => guideShapes(baseLayout.value, false));
const baseRect = (i) => slotRect(baseLayout.value, i, false); // where the grid itself would put a card

// ---- the card sizes against the real artwork's proportions ---------------------------------------------------------
const aspect = reactive({ front: null, back: null });
const ratioNow = (s) => print[s.w] / print[s.h];
const stretchLabel = (s) => {
  const k = ratioNow(s) / aspect[s.key].ratio - 1;
  return Math.abs(k) <= 0.01 ? 'proportions match' : `${k > 0 ? 'wider' : 'taller'} than the artwork by ${(Math.abs(k) * 100).toFixed(1)}%`;
};
function matchHeight(s) { if (aspect[s.key]) print[s.h] = Math.round((print[s.w] / aspect[s.key].ratio) * 100) / 100; }
async function measureArtwork() {
  for (const [key, url] of [['front', props.cardTemplate?.card_empty_url], ['back', backSrc.value]]) {
    try {
      const img = url ? await loadImage(url) : null;
      if (img && img.naturalWidth > 0) aspect[key] = { w: img.naturalWidth, h: img.naturalHeight, ratio: img.naturalWidth / img.naturalHeight };
    } catch (e) { /* the hint just stays blank */ }
  }
}

// ---- the cards' real design (stable objects: handed to CardPreview, which repaints when their CONTENT changes) ------
const template = computed(() => props.cardTemplate);
const partner = computed(() => (props.card.partner_logo_url ? { ...(props.partner || {}), image: props.card.partner_logo_url } : props.partner));
const cardOverrides = computed(() => props.card.layout_overrides || null);
const contact = computed(() => props.card.layout_overrides?.contact_text ?? null);
const printed = computed(() => props.card.memberships.map((m) => ({
  ...m,
  membership_number: cardPrintedNumber(m.membership_number, { displayPrefix: props.card.display_prefix, groups: props.card.display_groups }),
  stored_number: m.membership_number,
})));

// ---- the stage: pixels per cm follow the width available -------------------------------------------------------
const stage = ref(null);
const PX_PER_CM = 96 / 2.54; // a CSS inch is 96 px, so this is real size on a normal screen
const ZOOMS = [{ k: 'fit', l: 'Fit' }, { k: 1, l: 'Real size' }, { k: 1.5, l: '150%' }, { k: 2, l: '200%' }];
const zoom = ref('fit');
const fitScale = ref(24);
// px per cm: "fit" squeezes the whole A4 into the column; the others show the sheet at that multiple of real size (scroll to move around)
const scale = computed(() => (zoom.value === 'fit' ? fitScale.value : zoom.value * PX_PER_CM));
const zoomLabel = computed(() => (zoom.value === 'fit' ? `fit to the screen (${(fitScale.value / PX_PER_CM * 100).toFixed(0)}% of real size)` : `${Math.round(zoom.value * 100)}% of real size`));
let ro = null;
const fit = () => {
  const w = stage.value?.clientWidth || 0;
  if (w > 0) fitScale.value = Math.min(w / PAGE_W, 40);
};
onMounted(() => {
  fit();
  if (typeof ResizeObserver !== 'undefined' && stage.value) { ro = new ResizeObserver(fit); ro.observe(stage.value); }
  measureArtwork();
});
onBeforeUnmount(() => ro && ro.disconnect());
const boxStyle = (r) => ({ left: r.x * scale.value + 'px', top: r.y * scale.value + 'px', width: r.w * scale.value + 'px', height: r.h * scale.value + 'px' });

// ---- editing -------------------------------------------------------------------------------------------------------
const selected = ref(null);
const moveAll = ref(false);
const side = ref('front'); // which side of the sheet is on screen
// On the back the sheet is turned over, so dragging something to the right moves it to the LEFT in front terms.
const dirSign = () => (side.value === 'back' ? -1 : 1);
const round = (v) => Math.round(v * 100) / 100;
const snap = (v) => Math.round(v / STEP) * STEP;

let drag = null;
function startDrag(i, e) {
  selected.value = i;
  drag = { i, sx: e.clientX, sy: e.clientY, base: cfg.cards.map((c) => ({ dx: c.dx, dy: c.dy })) };
  e.currentTarget.setPointerCapture?.(e.pointerId);
}
function onDrag(e) {
  if (!drag) return;
  const ddx = snap(((e.clientX - drag.sx) / scale.value) * dirSign());
  const ddy = snap((e.clientY - drag.sy) / scale.value);
  const targets = moveAll.value ? cfg.cards.map((_, k) => k) : [drag.i];
  targets.forEach((k) => { cfg.cards[k].dx = round(drag.base[k].dx + ddx); cfg.cards[k].dy = round(drag.base[k].dy + ddy); });
}
function endDrag(e) {
  if (drag) e.currentTarget.releasePointerCapture?.(e.pointerId);
  drag = null;
}
function nudge(dx, dy) {
  if (selected.value === null) return;
  dx *= dirSign();
  const targets = moveAll.value ? cfg.cards.map((_, k) => k) : [selected.value];
  targets.forEach((k) => { cfg.cards[k].dx = round(cfg.cards[k].dx + dx); cfg.cards[k].dy = round(cfg.cards[k].dy + dy); });
}
// A typed X / Y / width / height for one card. X and Y are absolute on the sheet; they are kept as an offset from the grid's own spot.
function setField(i, f, raw) {
  const v = Number(raw);
  if (!Number.isFinite(v)) return;
  const c = cfg.cards[i];
  const b = baseRect(i);
  if (f === 'x') c.dx = round(v - b.x);
  else if (f === 'y') c.dy = round(v - b.y);
  else if (f === 'w') c.w = v > 0 ? round(v) : null;
  else if (f === 'h') c.h = v > 0 ? round(v) : null;
}
function resetCard(i) { Object.assign(cfg.cards[i], { dx: 0, dy: 0, w: null, h: null }); }

// Cut lines and the pin mark: dragged on the sheet or typed. Stored as offsets from where the grid puts them.
let lineDrag = null;
function startLine(kind, i, e) {
  lineDrag = { kind, i, sx: e.clientX, sy: e.clientY, base: kind === 'h' ? cfg.h_lines[i] : cfg.v_lines[i] };
  e.currentTarget.setPointerCapture?.(e.pointerId);
}
function onLine(e) {
  if (!lineDrag) return;
  if (lineDrag.kind === 'h') cfg.h_lines[lineDrag.i] = round(lineDrag.base + snap((e.clientY - lineDrag.sy) / scale.value));
  else cfg.v_lines[lineDrag.i] = round(lineDrag.base + snap(((e.clientX - lineDrag.sx) / scale.value) * dirSign()));
}
function endLine(e) {
  if (lineDrag) e.currentTarget.releasePointerCapture?.(e.pointerId);
  lineDrag = null;
}
function setLine(kind, i, raw) {
  const v = Number(raw);
  if (!Number.isFinite(v)) return;
  if (kind === 'h') cfg.h_lines[i] = round(v - baseShapes.value.hLines[i]);
  else cfg.v_lines[i] = round(v - baseShapes.value.vLines[i]);
}
let pinDrag = null;
function startPin(e) {
  pinDrag = { sx: e.clientX, sy: e.clientY, dx: cfg.pin_dx, y: cfg.pin_y };
  e.currentTarget.setPointerCapture?.(e.pointerId);
}
function onPin(e) {
  if (!pinDrag) return;
  cfg.pin_dx = round(pinDrag.dx + snap(((e.clientX - pinDrag.sx) / scale.value) * dirSign()));
  cfg.pin_y = round(Math.max(0.1, pinDrag.y + snap((e.clientY - pinDrag.sy) / scale.value)));
}
function endPin(e) {
  if (pinDrag) e.currentTarget.releasePointerCapture?.(e.pointerId);
  pinDrag = null;
}
function setPinX(raw) {
  const v = Number(raw);
  if (Number.isFinite(v)) cfg.pin_dx = round(v - PAGE_W / 2);
}

function resetAll() {
  const d = fromSaved({});
  Object.assign(cfg, d); // the sheet back to the standard layout; the card SIZES are yours to keep
}

// ---- saving: ONE button keeps the card sizes, the position and size of every card, the lines and the pin ------------
const saving = ref(false);
const error = ref('');
function save() {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  const body = payload();
  router.post(route('admin.membership-card-patches.sheet.save', props.card.id), body, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => { saved.value = JSON.stringify(body); useNotification().success('Sheet layout saved'); },
    onError: (e) => { error.value = Object.values(e)[0] || 'Could not save.'; useNotification().error('Could not save the layout'); },
    onFinish: () => { saving.value = false; },
  });
}
</script>

<style scoped>
/* A card is drawn to FILL its box, exactly as the PDF stretches the picture to the card's width × height. */
:deep(.ash-card-host) {
  height: 100%;
  max-width: none;
  border-radius: 0;
  box-shadow: none;
  aspect-ratio: auto !important;
}
</style>
