<template>
  <AppLayout>
    <div class="cg-shell">
      <div class="cg-container">
        <div class="cg-form-panel">
          <h2>{{ t.title || 'Card Details' }}</h2>

          <div class="cg-field">
            <label class="cg-label-row">
              <span>{{ t.minimal_design || 'Minimal Design' }}</span>
              <button type="button" class="cg-switch" :class="{ 'is-on': minimalMode }" @click="toggleMinimal" :aria-pressed="minimalMode">
                <span class="cg-switch-knob"></span>
              </button>
            </label>
            <div class="cg-help">{{ t.minimal_help || 'Partner + QR + Membership code only' }}</div>
          </div>

          <div class="cg-field">
            <label>{{ t.partner || 'Partner' }}</label>
            <select v-model="partnerId" @change="onPartnerChange" :class="{ 'is-changed': isChanged('partner') }">
              <option value="">{{ t.none || '— None —' }}</option>
              <option v-for="p in partners" :key="p.id" :value="String(p.id)">{{ p.title }}</option>
            </select>
            <div v-if="isChanged('partner')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('partner') ? (partners.find(p => String(p.id) === oldValue('partner'))?.title || oldValue('partner')) : t.none || 'None' }}</span>
              <button class="cg-reset-btn" @click="resetField('partner')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('partner')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field" v-if="!minimalMode">
            <label>{{ t.full_name || 'Full Name' }}</label>
            <input type="text" v-model="form.name" dir="rtl" placeholder="رضا عبده منصور" :class="{ 'is-changed': isChanged('name') }" />
            <div v-if="isChanged('name')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('name') }}</span>
              <button class="cg-reset-btn" @click="resetField('name')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('name')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field">
            <label>{{ minimalMode ? (t.membership_code || 'Membership Code') : (t.policy_no || 'Policy No') }}</label>
            <input type="text" v-model="form.policy" placeholder="P 1181" :class="{ 'is-changed': isChanged('policy') }" />
            <div v-if="isChanged('policy')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('policy') }}</span>
              <button class="cg-reset-btn" @click="resetField('policy')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('policy')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field" v-if="!minimalMode">
            <label class="cg-label-row">
              <span>{{ t.member || 'Member' }}</span>
              <span v-if="hasMemberLang" class="cg-lang-row">
                <button type="button" :class="['cg-lang-btn', memberLang==='ar' && 'is-active']" @click="setLang('member','ar')">{{ t.ar || 'AR' }}</button>
                <button type="button" :class="['cg-lang-btn', memberLang==='en' && 'is-active']" @click="setLang('member','en')">{{ t.en || 'EN' }}</button>
              </span>
            </label>
            <input type="text" v-model="form.member" :dir="memberLang==='ar'?'rtl':'ltr'" placeholder="أخصائية مشتريات" :class="{ 'is-changed': isChanged('member') }" />
            <div v-if="isChanged('member')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('member') }}</span>
              <button class="cg-reset-btn" @click="resetField('member')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('member')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field" v-if="!minimalMode">
            <label class="cg-label-row">
              <span>{{ t.status || 'Status' }}</span>
              <span v-if="hasStatusLang" class="cg-lang-row">
                <button type="button" :class="['cg-lang-btn', statusLang==='ar' && 'is-active']" @click="setLang('status','ar')">{{ t.ar || 'AR' }}</button>
                <button type="button" :class="['cg-lang-btn', statusLang==='en' && 'is-active']" @click="setLang('status','en')">{{ t.en || 'EN' }}</button>
              </span>
            </label>
            <input type="text" v-model="form.status" :dir="statusLang==='ar'?'rtl':'ltr'" placeholder="تاون فيو للمقاولات العامة" :class="{ 'is-changed': isChanged('status') }" />
            <div v-if="isChanged('status')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('status') }}</span>
              <button class="cg-reset-btn" @click="resetField('status')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('status')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field" v-if="!minimalMode">
            <label>{{ t.valid_to || 'Valid to' }}</label>
            <input type="text" v-model="form.valid" placeholder="1 / 2027" :class="{ 'is-changed': isChanged('valid') }" />
            <div v-if="isChanged('valid')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('valid') }}</span>
              <button class="cg-reset-btn" @click="resetField('valid')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('valid')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field">
            <label>{{ t.url_qr || 'URL for QR Code' }}</label>
            <input type="text" v-model="form.url" placeholder="https://ash-healthcare.com/member/P1181" :class="{ 'is-changed': isChanged('url') }" />
            <div v-if="isChanged('url')" class="cg-field-diff">
              <span class="cg-old-value">{{ t.old_value || 'old' }}: {{ oldValue('url') }}</span>
              <button class="cg-reset-btn" @click="resetField('url')" title="Reset">↩</button>
              <button class="cg-save-btn" @click="saveField('url')" title="Save">✓</button>
            </div>
          </div>

          <div class="cg-field" v-if="!minimalMode">
            <label>{{ t.photo || 'Photo' }}</label>
            <div class="cg-photo-wrap">
              <div class="cg-photo-preview">
                <img v-if="photoPreviewUrl" :src="photoPreviewUrl" alt="" />
                <span v-else>+</span>
              </div>
              <div>
                <label class="cg-upload-label" for="cgPhotoInput">{{ t.choose_photo || 'Choose Photo' }}</label>
                <input id="cgPhotoInput" type="file" accept="image/*" style="display:none" @change="onPhotoFile" />
                <div class="cg-help">{{ t.photo_supported || 'JPG, PNG, AVIF supported' }}</div>
              </div>
            </div>
            <div v-if="isChanged('avatar')" class="cg-diff-row">
              <img v-if="oldValue('avatar')" :src="oldValue('avatar')" class="old-avatar-thumb" alt="Old photo" />
              <span class="cg-old-value">{{ t.old_photo || 'old photo' }}</span>
              <button class="cg-reset-btn" @click="resetField('avatar')" title="Reset">↩</button>
            </div>
          </div>

          <button class="cg-btn" @click="generateCard">&#9654; {{ t.generate_card || 'Generate Card' }}</button>
          <button v-if="showDownload" class="cg-btn-dl" @click="downloadCard">&#11015; {{ t.download_card || 'Download Card Image' }}</button>
          <button class="cg-btn-back" @click="resetLayout" style="margin-top:10px">&#8634; {{ t.reset_layout || 'Reset Layout' }}</button>
          <button class="cg-btn-back" @click="resetToDefault">&#9851; {{ t.reset_default || 'Reset to Default' }}</button>
          <div class="cg-hint">{{ t.click_hint || 'Click any element on the card to select. Drag to move · scroll wheel to resize' }}</div>
          <button class="cg-btn-back" @click="goBack">&#8592; {{ t.back || 'Back' }}</button>
        </div>

        <div class="cg-card-panel">
          <h2>{{ t.preview || 'Preview' }}</h2>
          <canvas ref="canvasRef" width="1063" height="650"></canvas>
          <p v-if="saveStatus" class="cg-save-status">{{ saveStatus }}</p>

          <div class="cg-layout-controls">
            <h3>{{ t.layout || 'Layout' }}</h3>
            <div class="cg-layout-grid cg-layout-head">
              <span>{{ t.element || 'Element' }}</span><span>{{ t.layout_x || 'X' }}</span><span>{{ t.layout_y || 'Y' }}</span><span>{{ t.layout_scale || 'Scale' }}</span><span>{{ t.layout_color || 'Color' }}</span>
            </div>
            <div
              v-for="item in layoutItems"
              :key="item.key"
              class="cg-layout-grid cg-layout-row"
              :class="{ 'is-selected': state.selected === item.key }"
            >
              <button type="button" class="cg-layout-label" @click="selectItem(item.key)">{{ getLayoutLabel(item) }}</button>
              <input type="number" step="1" :value="getLayoutValue(item.key, 'x')" @input="setLayoutValue(item.key, 'x', $event.target.value)" />
              <input type="number" step="1" :value="getLayoutValue(item.key, 'y')" @input="setLayoutValue(item.key, 'y', $event.target.value)" />
              <input type="number" step="0.05" min="0.1" max="5" :value="getLayoutValue(item.key, 'scale')" @input="setLayoutValue(item.key, 'scale', $event.target.value)" />
              <input
                v-if="item.key === 'name' || item.key === 'fields'"
                type="color"
                class="cg-color-input"
                :value="getColor(item.key)"
                @input="setColor(item.key, $event.target.value)"
              />
              <span v-else class="cg-color-na">—</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { AppLayout } from "@/Pages/Admin/Layout/Layout.js";
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";

const page = usePage();
const t = computed(() => page.props.translations?.admin?.card_generator || {});

const props = defineProps({
  partners: { type: Array, default: () => [] },
  initial: { type: Object, default: () => ({}) },
});

const CW = 1063, CH = 650;

const canvasRef = ref(null);
const partnerId = ref(String(props.initial.partner || ''));
const photoPreviewUrl = ref('');
const showDownload = ref(false);
const saveStatus = ref('');

const form = reactive({
  name: props.initial.name || '',
  policy: props.initial.policy || '',
  member: props.initial.member || props.initial.member_ar || props.initial.member_en || '',
  status: props.initial.status || props.initial.status_ar || props.initial.status_en || '',
  valid: props.initial.valid || '',
  url: props.initial.url || '',
});

const original = reactive({
  name: props.initial.name || '',
  policy: props.initial.policy || '',
  member_ar: props.initial.member_ar || props.initial.member || '',
  member_en: props.initial.member_en || props.initial.member || '',
  member_cur: props.initial.member || props.initial.member_ar || props.initial.member_en || '',
  status_ar: props.initial.status_ar || props.initial.status || '',
  status_en: props.initial.status_en || props.initial.status || '',
  status_cur: props.initial.status || props.initial.status_ar || props.initial.status_en || '',
  valid: props.initial.valid || '',
  url: props.initial.url || '',
  partner: String(props.initial.partner || ''),
  avatar: props.initial.avatar || '',
});

function isChanged(field) {
  switch (field) {
    case 'name': return form.name !== original.name;
    case 'policy': return form.policy !== original.policy;
    case 'member': return memberLang.value === 'ar' ? form.member !== original.member_ar : form.member !== original.member_en;
    case 'status': return statusLang.value === 'ar' ? form.status !== original.status_ar : form.status !== original.status_en;
    case 'valid': return form.valid !== original.valid;
    case 'url': return form.url !== original.url;
    case 'partner': return partnerId.value !== original.partner;
    case 'avatar': return photoPreviewUrl.value && photoPreviewUrl.value !== original.avatar;
    default: return false;
  }
}

function oldValue(field) {
  switch (field) {
    case 'member': return memberLang.value === 'ar' ? original.member_ar : original.member_en;
    case 'status': return statusLang.value === 'ar' ? original.status_ar : original.status_en;
    default: return original[field] || '';
  }
}

function resetField(field) {
  switch (field) {
    case 'name': form.name = original.name; break;
    case 'policy': form.policy = original.policy; break;
    case 'member':
      if (memberLang.value === 'ar') {
        form.member = original.member_ar;
        langData.member.ar = original.member_ar;
      } else {
        form.member = original.member_en;
        langData.member.en = original.member_en;
      }
      break;
    case 'status':
      if (statusLang.value === 'ar') {
        form.status = original.status_ar;
        langData.status.ar = original.status_ar;
      } else {
        form.status = original.status_en;
        langData.status.en = original.status_en;
      }
      break;
    case 'valid': form.valid = original.valid; break;
    case 'url': form.url = original.url; break;
    case 'partner':
      partnerId.value = original.partner;
      onPartnerChange();
      break;
    case 'avatar':
      photoPreviewUrl.value = original.avatar;
      photoImg = null;
      loadInitialAvatar();
      break;
  }
  generateCard();
}

async function saveField(field) {
  saveStatus.value = t.value.saving || 'Saving…';
  const payload = {};

  switch (field) {
    case 'name': payload.name = form.name; break;
    case 'policy': payload.policy = form.policy; break;
    case 'member':
      if (memberLang.value === 'ar' && form.member !== original.member_ar) {
        payload.member_ar = form.member;
      } else if (memberLang.value === 'en' && form.member !== original.member_en) {
        payload.member_en = form.member;
      }
      break;
    case 'status':
      if (statusLang.value === 'ar' && form.status !== original.status_ar) {
        payload.status_ar = form.status;
      } else if (statusLang.value === 'en' && form.status !== original.status_en) {
        payload.status_en = form.status;
      }
      break;
    case 'valid': payload.valid = form.valid; break;
    case 'url': payload.url = form.url; break;
    case 'partner': payload.partner = Number(partnerId.value); break;
    default: break;
  }

  if (Object.keys(payload).length === 0) {
    saveStatus.value = t.value.no_changes || 'No changes to save.';
    setTimeout(() => { saveStatus.value = ''; }, 2000);
    return;
  }

  try {
    const lookupKey = original.policy;
    if (!lookupKey) {
      saveStatus.value = t.value.no_membership || 'No membership number to update.';
      setTimeout(() => { saveStatus.value = ''; }, 2000);
      return;
    }
    payload._lookup = lookupKey;
    await window.axios.patch(`/api/memberships/${encodeURIComponent(lookupKey)}/card-data`, payload);

    switch (field) {
      case 'name': original.name = form.name; break;
      case 'policy': original.policy = form.policy; break;
      case 'member':
        if (memberLang.value === 'ar') original.member_ar = form.member;
        else original.member_en = form.member;
        break;
      case 'status':
        if (statusLang.value === 'ar') original.status_ar = form.status;
        else original.status_en = form.status;
        break;
      case 'valid': original.valid = form.valid; break;
      case 'url': original.url = form.url; break;
      case 'partner': original.partner = partnerId.value; break;
    }

    saveStatus.value = t.value.saved || 'Saved.';
    setTimeout(() => { saveStatus.value = ''; }, 2000);
  } catch (e) {
    saveStatus.value = t.value.save_failed || 'Failed to save.';
    setTimeout(() => { saveStatus.value = ''; }, 3000);
  }
}

const langData = reactive({
  member: { ar: props.initial.member_ar || '', en: props.initial.member_en || '' },
  status: { ar: props.initial.status_ar || '', en: props.initial.status_en || '' },
});
const memberLang = ref((props.initial.member_ar ? 'ar' : (props.initial.member_en ? 'en' : 'ar')));
const statusLang = ref((props.initial.status_ar ? 'ar' : (props.initial.status_en ? 'en' : 'ar')));

const hasMemberLang = computed(() => !!(langData.member.ar || langData.member.en));
const hasStatusLang = computed(() => !!(langData.status.ar || langData.status.en));

function setLang(field, lang) {
  if (field === 'member') {
    langData.member[memberLang.value] = form.member;
    form.member = langData.member[lang] || '';
    memberLang.value = lang;
  } else {
    langData.status[statusLang.value] = form.status;
    form.status = langData.status[lang] || '';
    statusLang.value = lang;
  }
}

const minimalMode = ref(false);

const BG_VERSION = "20260516b";
const BG_FULL = `/card-template_pure.jpg?v=${BG_VERSION}`;
const BG_MINIMAL = `/card-template_white.jpg?v=${BG_VERSION}`;

const bgImg = new Image();
let bgReady = false;
let bgInitialized = false;
bgImg.onload = () => {
  bgReady = true;
  if (!bgInitialized) {
    bgInitialized = true;
    drawBase();
    if (autoGenerate && !hasInitialAvatar) generateCard();
  } else {
    generateCard();
  }
};
bgImg.onerror = () => { bgReady = true; drawBase(); };
bgImg.src = BG_FULL;

const MINIMAL_CACHE_KEY = 'cg.minimalLayout.v1';
const MINIMAL_KEYS = ['partner', 'qr', 'fields'];
const COLOR_CACHE_KEY = 'cg.colors.v1';

function saveColorCache() {
  try {
    localStorage.setItem(COLOR_CACHE_KEY, JSON.stringify(state.colors));
  } catch {}
}
function loadColorCache() {
  try {
    const raw = localStorage.getItem(COLOR_CACHE_KEY);
    if (!raw) return;
    const data = JSON.parse(raw);
    ['full', 'minimal'].forEach(m => {
      if (data[m]) {
        COLOR_KEYS.forEach(k => {
          if (typeof data[m][k] === 'string') state.colors[m][k] = data[m][k];
        });
      }
    });
  } catch {}
}
function clearColorCache(mode) {
  COLOR_KEYS.forEach(k => { state.colors[mode][k] = null; });
  saveColorCache();
}

function loadMinimalCache() {
  try {
    const raw = localStorage.getItem(MINIMAL_CACHE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch { return null; }
}
function saveMinimalCache() {
  try {
    const data = {};
    MINIMAL_KEYS.forEach(k => {
      if (state.overrides[k]) data[k] = { ...state.overrides[k] };
    });
    if (Object.keys(data).length) localStorage.setItem(MINIMAL_CACHE_KEY, JSON.stringify(data));
    else localStorage.removeItem(MINIMAL_CACHE_KEY);
  } catch {}
}
function clearMinimalCache() {
  try { localStorage.removeItem(MINIMAL_CACHE_KEY); } catch {}
}
function applyMinimalCache() {
  const cached = loadMinimalCache();
  if (!cached) return;
  MINIMAL_KEYS.forEach(k => {
    if (cached[k]) state.overrides[k] = { ...cached[k] };
  });
}

function toggleMinimal() {
  minimalMode.value = !minimalMode.value;
  state.overrides = { name:null, photo:null, fields:null, qr:null, partner:null };
  state.selected = null;
  if (minimalMode.value) applyMinimalCache();
  bgReady = false;
  bgImg.src = minimalMode.value ? BG_MINIMAL : BG_FULL;
}

function resetToDefault() {
  state.overrides = { name:null, photo:null, fields:null, qr:null, partner:null };
  state.selected = null;
  const mode = minimalMode.value ? 'minimal' : 'full';
  clearColorCache(mode);
  if (minimalMode.value) clearMinimalCache();
  paintCard();
  saveStatus.value = t.value.restored_defaults || 'Restored built-in defaults.';
  setTimeout(() => { saveStatus.value = ''; }, 2000);
}

let photoImg = null;
let partnerImg = null;
let qrCanvas = null;

const autoGenerate = !!(props.initial.name || props.initial.policy);
const hasInitialAvatar = !!props.initial.avatar;

const defaultLayoutFull = {
  name:    { x:796, y:337, scale:1 },
  photo:   { x:690, y:94,  scale:1 },
  fields:  { x:572, y:413, scale:1 },
  qr:      { x:376, y:410, scale:0.65 },
  partner: { x:-87, y:361, scale:2.65 },
};
const defaultLayoutEmpty = {
  name:    { x:796, y:337, scale:1 },
  photo:   { x:690, y:94,  scale:1 },
  fields:  { x:779, y:442, scale:1.1 },
  qr:      { x:376, y:410, scale:0.65 },
  partner: { x:95,  y:405, scale:1.16 },
};
const defaultLayoutMinimal = {
  name:    { x:531, y:540, scale:1 },
  photo:   { x:443, y:75,  scale:1 },
  fields:  { x:724, y:368, scale:1.4 },
  qr:      { x:704, y:107, scale:0.6 },
  partner: { x:-165, y:313, scale:2.65 },
};
function getDefaultFor(key) {
  if (minimalMode.value) {
    return { ...defaultLayoutMinimal[key] };
  }
  const em = !form.member && !form.status;
  const d = em ? defaultLayoutEmpty : defaultLayoutFull;
  if (key === 'partner') return partnerDefaultLayout();
  return { x:d[key].x, y:d[key].y, scale:d[key].scale };
}
function partnerDefaultLayout() {
  const p = currentPartner();
  const em = !form.member && !form.status;
  const fallback = em ? defaultLayoutEmpty.partner : defaultLayoutFull.partner;
  if (p && p.card_x != null && p.card_y != null) {
    return { x: Number(p.card_x), y: Number(p.card_y), scale: p.card_scale != null ? Number(p.card_scale) : fallback.scale };
  }
  return { ...fallback };
}
function getLayoutFor(key) {
  return state.overrides[key] || getDefaultFor(key);
}
function ensureOverride(key) {
  if (!state.overrides[key]) state.overrides[key] = getDefaultFor(key);
  return state.overrides[key];
}

const state = reactive({
  overrides: { name:null, photo:null, fields:null, qr:null, partner:null },
  colors: {
    full:    { name: null, fields: null },
    minimal: { name: null, fields: null },
  },
  selected: null,
});
let bounds = {};

const defaultColors = {
  full:    { name: '#000000', fields: '#000000' },
  minimal: { name: '#1a1a2e', fields: '#1a1a2e' },
};
const COLOR_KEYS = ['name', 'fields'];

function getColor(key) {
  const mode = minimalMode.value ? 'minimal' : 'full';
  return state.colors[mode][key] || defaultColors[mode][key];
}
function setColor(key, value) {
  if (!value) return;
  const mode = minimalMode.value ? 'minimal' : 'full';
  state.colors[mode][key] = value;
  saveColorCache();
  paintCard();
}

watch(() => state.overrides, () => {
  if (minimalMode.value) saveMinimalCache();
}, { deep: true });

const allLayoutItems = [
  { key: 'partner', labelKey: 'partner_logo' },
  { key: 'photo',   labelKey: 'photo_label' },
  { key: 'name',    labelKey: 'name_label' },
  { key: 'fields',  labelKey: 'text_fields' },
  { key: 'qr',      labelKey: 'qr_code' },
];
function getLayoutLabel(item) {
  return t.value[item.labelKey] || item.key;
}
const layoutItems = computed(() => {
  if (minimalMode.value) {
    return allLayoutItems.filter(it => ['partner', 'qr', 'fields'].includes(it.key));
  }
  return allLayoutItems;
});

function getLayoutValue(key, field) {
  const l = getLayoutFor(key);
  const n = l && typeof l[field] === 'number' ? l[field] : 0;
  return field === 'scale' ? Math.round(n * 100) / 100 : Math.round(n);
}
function setLayoutValue(key, field, value) {
  const num = Number(value);
  if (!isFinite(num)) return;
  const ov = ensureOverride(key);
  ov[field] = field === 'scale' ? Math.max(0.1, Math.min(5, num)) : num;
  paintCard();
}
function selectItem(key) {
  state.selected = state.selected === key ? null : key;
  paintCard();
}

function currentPartner() {
  return props.partners.find(p => String(p.id) === String(partnerId.value)) || null;
}

function loadInitialAvatar() {
  if (!props.initial.avatar) return;
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = () => {
    photoImg = img;
    photoPreviewUrl.value = props.initial.avatar;
    if (bgReady) generateCard();
  };
  img.onerror = () => { photoImg = null; if (bgReady) generateCard(); };
  const avatarUrl = props.initial.avatar.includes('?')
    ? props.initial.avatar + '&_cb=' + Date.now()
    : props.initial.avatar + '?_cb=' + Date.now();
  img.src = avatarUrl;
}

function onPartnerChange() {
  state.overrides.partner = null;
  const p = currentPartner();
  if (!p || !p.image) {
    partnerImg = null;
    if (bgReady) paintCard();
    return;
  }
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = () => { partnerImg = img; if (bgReady) paintCard(); };
  img.onerror = () => { partnerImg = null; if (bgReady) paintCard(); };
  img.src = p.image;
}

function onPhotoFile(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = new Image();
    img.onload = () => {
      photoImg = img;
      photoPreviewUrl.value = ev.target.result;
      if (bgReady) paintCard();
    };
    img.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function getCtx() {
  return canvasRef.value.getContext('2d', { colorSpace: 'srgb', willReadFrequently: false });
}
function drawBase() {
  const ctx = getCtx();
  if (!ctx) return;
  ctx.drawImage(bgImg, 0, 0, CW, CH);
}

function paintMinimal() {
  const ctx = getCtx();
  if (!ctx) return;
  ctx.drawImage(bgImg, 0, 0, CW, CH);
  bounds = {};

  if (partnerImg) {
    const prl = getLayoutFor('partner');
    const maxW = 260 * prl.scale, maxH = 260 * prl.scale;
    const ar = partnerImg.width / partnerImg.height;
    let dw = maxW, dh = maxH;
    if (ar > 1) dh = dw / ar; else dw = dh * ar;
    ctx.drawImage(partnerImg, prl.x, prl.y, dw, dh);
    bounds.partner = { x1: prl.x, y1: prl.y, x2: prl.x + dw, y2: prl.y + dh };
  }

  if (qrCanvas) {
    const ql = getLayoutFor('qr');
    const qs = 190 * ql.scale, pad = 14 * ql.scale;
    ctx.fillStyle = "#fff";
    ctx.fillRect(ql.x - pad, ql.y - pad, qs + pad * 2, qs + pad * 2);
    ctx.drawImage(qrCanvas, ql.x, ql.y, qs, qs);
    bounds.qr = { x1: ql.x - pad, y1: ql.y - pad, x2: ql.x + qs + pad, y2: ql.y + qs + pad };
  }

  if (form.policy) {
    const fl = getLayoutFor('fields');
    const fSize = 34 * fl.scale;
    ctx.font = "bold " + fSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor('fields'); ctx.textAlign = "center"; ctx.direction = "ltr";
    ctx.fillText(form.policy, fl.x, fl.y);
    const w = ctx.measureText(form.policy).width;
    bounds.fields = { x1: fl.x - w/2 - 8, y1: fl.y - fSize - 4, x2: fl.x + w/2 + 8, y2: fl.y + 8 };
  }

  if (state.selected && bounds[state.selected]) {
    const b = bounds[state.selected];
    ctx.save();
    ctx.strokeStyle = "#D4AF6E"; ctx.lineWidth = 2; ctx.setLineDash([6, 4]);
    ctx.strokeRect(b.x1, b.y1, b.x2 - b.x1, b.y2 - b.y1);
    ctx.restore();
  }
}

function paintCard() {
  if (minimalMode.value) return paintMinimal();
  const ctx = getCtx();
  if (!ctx) return;
  ctx.drawImage(bgImg, 0, 0, CW, CH);
  bounds = {};

  const emptyMode = !form.member && !form.status;

  if (partnerImg) {
    const prl = getLayoutFor('partner');
    const maxW = 170 * prl.scale, maxH = 170 * prl.scale;
    const ar = partnerImg.width / partnerImg.height;
    let dw = maxW, dh = maxH;
    if (ar > 1) dh = dw / ar; else dw = dh * ar;
    ctx.drawImage(partnerImg, prl.x, prl.y, dw, dh);
    bounds.partner = { x1: prl.x, y1: prl.y, x2: prl.x + dw, y2: prl.y + dh };
  }

  if (photoImg) {
    const pl = getLayoutFor('photo');
    const pw = 178 * pl.scale, ph = 178 * pl.scale;
    const px = pl.x, py = pl.y;
    ctx.save();
    const r = 10 * pl.scale;
    ctx.beginPath();
    ctx.moveTo(px+r,py); ctx.lineTo(px+pw-r,py);
    ctx.quadraticCurveTo(px+pw,py,px+pw,py+r);
    ctx.lineTo(px+pw,py+ph-r);
    ctx.quadraticCurveTo(px+pw,py+ph,px+pw-r,py+ph);
    ctx.lineTo(px+r,py+ph);
    ctx.quadraticCurveTo(px,py+ph,px,py+ph-r);
    ctx.lineTo(px,py+r);
    ctx.quadraticCurveTo(px,py,px+r,py);
    ctx.closePath(); ctx.clip();
    const ar = photoImg.width/photoImg.height, tar = pw/ph;
    let sx=0, sy=0, sw=photoImg.width, sh=photoImg.height;
    if (ar > tar) { sw = sh * tar; sx = (photoImg.width - sw)/2; }
    else { sh = sw / tar; sy = (photoImg.height - sh)/2; }
    ctx.drawImage(photoImg, sx, sy, sw, sh, px, py, pw, ph);
    ctx.restore();
    bounds.photo = { x1: px, y1: py, x2: px + pw, y2: py + ph };
  }

  const nl = getLayoutFor('name');
  const nameFontSize = (emptyMode ? 46 : 34) * nl.scale;
  const lineYOffset = (emptyMode ? 15 : 30) * nl.scale;
  const nx = nl.x, ny = nl.y;
  if (form.name) {
    ctx.textAlign = "center"; ctx.direction = "rtl";
    ctx.font = "bold " + nameFontSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor('name');
    ctx.fillText(form.name, nx, ny);
    const nw = ctx.measureText(form.name).width;
    ctx.strokeStyle = "rgba(196,160,88,0.4)"; ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(nx - 175 * nl.scale, ny + lineYOffset);
    ctx.lineTo(nx + 175 * nl.scale, ny + lineYOffset);
    ctx.stroke();
    bounds.name = { x1: nx - nw/2 - 12, y1: ny - nameFontSize - 4, x2: nx + nw/2 + 12, y2: ny + lineYOffset + 8 };
  }

  const fl = getLayoutFor('fields');
  if (emptyMode) {
    const items = [
      { label: "Policy no / ", val: form.policy },
      { label: "Valid to / ", val: form.valid },
    ].filter(f => !!f.val);
    const fSize = 34 * fl.scale;
    ctx.font = "bold " + fSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor('fields'); ctx.textAlign = "center"; ctx.direction = "ltr";
    const lineH = 75 * fl.scale;
    let bx1=Infinity, bx2=-Infinity, by1=Infinity, by2=-Infinity;
    items.forEach((f,i) => {
      const full = f.label + f.val, y = fl.y + i * lineH;
      ctx.fillText(full, fl.x, y);
      const w = ctx.measureText(full).width;
      bx1 = Math.min(bx1, fl.x - w/2); bx2 = Math.max(bx2, fl.x + w/2);
      by1 = Math.min(by1, y - fSize); by2 = Math.max(by2, y + 8);
    });
    if (items.length) bounds.fields = { x1: bx1 - 8, y1: by1 - 4, x2: bx2 + 8, y2: by2 + 4 };
  } else {
    const items = [
      { label: "Policy no / ", val: form.policy },
      { label: "Member / ", val: form.member },
      { label: "Status / ", val: form.status },
      { label: "Valid to / ", val: form.valid },
    ].filter(f => !!f.val);
    const fSize = 32 * fl.scale;
    ctx.font = fSize + "px Tajawal,Arial";
    ctx.fillStyle = getColor('fields'); ctx.textAlign = "left"; ctx.direction = "ltr";
    const lineH = 47 * fl.scale;
    let maxW = 0;
    items.forEach((f,i) => {
      const t = f.label + f.val;
      ctx.fillText(t, fl.x, fl.y + i * lineH);
      const w = ctx.measureText(t).width;
      if (w > maxW) maxW = w;
    });
    if (items.length) bounds.fields = { x1: fl.x - 4, y1: fl.y - fSize - 4, x2: fl.x + maxW + 8, y2: fl.y + (items.length - 1) * lineH + 8 };
  }

  if (qrCanvas) {
    const ql = getLayoutFor('qr');
    const qs = 190 * ql.scale, pad = 14 * ql.scale;
    ctx.fillStyle = "#fff";
    ctx.fillRect(ql.x - pad, ql.y - pad, qs + pad * 2, qs + pad * 2);
    ctx.drawImage(qrCanvas, ql.x, ql.y, qs, qs);
    bounds.qr = { x1: ql.x - pad, y1: ql.y - pad, x2: ql.x + qs + pad, y2: ql.y + qs + pad };
  }

  if (state.selected && bounds[state.selected]) {
    const b = bounds[state.selected];
    ctx.save();
    ctx.strokeStyle = "#D4AF6E"; ctx.lineWidth = 2; ctx.setLineDash([6, 4]);
    ctx.strokeRect(b.x1, b.y1, b.x2 - b.x1, b.y2 - b.y1);
    ctx.restore();
  }
}

async function generateCard() {
  const qrData = form.url || [form.policy, form.name, form.member, form.status, form.valid].filter(Boolean).join(" | ") || "ASH Health Care";
  try {
    const off = document.createElement('canvas');
    off.width = 172; off.height = 172;
    await QRCode.toCanvas(off, qrData, { width: 172, margin: 0, color: { dark: '#000000', light: '#ffffff' }, errorCorrectionLevel: 'M' });
    qrCanvas = off;
  } catch (e) {
    qrCanvas = null;
  }
  paintCard();
  showDownload.value = true;
  savePartnerLayoutIfNeeded();
  saveCardLayoutAndImage();
}

async function saveCardLayoutAndImage() {
  if (!form.policy) return;
  try {
    saveStatus.value = t.value.saving_card || 'Saving card…';
    const payload = {
      mode: minimalMode.value ? 'minimal' : 'full',
      partner_id: partnerId.value ? Number(partnerId.value) : null,
      partner_x: getLayoutValue('partner', 'x'),
      partner_y: getLayoutValue('partner', 'y'),
      partner_scale: getLayoutValue('partner', 'scale'),
      photo_x: getLayoutValue('photo', 'x'),
      photo_y: getLayoutValue('photo', 'y'),
      photo_scale: getLayoutValue('photo', 'scale'),
      name_x: getLayoutValue('name', 'x'),
      name_y: getLayoutValue('name', 'y'),
      name_scale: getLayoutValue('name', 'scale'),
      name_color: getColor('name'),
      fields_x: getLayoutValue('fields', 'x'),
      fields_y: getLayoutValue('fields', 'y'),
      fields_scale: getLayoutValue('fields', 'scale'),
      fields_color: getColor('fields'),
      qr_x: getLayoutValue('qr', 'x'),
      qr_y: getLayoutValue('qr', 'y'),
      qr_scale: getLayoutValue('qr', 'scale'),
      image: canvasRef.value.toDataURL('image/png'),
    };
    await window.axios.post(`/api/memberships/${encodeURIComponent(form.policy)}/card-layout`, payload);
    saveStatus.value = t.value.saved_card || 'Card saved.';
    setTimeout(() => { saveStatus.value = ''; }, 2000);
  } catch (e) {
    saveStatus.value = t.value.save_card_failed || 'Failed to save card.';
    setTimeout(() => { saveStatus.value = ''; }, 3000);
  }
}

async function savePartnerLayoutIfNeeded() {
  if (minimalMode.value) return;
  const p = currentPartner();
  if (!p) return;
  const ov = state.overrides.partner;
  if (!ov) return;
  try {
    saveStatus.value = t.value.saving_layout || 'Saving partner layout…';
    await window.axios.patch(`/api/partners/${p.id}/card-layout`, {
      card_x: ov.x,
      card_y: ov.y,
      card_scale: ov.scale,
    });
    p.card_x = ov.x; p.card_y = ov.y; p.card_scale = ov.scale;
    saveStatus.value = t.value.saved_layout || 'Saved partner layout as default.';
    setTimeout(() => { saveStatus.value = ''; }, 2500);
  } catch (e) {
    saveStatus.value = t.value.save_layout_failed || 'Failed to save partner layout.';
  }
}

function resetLayout() {
  state.overrides = { name:null, photo:null, fields:null, qr:null, partner:null };
  state.selected = null;
  paintCard();
}

function downloadCard() {
  const c = canvasRef.value;
  const a = document.createElement('a');
  a.download = 'ash_health_card.png';
  a.href = c.toDataURL('image/png');
  a.click();
}

function goBack() {
  if (window.history.length > 1) window.history.back();
}

// drag + scroll-wheel resize
let dragState = { dragging: false, offX: 0, offY: 0, target: null };
function canvasCoords(e) {
  const c = canvasRef.value;
  const rect = c.getBoundingClientRect();
  const cx = e.clientX != null ? e.clientX : (e.touches && e.touches[0] && e.touches[0].clientX);
  const cy = e.clientY != null ? e.clientY : (e.touches && e.touches[0] && e.touches[0].clientY);
  return { x: (cx - rect.left) * (c.width / rect.width), y: (cy - rect.top) * (c.height / rect.height) };
}
function hitTest(p) {
  const keys = ["name", "qr", "photo", "fields", "partner"];
  for (let i = 0; i < keys.length; i++) {
    const k = keys[i], b = bounds[k];
    if (b && p.x >= b.x1 && p.x <= b.x2 && p.y >= b.y1 && p.y <= b.y2) return k;
  }
  return null;
}
function onStart(e) {
  const p = canvasCoords(e);
  const hit = hitTest(p);
  if (!hit) {
    if (state.selected) { state.selected = null; paintCard(); }
    return;
  }
  const ov = ensureOverride(hit);
  dragState.target = hit;
  state.selected = hit;
  dragState.offX = p.x - ov.x;
  dragState.offY = p.y - ov.y;
  dragState.dragging = true;
  canvasRef.value.style.cursor = 'grabbing';
  paintCard();
  e.preventDefault();
}
function onMove(e) {
  const p = canvasCoords(e);
  if (!dragState.dragging) {
    canvasRef.value.style.cursor = hitTest(p) ? 'grab' : 'default';
    return;
  }
  const ov = state.overrides[dragState.target];
  ov.x = p.x - dragState.offX;
  ov.y = p.y - dragState.offY;
  paintCard();
  e.preventDefault();
}
function onEnd() {
  if (dragState.dragging) { dragState.dragging = false; dragState.target = null; canvasRef.value.style.cursor = 'grab'; }
}
function onWheel(e) {
  if (!state.selected) return;
  e.preventDefault();
  const ov = ensureOverride(state.selected);
  const f = e.deltaY < 0 ? 1.05 : 0.95;
  ov.scale = Math.max(0.3, Math.min(3, ov.scale * f));
  paintCard();
}

onMounted(() => {
  const c = canvasRef.value;
  c.addEventListener('mousedown', onStart);
  c.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onEnd);
  c.addEventListener('touchstart', onStart, { passive: false });
  c.addEventListener('touchmove', onMove, { passive: false });
  window.addEventListener('touchend', onEnd);
  c.addEventListener('wheel', onWheel, { passive: false });

  loadColorCache();
  loadInitialAvatar();
  if (partnerId.value) onPartnerChange();
});
onBeforeUnmount(() => {
  const c = canvasRef.value;
  if (!c) return;
  c.removeEventListener('mousedown', onStart);
  c.removeEventListener('mousemove', onMove);
  window.removeEventListener('mouseup', onEnd);
  c.removeEventListener('touchstart', onStart);
  c.removeEventListener('touchmove', onMove);
  window.removeEventListener('touchend', onEnd);
  c.removeEventListener('wheel', onWheel);
});
</script>

<style scoped>
.cg-shell { background:#1a1a2e; min-height:100vh; padding:30px 20px; font-family: Tajawal, Arial, sans-serif; }
.cg-container { display:flex; gap:32px; width:100%; max-width:1220px; margin:0 auto; flex-wrap:wrap; justify-content:center; }
.cg-form-panel { background:#0f3460; border-radius:16px; padding:28px; width:330px; flex-shrink:0; border:1px solid rgba(184,148,90,0.3); }
.cg-form-panel h2 { color:#D4AF6E; font-size:15px; letter-spacing:.1em; text-transform:uppercase; margin-bottom:20px; font-family: 'Cinzel', serif; }
.cg-field { margin-bottom:14px; }
.cg-field label { display:block; font-size:12px; color:#9BA4B5; margin-bottom:5px; }
.cg-field input,
.cg-field select { width:100%; background:#162447; border:1px solid rgba(184,148,90,0.25); border-radius:8px; padding:9px 12px; font-size:14px; color:#e0e0e0; outline:none; transition:border .2s; font-family: inherit; }
.cg-field select { appearance:none; background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23D4AF6E'><path d='M7 10l5 5 5-5z'/></svg>"); background-repeat:no-repeat; background-position:right 8px center; background-size:18px; padding-right:32px; }
.cg-field input:focus,
.cg-field select:focus { border-color:#D4AF6E; }
.cg-label-row { display:flex; align-items:center; justify-content:space-between; }
.cg-lang-row { display:inline-flex; gap:4px; }
.cg-lang-btn { padding:2px 8px; font-size:11px; border-radius:5px; border:1px solid rgba(184,148,90,0.4); background:rgba(184,148,90,0.1); color:#9BA4B5; cursor:pointer; font-family: inherit; }
.cg-lang-btn.is-active { background:rgba(184,148,90,0.3); color:#D4AF6E; border-color:#D4AF6E; }
.cg-switch { position:relative; width:42px; height:22px; border-radius:999px; background:rgba(155,164,181,0.25); border:1px solid rgba(184,148,90,0.3); padding:0; cursor:pointer; transition:background .2s; }
.cg-switch.is-on { background:rgba(184,148,90,0.55); border-color:#D4AF6E; }
.cg-switch-knob { position:absolute; top:2px; left:2px; width:16px; height:16px; border-radius:50%; background:#e0e0e0; transition:left .2s, background .2s; }
.cg-switch.is-on .cg-switch-knob { left:22px; background:#fff; }
.cg-photo-wrap { display:flex; align-items:center; gap:12px; }
.cg-photo-preview { width:60px; height:60px; border-radius:8px; background:#162447; border:1px solid rgba(184,148,90,0.25); overflow:hidden; display:flex; align-items:center; justify-content:center; color:#555; font-size:22px; flex-shrink:0; }
.cg-photo-preview img { width:100%; height:100%; object-fit:cover; }
.cg-upload-label { display:inline-block; padding:8px 14px; background:rgba(184,148,90,0.15); border:1px solid rgba(184,148,90,0.4); color:#D4AF6E; border-radius:8px; cursor:pointer; font-size:13px; }
.cg-help { font-size:11px; color:#666; margin-top:4px; }
.cg-btn { width:100%; margin-top:20px; padding:12px; background:linear-gradient(135deg,#C4A058,#8a6f35); color:#fff; border:none; border-radius:10px; font-size:15px; cursor:pointer; font-family: inherit; }
.cg-btn-dl { width:100%; margin-top:10px; padding:12px; background:rgba(184,148,90,0.15); border:1px solid rgba(184,148,90,0.4); color:#D4AF6E; border-radius:10px; font-size:15px; cursor:pointer; font-family: inherit; }
.cg-btn-back { width:100%; margin-top:10px; padding:12px; background:rgba(155,164,181,0.1); border:1px solid rgba(155,164,181,0.3); color:#9BA4B5; border-radius:10px; font-size:15px; cursor:pointer; font-family: inherit; }
.cg-hint { font-size:11px; color:#9BA4B5; margin-top:8px; text-align:center; line-height:1.5; }
.cg-card-panel { flex:1; min-width:300px; display:flex; flex-direction:column; align-items:center; gap:16px; }
.cg-card-panel h2 { color:#D4AF6E; font-size:13px; letter-spacing:.1em; text-transform:uppercase; font-family: 'Cinzel', serif; }
.cg-card-panel canvas { border-radius:12px; box-shadow:0 8px 40px rgba(0,0,0,0.6); max-width:100%; height:auto; display:block; }
.cg-save-status { font-size:12px; color:#D4AF6E; }
.cg-layout-controls { width:100%; max-width:1063px; background:#0f3460; border:1px solid rgba(184,148,90,0.3); border-radius:12px; padding:16px 18px; }
.cg-layout-controls h3 { color:#D4AF6E; font-size:13px; letter-spacing:.1em; text-transform:uppercase; font-family:'Cinzel', serif; margin:0 0 12px; }
.cg-layout-grid { display:grid; grid-template-columns: 1.4fr 1fr 1fr 1fr 0.7fr; gap:10px; align-items:center; padding:6px 8px; }
.cg-color-input { padding:0; width:34px; height:28px; background:transparent; border:1px solid rgba(184,148,90,0.4); border-radius:6px; cursor:pointer; justify-self:center; }
.cg-color-input::-webkit-color-swatch-wrapper { padding:2px; }
.cg-color-input::-webkit-color-swatch { border:none; border-radius:4px; }
.cg-color-na { text-align:center; color:#555; font-size:14px; justify-self:center; }
.cg-layout-head { font-size:11px; color:#9BA4B5; text-transform:uppercase; letter-spacing:.08em; border-bottom:1px solid rgba(184,148,90,0.2); padding-bottom:8px; margin-bottom:4px; }
.cg-layout-head span:first-child { text-align:left; }
.cg-layout-head span:not(:first-child) { text-align:center; }
.cg-layout-row { border-radius:8px; transition:background .15s; }
.cg-layout-row.is-selected { background:rgba(184,148,90,0.12); outline:1px solid rgba(212,175,110,0.55); }
.cg-layout-label { text-align:left; background:transparent; border:none; color:#e0e0e0; font-size:13px; cursor:pointer; padding:6px 0; font-family:inherit; }
.cg-layout-label:hover { color:#D4AF6E; }
.cg-layout-row input { background:#162447; border:1px solid rgba(184,148,90,0.25); border-radius:6px; padding:6px 8px; font-size:13px; color:#e0e0e0; outline:none; text-align:center; font-family:inherit; width:100%; min-width:0; }
.cg-layout-row input:focus { border-color:#D4AF6E; }
.cg-layout-row input::-webkit-outer-spin-button,
.cg-layout-row input::-webkit-inner-spin-button { opacity:0.6; }
.cg-field-diff { display:flex; align-items:center; gap:6px; margin-top:5px; font-size:11px; }
.cg-old-value { color:#8a8a8a; background:rgba(0,0,0,0.2); padding:2px 8px; border-radius:4px; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cg-reset-btn, .cg-save-btn { flex-shrink:0; width:26px; height:26px; border-radius:6px; border:none; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; line-height:1; }
.cg-reset-btn { background:rgba(155,164,181,0.15); color:#9BA4B5; border:1px solid rgba(155,164,181,0.3); }
.cg-reset-btn:hover { background:rgba(155,164,181,0.3); color:#e0e0e0; }
.cg-save-btn { background:rgba(184,148,90,0.15); color:#D4AF6E; border:1px solid rgba(184,148,90,0.4); }
.cg-save-btn:hover { background:rgba(184,148,90,0.35); color:#fff; }
.cg-field-changed input,
.cg-field-changed select { border-color:rgba(184,148,90,0.55) !important; }
.old-avatar-thumb { width:40px; height:40px; border-radius:6px; border:1px solid rgba(184,148,90,0.3); object-fit:cover; flex-shrink:0; }
.cg-diff-row { display:flex; align-items:center; gap:8px; margin-top:6px; }
</style>
