<template>
  <!-- "Quick add tag" popup on the facility form's Tags card. Creates the tag
       right away (JSON) and hands it back so the form can show it ticked. -->
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[110] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      @keydown.esc="close"
    >
      <div class="my-8 w-full max-w-lg overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-xl">
        <div class="flex items-start gap-3 border-b border-border p-4">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-golden-yellow mt-0.5">
            <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path>
            <path d="M7 7h.01"></path>
          </svg>
          <div>
            <h3 class="text-sm font-semibold text-white">{{ t.facility?.tag_quick_add_title || 'Add a new tag' }}</h3>
            <p v-if="suggested" class="mt-1 text-xs text-muted-foreground">
              {{ t.facility?.tag_quick_add_ai_note || 'None of the existing tags fit, so AI suggested this one. Edit it if needed, then add it.' }}
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

        <!-- .stop: nested inside the page form; without it the submit would
             bubble up and save the whole facility. -->
        <form @submit.prevent.stop="submit">
          <div class="max-h-[70vh] overflow-y-auto p-4 space-y-4">
            <FormTranslatableInput
              v-model="name"
              :label="t.tag?.name || 'Name'"
              :error="nameErrors"
              required
              :locales="['ar', 'en']"
            />

            <div>
              <label class="block text-sm font-medium text-white mb-2">{{ t.tag?.icon || 'Icon' }}</label>
              <div class="grid grid-cols-8 sm:grid-cols-10 gap-1.5 max-h-40 overflow-y-auto pr-1">
                <button
                  v-for="option in iconOptions"
                  :key="option.value"
                  type="button"
                  class="flex h-9 items-center justify-center rounded-md border text-lg transition-colors"
                  :class="icon === option.value ? 'border-primary bg-primary/15' : 'border-border bg-background hover:bg-muted'"
                  :title="option.label"
                  @click="icon = icon === option.value ? '' : option.value"
                >
                  {{ option.value }}
                </button>
              </div>
              <p v-if="errors.icon" class="mt-1 text-xs text-destructive">{{ errors.icon }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-white mb-2">{{ t.tag?.color || 'Color' }}</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="option in colorOptions"
                  :key="option.value"
                  type="button"
                  class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110"
                  :style="{ backgroundColor: option.value, borderColor: color === option.value ? '#fff' : 'transparent', boxShadow: color === option.value ? `0 0 0 2px ${option.value}` : 'none' }"
                  :title="option.label"
                  :aria-pressed="color === option.value"
                  @click="color = option.value"
                ></button>
              </div>
              <p v-if="errors.color" class="mt-1 text-xs text-destructive">{{ errors.color }}</p>
            </div>

            <div class="flex items-center gap-2 pt-1">
              <span class="text-xs font-medium text-muted-foreground">{{ t.tag?.preview || 'Preview' }}:</span>
              <span
                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium text-white"
                :style="{ backgroundColor: previewColor, borderColor: previewColor }"
              >
                <span v-if="icon">{{ icon }}</span>
                {{ previewName || (t.tag?.name || 'Name') }}
              </span>
            </div>

            <p v-if="errors.general" class="text-sm text-destructive">{{ errors.general }}</p>
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
              type="submit"
              :disabled="saving"
              class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 h-9 px-4 py-2"
            >
              <svg v-if="saving" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
              </svg>
              {{ t.facility?.tag_quick_add_submit || 'Add tag' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { FormTranslatableInput } from "@/Components/form";
import { computed, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
  open: { type: Boolean, default: false },
  // { name: { ar, en }, icon, color } — what the AI proposed, when it did.
  initial: { type: Object, default: null },
  iconOptions: { type: Array, default: () => [] },
  colorOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'created']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});
const locale = computed(() => page.props.locale || 'ar');

const name = ref({});
const icon = ref('');
const color = ref('');
const saving = ref(false);
const errors = ref({});

const suggested = computed(() => Boolean(props.initial));

// Every opening starts from the AI's proposal, or from blank.
watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  name.value = { ar: props.initial?.name?.ar || '', en: props.initial?.name?.en || '' };
  icon.value = props.initial?.icon || '';
  color.value = props.initial?.color || props.colorOptions[0]?.value || '';
  errors.value = {};
});

const previewName = computed(() => name.value[locale.value] || name.value.ar || name.value.en || '');
const previewColor = computed(() => color.value || '#6B7280');

// Laravel reports "name.ar" / "name.en"; the input wants them keyed by locale.
const nameErrors = computed(() => {
  const perLocale = {};
  for (const code of ['ar', 'en']) {
    if (errors.value[`name.${code}`]) perLocale[code] = errors.value[`name.${code}`];
  }
  return Object.keys(perLocale).length ? perLocale : (errors.value.name || null);
});

const close = () => {
  if (!saving.value) emit('close');
};

const reportError = (message, error, extra = {}) => {
  try {
    fetch('/api/v1/client-errors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        message,
        stack: error?.stack || String(error?.message || error || ''),
        route: window.location.pathname,
        fatal: false,
        extra: { step: 'facility-tag-quick-add', ...extra },
      }),
    }).catch(() => {});
  } catch {
    // Reporting is never worth a second error.
  }
};

const submit = async () => {
  if (saving.value) return;
  saving.value = true;
  errors.value = {};

  try {
    const { data } = await axios.post(route('admin.facility.tag.quick-store'), {
      name: { ar: (name.value.ar || '').trim(), en: (name.value.en || '').trim() },
      icon: icon.value || null,
      color: color.value || null,
    });
    emit('created', data.tag);
  } catch (error) {
    const status = error?.response?.status;
    if (status === 422) {
      const bag = error.response.data?.errors || {};
      errors.value = Object.fromEntries(Object.entries(bag).map(([key, list]) => [key, Array.isArray(list) ? list[0] : list]));
    } else {
      reportError('Quick tag create failed', error, { status: status ?? null });
      errors.value = {
        general: error?.response?.data?.message || (t.value.facility?.tag_quick_add_failed || 'Could not add the tag. Please try again.'),
      };
    }
  } finally {
    saving.value = false;
  }
};
</script>
