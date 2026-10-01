<template>
  <!-- "Fix translations with AI": the AI's corrected Arabic + English names,
       one row per tag, each ticked by default. Only the ticked rows are saved. -->
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[110] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @keydown.esc="close"
    >
      <div class="my-8 w-full max-w-3xl overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow mt-0.5">
            <path d="m5 8 6 6"></path><path d="m4 14 6-6 2-3"></path><path d="M2 5h12"></path><path d="M7 2h1"></path>
            <path d="m22 22-5-10-5 10"></path><path d="M14 18h6"></path>
          </svg>
          <div>
            <h3 class="text-sm font-semibold text-white">{{ t.tag?.translations_fix_title || 'Fix tag translations with AI' }}</h3>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ t.tag?.translations_fix_help || 'Tags whose Arabic or English name is missing, in the wrong language or copied. Untick any you want to keep as they are.' }}
            </p>
          </div>
          <button
            type="button"
            class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            :title="t.common?.close || 'Close (Esc)'"
            @click="close"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
            </svg>
          </button>
        </div>

        <div class="max-h-[65vh] overflow-y-auto p-4">
          <!-- Loading -->
          <div v-if="loading" class="flex flex-col items-center gap-3 py-10 text-sm text-muted-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin text-golden-yellow">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            {{ t.tag?.translations_fix_loading || 'Asking AI for corrected names…' }}
          </div>

          <!-- Error -->
          <p v-else-if="error && !rows.length" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">{{ error }}</p>

          <!-- Nothing to fix -->
          <div v-else-if="!rows.length" class="flex flex-col items-center gap-2 py-10 text-sm text-muted-foreground">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-400">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path>
            </svg>
            {{ unresolved
              ? (t.tag?.translations_fix_unresolved_only || 'Some tags still need a fix but the AI gave no usable answer. Try again.')
              : (t.tag?.translations_fix_none || 'Every tag already has a proper Arabic and English name.') }}
          </div>

          <!-- Proposals -->
          <div v-else class="space-y-2">
            <p v-if="error" class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">{{ error }}</p>
            <label class="flex items-center gap-2 text-xs text-muted-foreground">
              <input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)" class="h-4 w-4 accent-[var(--color-golden-yellow,#d4a017)]" />
              {{ t.tag?.translations_fix_select_all || 'Select all' }} ({{ checkedCount }}/{{ rows.length }})
            </label>
            <div
              v-for="row in rows"
              :key="row.id"
              class="grid grid-cols-[auto_1fr] gap-3 rounded-lg border p-3 transition-colors"
              :class="row.checked ? 'border-primary/50 bg-primary/5' : 'border-border opacity-60'"
            >
              <input v-model="row.checked" type="checkbox" class="mt-1 h-4 w-4 accent-[var(--color-golden-yellow,#d4a017)]" />
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 min-w-0">
                <div v-for="code in ['ar', 'en']" :key="code" class="min-w-0">
                  <div class="mb-1 flex items-center gap-1.5 text-[10px] uppercase tracking-wide text-muted-foreground">
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: row.color || '#6B7280' }"></span>
                    <span v-if="row.icon">{{ row.icon }}</span>
                    {{ code === 'ar' ? (t.tag?.arabic || 'Arabic') : (t.tag?.english || 'English') }}
                  </div>
                  <div class="text-xs line-through text-muted-foreground truncate" :dir="code === 'ar' ? 'rtl' : 'ltr'">
                    {{ row.from[code] || '—' }}
                  </div>
                  <input
                    v-model="row.to[code]"
                    type="text"
                    :dir="code === 'ar' ? 'rtl' : 'ltr'"
                    class="mt-1 w-full rounded-md border border-border bg-transparent px-2 py-1 text-sm text-foreground focus:border-ring focus:outline-none focus:ring-[3px] focus:ring-ring/50"
                    :class="row.from[code] === row.to[code] ? '' : 'text-emerald-300'"
                  />
                </div>
              </div>
            </div>
            <p v-if="unresolved" class="text-xs text-amber-400">
              {{ (t.tag?.translations_fix_unresolved || ':count tag(s) still need a fix but got no usable answer — run it again for those.').replace(':count', unresolved) }}
            </p>
          </div>
        </div>

        <div class="flex gap-3 justify-end border-t border-border p-3">
          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background text-white shadow-xs hover:bg-primary hover:text-primary-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2"
            @click="close"
          >
            {{ t.common?.cancel || 'Cancel' }}
          </button>
          <button
            v-if="rows.length"
            type="button"
            :disabled="saving || !checkedCount"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
            @click="apply"
          >
            <svg v-if="saving" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
              <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
            {{ (t.tag?.translations_fix_apply || 'Save :count tag(s)').replace(':count', checkedCount) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useNotification } from '@/composables/useNotification';

const props = defineProps({
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const rows = ref([]);
const unresolved = ref(0);

const checkedCount = computed(() => rows.value.filter(row => row.checked).length);
const allChecked = computed(() => rows.value.length > 0 && checkedCount.value === rows.value.length);
const toggleAll = (value) => rows.value.forEach(row => { row.checked = value; });

const reportError = (message, err, extra = {}) => {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        stack: err?.stack || String(err?.message || err || ''),
        route: window.location.pathname,
        fatal: false,
        extra: { step: 'tag-translation-fix', ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

const load = async () => {
  loading.value = true;
  error.value = '';
  rows.value = [];
  unresolved.value = 0;

  try {
    const { data } = await axios.post(route('admin.tag.translations.preview'));
    rows.value = (data?.proposals || []).map(item => ({ ...item, to: { ...item.to }, checked: true }));
    unresolved.value = data?.unresolved || 0;
  } catch (err) {
    const status = err?.response?.status;
    if (status !== 422) reportError('Tag translation preview failed', err, { status: status ?? null });
    error.value = err?.response?.data?.message
      || (t.value.tag?.translations_fix_failed || 'Could not get translations from the AI. Please try again.');
  } finally {
    loading.value = false;
  }
};

watch(() => props.open, (isOpen) => { if (isOpen) load(); });

const close = () => {
  if (!saving.value) emit('close');
};

const apply = async () => {
  if (saving.value || !checkedCount.value) return;
  saving.value = true;

  try {
    const items = rows.value
      .filter(row => row.checked)
      .map(row => ({ id: row.id, ar: (row.to.ar || '').trim(), en: (row.to.en || '').trim() }));
    const { data } = await axios.post(route('admin.tag.translations.apply'), { items });

    const notify = useNotification();
    notify.success((t.value.tag?.translations_fix_done || ':count tag(s) updated.').replace(':count', data?.updated ?? 0));
    if (data?.skipped) {
      notify.warning((t.value.tag?.translations_fix_skipped || ':count tag(s) skipped: the Arabic must be in Arabic and the English in English.').replace(':count', data.skipped));
    }

    emit('close');
    router.reload({ only: ['tags'] });
  } catch (err) {
    const status = err?.response?.status;
    if (status !== 422) reportError('Tag translation apply failed', err, { status: status ?? null });
    error.value = err?.response?.data?.message
      || (t.value.tag?.translations_fix_save_failed || 'Could not save the translations. Nothing was changed.');
  } finally {
    saving.value = false;
  }
};
</script>
