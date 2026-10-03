<template>
  <AppLayout>
    <div class="p-3 sm:p-4 md:p-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="flex items-center gap-2 text-lg font-semibold sm:text-xl">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M7 9h10" /><path d="M7 13h6" /></svg>
          Back side — {{ name }}
        </h1>
        <button type="button" class="h-9 rounded-md border border-border bg-background px-3 text-sm font-medium hover:bg-muted" @click="goBack">Back to templates</button>
      </div>

      <div class="rounded-xl border border-border bg-card text-card-foreground shadow-sm">
        <div class="space-y-3 border-b border-border p-4">
          <label class="flex items-center gap-2 text-sm font-medium">
            <input v-model="cfg.enabled" type="checkbox" class="h-4 w-4" />
            Use a custom back for this template
          </label>
          <p class="text-xs text-muted-foreground">
            Off = the shipped back artwork. On = the back below, everywhere cards from this template are shown or downloaded.
          </p>
          <div class="space-y-1" :class="{ 'opacity-50 pointer-events-none': !cfg.enabled }">
            <label class="text-xs font-medium">Background artwork</label>
            <div class="flex items-center gap-2">
              <input type="file" accept="image/png,image/jpeg,image/webp" class="text-xs" @change="pick('back_image', $event)" />
              <button v-if="hasImage" type="button" title="Remove the uploaded background (the default is used)" class="inline-flex h-7 shrink-0 cursor-pointer items-center gap-1 rounded-md bg-destructive px-2 text-[11px] font-medium text-white shadow-xs transition hover:bg-destructive/90" @click="clearFile('back_image')"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6" /><path d="M14 11v6" /></svg> Remove</button>
            </div>
            <p v-if="!hasImage" class="text-[11px] text-muted-foreground">No upload — the default Deilar background is used.</p>
            <p v-if="template.back_image_url && !removed.back_image" class="text-[11px] text-muted-foreground">Current: {{ template.back_image_url }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 xl:grid-cols-4" :class="{ 'opacity-50 pointer-events-none': !cfg.enabled }">
          <div class="space-y-2 xl:col-span-3">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm font-semibold">Layout</h2>
              <span v-if="!cfg.enabled" class="text-xs text-amber-600">Custom back is off — cards use the shipped back</span>
            </div>
            <p class="text-[11px] text-muted-foreground">
              Drag an element to move it, or drag its bottom-right corner to resize. Positions are stored as fractions of the card.
            </p>
            <div class="overflow-hidden rounded-md border border-border">
              <CardBackPreview v-model:selected="selected" :config="cfg" :background="backgroundSrc" :logo="logoSrc" editable />
            </div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="el in elements" :key="el.key" type="button"
                class="cursor-pointer rounded-md px-2 py-1 text-[11px] font-medium"
                :class="[el.key === selected ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/70', cfg[el.key].visible ? '' : 'line-through opacity-60']"
                @click="selected = el.key"
              >{{ el.label }}</button>
            </div>
          </div>

          <div class="space-y-3 rounded-xl border border-border p-4">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm font-semibold">{{ currentLabel }}</h2>
              <button type="button" title="Put this element back to its defaults" class="inline-flex h-7 shrink-0 cursor-pointer items-center gap-1 rounded-md bg-secondary px-2 text-[11px] font-medium text-secondary-foreground shadow-xs transition hover:bg-secondary/80" @click="resetElement(selected)">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7" /><polyline points="3 3 3 9 9 9" /></svg> Reset {{ currentLabel.toLowerCase() }}
              </button>
            </div>
            <template v-if="field">
              <label class="flex items-center gap-2 text-xs"><input v-model="field.visible" type="checkbox" /> Show on the back</label>

              <div class="grid grid-cols-2 gap-2">
                <label v-for="k in ['x', 'y', 'width', 'height']" :key="k" class="text-[10px] uppercase text-muted-foreground">
                  {{ k }} (%)
                  <input type="number" step="0.1" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" :value="round(field[k] * 100)" @input="setFraction(k, $event.target.value)" />
                </label>
              </div>

              <template v-if="textKeys.includes(selected)">
                <label class="block text-[10px] uppercase text-muted-foreground">
                  Value
                  <input v-model="field.text" type="text" maxlength="120" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" />
                </label>
                <div class="grid grid-cols-2 gap-2">
                  <label class="text-[10px] uppercase text-muted-foreground">
                    Font size
                    <input v-model.number="field.font_size" type="number" step="0.5" min="1" max="200" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" />
                  </label>
                  <label class="text-[10px] uppercase text-muted-foreground">
                    Align
                    <select v-model="field.direction" class="w-full rounded border border-border bg-background px-2 py-1 text-xs">
                      <option value="ltr">ltr (left)</option>
                      <option value="center">center</option>
                      <option value="rtl">rtl (right)</option>
                    </select>
                  </label>
                  <label class="col-span-2 text-[10px] uppercase text-muted-foreground">
                    Colour
                    <input v-model="field.color" type="color" class="h-7 w-full rounded border border-border bg-background" />
                  </label>
                </div>
                <p class="text-[10px] text-muted-foreground">Font size is pixels on a 700px-wide card and scales with the render.</p>
              </template>

              <template v-else-if="selected === 'qrcode'">
                <label class="block text-[10px] uppercase text-muted-foreground">
                  Encodes
                  <input v-model="field.value" type="text" dir="ltr" maxlength="500" placeholder="https://deilar.com" class="w-full rounded border border-border bg-background px-2 py-1 text-xs" />
                </label>
                <p class="text-[10px] text-muted-foreground">The same link on every card from this template.</p>
              </template>

              <template v-else-if="selected === 'logo'">
                <span class="block text-[10px] uppercase text-muted-foreground">Image</span>
                <div class="flex items-start gap-3">
                  <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded border border-border bg-muted/30">
                    <img v-if="logoSrc" :src="logoSrc" alt="" class="h-full w-full object-contain" />
                  </div>
                  <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap gap-2">
                      <label class="inline-flex cursor-pointer items-center gap-1 rounded border border-border px-2 py-1 text-[11px] hover:bg-muted">
                        {{ hasLogo ? 'Replace' : 'Upload' }}
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="pick('back_logo', $event)" />
                      </label>
                      <button v-if="hasLogo" type="button" title="Remove the uploaded logo (the default is used)" class="inline-flex h-7 shrink-0 cursor-pointer items-center gap-1 rounded-md bg-destructive px-2 text-[11px] font-medium text-white shadow-xs transition hover:bg-destructive/90" @click="clearFile('back_logo')"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /><path d="M10 11v6" /><path d="M14 11v6" /></svg> Remove</button>
                    </div>
                    <p class="text-[10px] text-muted-foreground">PNG or JPG, up to 5MB. Without an upload the default Deilar logo is used.</p>
                    <p v-if="files.back_logo" class="text-[10px] text-amber-600">Uploads when you save.</p>
                  </div>
                </div>
              </template>
            </template>
          </div>
        </div>

        <div class="space-y-3 border-t border-border p-4">
          <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">Live render</h2>
            <button type="button" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted" @click="flipped = !flipped">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9" /><path d="M3 11V9a4 4 0 0 1 4-4h14" /><polyline points="7 23 3 19 7 15" /><path d="M21 13v2a4 4 0 0 1-4 4H3" /></svg>
              {{ flipped ? 'Show front' : 'Flip to back' }}
            </button>
          </div>
          <p class="text-[11px] text-muted-foreground">
            The finished card as it will be shown — click the card or the button to flip it. It follows every edit above, saved or not.
          </p>
          <div class="mx-auto max-w-3xl cursor-pointer [perspective:1400px]" @click="flipped = !flipped">
            <div class="live-flip relative w-full" :class="{ flipped }" :style="{ aspectRatio: '1579 / 996' }">
              <div class="live-face overflow-hidden rounded-xl border border-border bg-white shadow-md">
                <img :src="frontSrc" alt="Card front" class="h-full w-full object-contain" />
              </div>
              <div class="live-face live-back overflow-hidden rounded-xl border border-border bg-white shadow-md">
                <CardBackPreview :config="cfg" :background="backgroundSrc" :logo="logoSrc" />
              </div>
            </div>
          </div>
          <p v-if="!cfg.enabled" class="text-center text-xs text-amber-600">Custom back is off — cards currently use the shipped back, not this one.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-t border-border px-4 py-3">
          <button type="button" :disabled="saving" class="inline-flex h-9 items-center rounded-md bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 cursor-pointer" @click="save({ stay: false })">
            {{ saving ? 'Saving…' : 'Save changes' }}
          </button>
          <button type="button" :disabled="saving" title="Save and keep editing" class="inline-flex h-9 items-center rounded-md border border-emerald-600 px-4 text-sm font-medium text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 cursor-pointer" @click="save({ stay: true })">
            Save and stay
          </button>
          <button type="button" class="inline-flex h-9 items-center rounded-md bg-muted px-4 text-sm font-medium hover:bg-muted/70 cursor-pointer" @click="requestClose">Cancel</button>
          <button type="button" title="Put every element back to its defaults" class="inline-flex h-9 shrink-0 cursor-pointer items-center gap-1 rounded-md bg-secondary px-3 text-xs font-medium text-secondary-foreground shadow-xs transition hover:bg-secondary/80" @click="resetToDefaults"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7" /><polyline points="3 3 3 9 9 9" /></svg> Reset all to defaults</button>
          <span v-if="dirty" class="text-xs text-amber-600">Unsaved changes</span>
          <span v-else-if="savedAt" class="text-[11px] text-emerald-700">Saved {{ savedAt }}</span>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import CardBackPreview from './CardBackPreview.vue';
import { AppLayout } from '@/Pages/Admin/Layout/Layout.js';

const props = defineProps({
  template: { type: Object, required: true },
  backDefaults: { type: Object, required: true },
});

const clone = (v) => JSON.parse(JSON.stringify(v));
const cfg = reactive(clone(props.template.back_config));
const snapshot = ref(JSON.stringify(cfg));
const files = reactive({ back_image: null, back_logo: null });
const removed = reactive({ back_image: false, back_logo: false });
const saving = ref(false);
const saved = ref(false);
const flipped = ref(false);
const frontSrc = computed(() => props.template.sample_card_url || props.template.card_empty_url || '/images/cards/deilar-card-blank.png');
const savedAt = ref('');
const error = ref('');

// Previews follow the edits: a freshly picked file shows from the browser
// before it is uploaded, a removed one falls back to the renderer's default.
const objectUrls = {};
const fileUrl = (key) => {
  const f = files[key];
  if (!f) return '';
  if (!objectUrls[key] || objectUrls[key].file !== f) {
    if (objectUrls[key]) URL.revokeObjectURL(objectUrls[key].url);
    objectUrls[key] = { file: f, url: URL.createObjectURL(f) };
  }
  return objectUrls[key].url;
};
const backgroundSrc = computed(() => fileUrl('back_image')
  || (removed.back_image ? '' : props.template.back_image_url)
  || '/images/cards/card-back-empty.png');
const logoSrc = computed(() => fileUrl('back_logo')
  || (removed.back_logo ? '' : props.template.back_logo_url)
  || '/images/logo/dielar.png');

const name = computed(() => {
  const n = props.template.name;
  return typeof n === 'string' ? n : (n?.en || n?.ar || `#${props.template.id}`);
});

const elements = [
  { key: 'logo', label: 'Logo' },
  { key: 'slogan', label: 'Slogan' },
  { key: 'title', label: 'Title' },
  { key: 'website', label: 'Website' },
  { key: 'qrcode', label: 'QR code' },
];
const textKeys = ['slogan', 'title', 'website'];
const selected = ref('title');
const field = computed(() => cfg[selected.value]);
const currentLabel = computed(() => elements.find((e) => e.key === selected.value)?.label || '');
const round = (n) => Math.round(Number(n) * 10) / 10;
const setFraction = (key, percent) => {
  const value = Number(percent);
  if (Number.isFinite(value)) field.value[key] = value / 100;
};

const hasImage = computed(() => files.back_image || (props.template.back_image_url && !removed.back_image));
const hasLogo = computed(() => files.back_logo || (props.template.back_logo_url && !removed.back_logo));
const dirty = computed(() => JSON.stringify(cfg) !== snapshot.value || files.back_image || files.back_logo || removed.back_image || removed.back_logo);

const pick = (key, event) => {
  files[key] = event.target.files?.[0] || null;
  if (files[key]) removed[key] = false;
};
const clearFile = (key) => { files[key] = null; removed[key] = true; };

const leave = () => router.visit(route('admin.card-templates.index'));
const goBack = () => {
  if (dirty.value && !window.confirm('You have unsaved changes to the back side. Leave without saving?')) return;
  leave();
};
const requestClose = goBack;

// Closing the tab or following a browser link would lose the edits too.
const warnUnload = (e) => { if (dirty.value) { e.preventDefault(); e.returnValue = ''; } };
onMounted(() => window.addEventListener('beforeunload', warnUnload));
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnUnload));

const reportError = (e) => {
  axios.post('/api/v1/client-errors', {
    message: e?.message || 'Card back save failed',
    stack: e?.stack,
    fatal: false,
    route: window.location.pathname,
    extra: { feature: 'card-back-editor', card_template_id: props.template.id, status: e?.response?.status },
  }).catch(() => {});
};

// One element back to its defaults: position, size, text, colour, visibility
// (and, for the logo, the uploaded image). The others are left alone.
const resetElement = (key) => {
  const label = elements.find((e) => e.key === key)?.label || key;
  if (!window.confirm(`Reset the ${label.toLowerCase()} to its defaults?`)) return;
  Object.assign(cfg[key], clone(props.backDefaults[key]));
  if (key === 'logo') clearFile('back_logo');
};

const resetToDefaults = () => {
  if (!window.confirm('Reset the texts, colours and visibility to the defaults? Uploads are kept.')) return;
  const enabled = cfg.enabled;
  Object.assign(cfg, clone(props.backDefaults), { enabled });
};

const save = async ({ stay = false } = {}) => {
  if (saving.value) return;
  saving.value = true;
  error.value = '';
  saved.value = false;
  try {
    const body = new FormData();
    body.append('settings', JSON.stringify(cfg));
    ['back_image', 'back_logo'].forEach((k) => {
      if (files[k]) body.append(k, files[k]);
      if (removed[k]) body.append(`remove_${k}`, '1');
    });
    const { data } = await axios.post(route('admin.card-templates.back', props.template.id), body);
    const fresh = data.data;
    snapshot.value = JSON.stringify(cfg);
    files.back_image = null; files.back_logo = null;
    removed.back_image = false; removed.back_logo = false;
    saved.value = true;
    savedAt.value = new Date().toLocaleTimeString();
    if (stay) router.reload({ only: ['template'], preserveState: true });
    else router.visit(route('admin.card-templates.index'));
  } catch (e) {
    reportError(e);
    error.value = e?.response?.data?.message || 'Could not save the back side.';
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
.live-flip { transform-style: preserve-3d; transition: transform 0.6s ease; }
.live-flip.flipped { transform: rotateY(180deg); }
.live-face { position: absolute; inset: 0; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
.live-back { transform: rotateY(180deg); }
</style>
