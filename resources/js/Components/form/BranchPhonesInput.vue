<template>
  <div class="w-full">
    <label class="block text-sm font-medium text-white mb-2">
      {{ label }}
      <span v-if="hint" class="text-xs text-white/70 ms-1">{{ hint }}</span>
    </label>

    <div class="space-y-2">
      <div
        v-for="(entry, index) in entries"
        :key="index"
        class="flex flex-col sm:flex-row gap-2"
      >
        <input
          :value="entry.number"
          @input="updateNumber(index, $event.target.value)"
          type="tel"
          dir="ltr"
          :placeholder="numberPlaceholder"
          :class="[
            'flex-1 min-w-0 py-2 px-3 border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md h-9 focus:ring-[3px] focus:ring-ring/50',
            rowError(index) ? 'border-destructive focus:border-destructive focus:ring-destructive/20' : 'border-border'
          ]"
        />
        <select
          :value="entry.type"
          @change="updateType(index, $event.target.value)"
          class="sm:w-48 shrink-0 border-border rounded-md border bg-transparent dark:bg-input/30 px-3 text-sm h-9 cursor-pointer focus:outline-none focus:ring-[3px] focus:ring-ring/50 focus:border-ring"
        >
          <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
        </select>
        <button
          type="button"
          @click="removeRow(index)"
          class="shrink-0 inline-flex h-9 w-9 items-center justify-center rounded-md border border-border bg-background text-destructive transition hover:bg-destructive/10"
          :title="t.common?.delete || 'Remove'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
    </div>

    <button
      type="button"
      @click="addRow"
      class="mt-2 inline-flex h-9 items-center justify-center gap-1.5 rounded-md border border-border bg-background px-3 text-sm font-medium transition hover:bg-muted"
    >
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 12h14"></path><path d="M12 5v14"></path>
      </svg>
      {{ t.facility_branch?.add_phone || 'Add phone number' }}
    </button>

    <p v-if="errorText" class="mt-1 text-sm text-destructive">{{ errorText }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PHONE_TYPES, DEFAULT_PHONE_TYPE, normalizePhoneEntries, phoneTypeLabel } from '@/lib/branchPhones';

const props = defineProps({
  // A list of { number, type }. Flat strings from older rows are accepted and
  // shown as typed rows, so an unedited branch does not lose its numbers.
  modelValue: {
    type: [Array, String],
    default: () => []
  },
  label: {
    type: String,
    default: ''
  },
  hint: {
    type: String,
    default: ''
  },
  // Server-side messages, keyed as the request reports them:
  // phone, phone.0.number, phone.1.type …
  errors: {
    type: Object,
    default: () => ({})
  },
  // The prefix those keys carry — 'phone' here, 'branches.2.phone' if this is
  // ever rendered inside the full facility form's branch list.
  errorPrefix: {
    type: String,
    default: 'phone'
  }
});

const emit = defineEmits(['update:modelValue']);

const page = usePage();
const t = computed(() => page.props.translations?.admin || {});

const types = PHONE_TYPES;

const typeLabel = (type) => t.value.facility_branch?.phone_types?.[type] || phoneTypeLabel(type);

const numberPlaceholder = computed(() =>
  t.value.facility_branch?.phone_number_placeholder || '01020709993'
);

// Always render at least one row: an empty branch should show an input to type
// into, not a bare "add" button.
const entries = computed(() => {
  const normalized = normalizePhoneEntries(props.modelValue);
  return normalized.length > 0 ? normalized : [{ number: '', type: DEFAULT_PHONE_TYPE }];
});

const commit = (next) => {
  // Rows left completely blank are dropped on the way out — the server would
  // reject them, and an admin who clicked "add" twice did not mean to add one.
  emit('update:modelValue', next.filter((entry) => String(entry.number || '').trim() !== ''));
};

const updateNumber = (index, value) => {
  const next = entries.value.map((entry) => ({ ...entry }));
  next[index].number = value;
  commit(next);
};

const updateType = (index, value) => {
  const next = entries.value.map((entry) => ({ ...entry }));
  next[index].type = value;
  commit(next);
};

const addRow = () => {
  // The new row is only kept once it has a number, so append it to what the
  // parent holds rather than to the rendered list.
  const next = [...entries.value.map((entry) => ({ ...entry })), { number: '', type: DEFAULT_PHONE_TYPE }];
  emit('update:modelValue', next);
};

const removeRow = (index) => {
  const next = entries.value.map((entry) => ({ ...entry }));
  next.splice(index, 1);
  commit(next);
};

const rowError = (index) =>
  props.errors?.[`${props.errorPrefix}.${index}.number`]
  || props.errors?.[`${props.errorPrefix}.${index}.type`]
  || '';

// The list-level message, plus the first row-level one so a problem lower down
// is never silently invisible.
const errorText = computed(() => {
  const list = props.errors?.[props.errorPrefix];
  if (list) return Array.isArray(list) ? list[0] : list;

  for (let i = 0; i < entries.value.length; i += 1) {
    const message = rowError(i);
    if (message) return Array.isArray(message) ? message[0] : message;
  }

  return '';
});
</script>
