<template>
  <Teleport to="body">
    <!-- No backdrop or Escape handler on purpose: only Cancel / Save leave. -->
    <div v-if="open" class="fixed inset-0 z-[120] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm">
      <div class="my-8 w-full max-w-lg rounded-xl border border-border bg-card text-card-foreground shadow-lg">
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
          <h3 class="flex items-center gap-2 text-sm font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle></svg>
            {{ tag ? 'Edit tag' : 'New tag' }}
          </h3>
        </div>

        <div class="space-y-4 p-4">
          <p v-if="errors.message" class="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">{{ errors.message }}</p>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-for="code in ['ar', 'en']" :key="code" class="space-y-1">
              <label class="text-xs font-medium">Name ({{ code === 'ar' ? 'Arabic' : 'English' }}) <span class="text-destructive">*</span></label>
              <input
                v-model="draft.name[code]"
                type="text"
                :dir="code === 'ar' ? 'rtl' : 'ltr'"
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
              />
              <p v-if="errors[`name.${code}`]" class="text-xs text-destructive">{{ errors[`name.${code}`] }}</p>
            </div>
          </div>
          <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>

          <div class="space-y-1">
            <label class="text-xs font-medium">Icon</label>
            <select v-model="draft.icon" class="flex h-9 w-full rounded-md border border-input bg-card px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
              <option value="">No icon</option>
              <option v-for="o in iconOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-medium">Color</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="c in colorOptions"
                :key="c.value"
                type="button"
                :title="c.label"
                class="h-7 w-7 rounded-full ring-offset-2 ring-offset-card transition"
                :class="draft.color === c.value ? 'ring-2 ring-primary' : 'ring-1 ring-border'"
                :style="{ backgroundColor: c.value }"
                @click="draft.color = c.value"
              ></button>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-medium">Applies to <span class="text-destructive">*</span></label>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="o in targets"
                :key="o.value"
                class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition"
                :class="draft.applies_to.includes(o.value) ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted'"
              >
                <input type="checkbox" :value="o.value" v-model="draft.applies_to" />
                {{ o.label }}
              </label>
            </div>
            <p v-if="errors.applies_to" class="text-xs text-destructive">{{ errors.applies_to }}</p>
          </div>

          <div v-if="previewName || draft.icon" class="flex items-center gap-2">
            <span class="text-xs text-muted-foreground">Preview:</span>
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset" :style="previewStyle">
              <span v-if="draft.icon">{{ draft.icon }}</span>{{ previewName }}
            </span>
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-border px-4 py-3">
          <button type="button" :disabled="saving" class="h-9 rounded-md border border-border bg-background px-4 text-sm font-medium hover:bg-muted disabled:opacity-50" @click="$emit('close')">Cancel</button>
          <button type="button" :disabled="saving" class="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50" @click="save">
            {{ saving ? 'Saving…' : (tag ? 'Save tag' : 'Create tag') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  open: { type: Boolean, default: false },
  // The tag being edited, or null to create one.
  tag: { type: Object, default: null },
  iconOptions: { type: Array, default: () => [] },
  colorOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved']);

const targets = [
  { value: 'facilities', label: 'Facilities' },
  { value: 'products', label: 'Products' },
  { value: 'stores', label: 'Stores' },
];

const blank = () => ({ name: { ar: '', en: '' }, icon: '', color: '#3B82F6', applies_to: ['stores'] });
const draft = reactive(blank());
const errors = ref({});
const saving = ref(false);

// Each time the dialog opens it starts from the tag (or a blank one).
watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  errors.value = {};
  const t = props.tag;
  Object.assign(draft, t
    ? {
        name: { ar: t.name_translations?.ar || '', en: t.name_translations?.en || '' },
        icon: t.icon || '',
        color: t.color || '#3B82F6',
        applies_to: t.applies_to?.length ? [...t.applies_to] : ['stores'],
      }
    : blank());
});

const previewName = computed(() => draft.name.ar || draft.name.en || '');
const previewStyle = computed(() => ({
  backgroundColor: `${draft.color}1A`,
  color: draft.color,
  borderColor: `${draft.color}33`,
}));

const report = (error, step) => {
  axios.post('/api/v1/client-errors', {
    message: error?.message || String(error),
    stack: error?.stack,
    fatal: false,
    route: window.location.pathname,
    extra: { feature: 'store-tag-dialog', step, status: error?.response?.status, tag_id: props.tag?.id ?? null },
  }).catch(() => {});
};

const save = async () => {
  saving.value = true;
  errors.value = {};
  const payload = { name: { ...draft.name }, icon: draft.icon || null, color: draft.color || null, applies_to: [...draft.applies_to] };

  try {
    const { data } = props.tag
      ? await axios.put(route('admin.tag.quick.update', props.tag.id), payload)
      : await axios.post(route('admin.tag.quick.store'), payload);
    emit('saved', data.tag);
    emit('close');
  } catch (error) {
    if (error?.response?.status === 422) {
      const raw = error.response.data?.errors || {};
      errors.value = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
    } else {
      errors.value = { message: error?.response?.data?.message || 'Could not save the tag. Please try again.' };
      report(error, props.tag ? 'update' : 'create');
    }
  } finally {
    saving.value = false;
  }
};
</script>
