<template>
  <Teleport to="body">
    <!-- No backdrop or Escape handler on purpose: only Close leaves. -->
    <div v-if="open" class="fixed inset-0 z-[120] flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm">
      <div class="my-8 w-full max-w-md rounded-xl border border-border bg-card text-card-foreground shadow-lg">
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
          <h3 class="flex items-center gap-2 text-sm font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path></svg>
            Find tags with AI
          </h3>
          <button type="button" :disabled="busy" class="h-8 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted disabled:opacity-50" @click="$emit('close')">Close</button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex items-end gap-2">
            <div class="space-y-1">
              <label class="text-xs font-medium">How many tags?</label>
              <input v-model.number="count" type="number" min="1" max="10" class="flex h-9 w-24 rounded-md border border-input bg-transparent px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" />
            </div>
            <button type="button" :disabled="busy || !validCount" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50" @click="run">
              <svg v-if="searching" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path></svg>
              {{ searching ? 'Looking…' : 'Find with AI' }}
            </button>
          </div>

          <p v-if="error" class="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">{{ error }}</p>

          <template v-if="result">
            <div class="space-y-1.5">
              <p class="text-xs font-medium">Matched from existing tags ({{ matchedTags.length }})</p>
              <div v-if="matchedTags.length" class="flex flex-wrap gap-1.5">
                <span v-for="t in matchedTags" :key="t.id" class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset" :style="chip(t.color)">
                  <span v-if="t.icon">{{ t.icon }}</span>{{ label(t) }}
                </span>
              </div>
              <p v-else class="text-xs text-muted-foreground">No existing tag fits this store.</p>
              <p v-if="matchedTags.length" class="text-[11px] text-muted-foreground">Already selected on the form.</p>
            </div>

            <div v-if="result.suggestions.length" class="space-y-2">
              <p class="text-xs font-medium">New tags you could add — tick the ones you like</p>
              <div class="space-y-1.5">
                <label
                  v-for="(s, i) in result.suggestions"
                  :key="i"
                  class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition"
                  :class="picked.includes(i) ? 'border-primary bg-primary/10' : 'border-border hover:bg-muted'"
                >
                  <input type="checkbox" :value="i" v-model="picked" />
                  <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :style="chip(s.color)">
                    <span>{{ s.icon }}</span>{{ s.name.en }}
                  </span>
                  <span class="text-xs text-muted-foreground" dir="rtl">{{ s.name.ar }}</span>
                </label>
              </div>
              <button type="button" :disabled="busy || !picked.length" class="inline-flex h-9 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50" @click="createPicked">
                {{ creating ? 'Creating…' : `Add ${picked.length} selected tag${picked.length === 1 ? '' : 's'}` }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  open: { type: Boolean, default: false },
  // The store form as it stands — the AI reads it, nothing is saved.
  form: { type: Object, required: true },
  tags: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'matched', 'created']);

const count = ref(3);
const searching = ref(false);
const creating = ref(false);
const error = ref('');
const result = ref(null);
const picked = ref([]);

const busy = computed(() => searching.value || creating.value);
const validCount = computed(() => Number.isInteger(count.value) && count.value >= 1 && count.value <= 10);
const matchedTags = computed(() => (result.value?.matched || []).map(id => props.tags.find(t => t.id === id)).filter(Boolean));

watch(() => props.open, (isOpen) => {
  if (isOpen) { error.value = ''; result.value = null; picked.value = []; }
});

const label = (t) => t.name_translations?.en || t.name_translations?.ar || '';
const chip = (color) => ({ backgroundColor: `${color || '#6B7280'}1A`, color: color || '#6B7280', borderColor: `${color || '#6B7280'}33` });

const report = (err, step) => {
  axios.post('/api/v1/client-errors', {
    message: err?.message || String(err),
    stack: err?.stack,
    fatal: false,
    route: window.location.pathname,
    extra: { feature: 'store-tag-ai', step, status: err?.response?.status },
  }).catch(() => {});
};

const run = async () => {
  searching.value = true;
  error.value = '';
  result.value = null;
  picked.value = [];
  try {
    const f = props.form;
    const { data } = await axios.post(route('admin.store.tags.suggest'), {
      count: count.value,
      title: f.title,
      short_description: f.short_description,
      description: f.description,
      category_ids: f.category_ids || [],
    });
    result.value = { matched: data.matched || [], suggestions: data.suggestions || [] };
    if (result.value.matched.length) emit('matched', result.value.matched);
    // Tags the AI is most sure of start ticked, up to what is still missing.
    picked.value = result.value.suggestions
      .map((_, i) => i)
      .slice(0, Math.max(count.value - result.value.matched.length, 0));
  } catch (err) {
    error.value = err?.response?.data?.message || 'Could not find tags. Please try again.';
    if (err?.response?.status !== 422) report(err, 'suggest');
  } finally {
    searching.value = false;
  }
};

const createPicked = async () => {
  creating.value = true;
  error.value = '';
  const failed = [];
  for (const i of [...picked.value]) {
    const s = result.value.suggestions[i];
    try {
      const { data } = await axios.post(route('admin.tag.quick.store'), {
        name: s.name, icon: s.icon, color: s.color, applies_to: ['stores'],
      });
      emit('created', data.tag);
    } catch (err) {
      failed.push(s.name.en);
      report(err, 'create');
    }
  }
  // Created ones leave the list; failed ones stay ticked to retry.
  result.value.suggestions = result.value.suggestions.filter(s => failed.includes(s.name.en));
  picked.value = result.value.suggestions.map((_, i) => i);
  if (failed.length) error.value = `Could not create: ${failed.join(', ')}.`;
  else emit('close');
  creating.value = false;
};
</script>
