<template>
  <div class="relative w-full" ref="selectContainer">
    <!-- Trigger button -->
    <button
      type="button"
      :id="id"
      :disabled="disabled"
      @click.stop="toggleOpen"
      :class="[
        'w-full py-2 text-sm border border-gray-300 rounded-lg outline-none transition-all bg-white flex items-center justify-between relative min-h-[38px]',
        isOpen ? 'ring-2 ring-[#B89B6A] border-transparent' : 'focus:ring-2 focus:ring-[#B89B6A] focus:border-transparent',
        disabled ? 'bg-gray-100 cursor-not-allowed opacity-60' : 'cursor-pointer',
        locale === 'ar' ? 'pl-3 pr-8' : 'pr-8 pl-3'
      ]"
      style="color: #000000;"
    >
      <!-- Custom arrow icon, matching original design -->
      <svg
        xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
        :class="[
          'text-gray-400 pointer-events-none absolute top-1/2 -translate-y-1/2',
          locale === 'ar' ? 'left-2.5' : 'right-2.5'
        ]"
      >
        <path d="m6 9 6 6 6-6"/>
      </svg>
      <span class="flex-1 truncate text-sm" :class="[locale === 'ar' ? 'text-right' : 'text-left', {'text-gray-900': selectedLabel, 'text-gray-600': !selectedLabel}]" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        {{ selectedLabel || placeholder }}
      </span>
    </button>

    <!-- Dropdown -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="isOpen"
          ref="dropdownMenu"
          class="fixed z-50 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden"
          :style="dropdownStyle"
          @click.stop
        >
          <!-- Search input -->
          <div class="p-1.5 border-b border-gray-100">
            <div class="relative">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="absolute top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
                :class="locale === 'ar' ? 'right-2.5' : 'left-2.5'"
              >
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
              </svg>
              <input
                ref="searchInput"
                v-model="query"
                type="text"
                :placeholder="searchPlaceholder"
                class="w-full py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-md outline-none focus:border-[#B89B6A] focus:ring-1 focus:ring-[#B89B6A] text-[#1E3943] placeholder-gray-400 guest-search-input"
                :class="locale === 'ar' ? 'pr-8 pl-2.5' : 'pl-8 pr-2.5'"
                @click.stop
                @keydown.escape.stop="close"
                @keydown.enter.prevent="selectFirst"
                @keydown.down.prevent="moveHighlight(1)"
                @keydown.up.prevent="moveHighlight(-1)"
                :dir="locale === 'ar' ? 'rtl' : 'ltr'"
              />
            </div>
          </div>

          <!-- Options list -->
          <div class="max-h-52 overflow-y-auto p-0.5" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
            <!-- Placeholder / Clear option -->
            <div
              v-if="placeholder"
              @click="selectOption('')"
              class="relative flex cursor-pointer select-none items-center rounded-md px-3 py-2 text-sm outline-none transition-colors hover:bg-[#F9F6F1] text-[#1E3943]"
              :class="{ 'bg-[#1E3943] text-white hover:bg-[#1E3943]': !modelValue }"
            >
              <span class="flex-1 truncate" :class="locale === 'ar' ? 'text-right' : 'text-left'">{{ placeholder }}</span>
            </div>

            <div
              v-for="(option, index) in filteredOptions"
              :key="option.value"
              @click="selectOption(option.value)"
              class="relative flex cursor-pointer select-none items-center rounded-md px-3 py-2 text-sm outline-none transition-colors hover:bg-[#F9F6F1] text-[#1E3943]"
              :class="{
                'bg-[#1E3943] text-white hover:bg-[#1E3943]': String(modelValue) === String(option.value),
                'bg-[#F9F6F1]': highlightedIndex === index && String(modelValue) !== String(option.value),
              }"
            >
              <span
                class="flex-1 truncate"
                :class="locale === 'ar' ? 'text-right' : 'text-left'"
                v-html="highlight(option.label)"
              ></span>
            </div>

            <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-gray-500 text-center">
              {{ noResultsText }}
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import Fuse from 'fuse.js';

const normalizeSearch = (term) => {
  let t = String(term ?? '').toLowerCase().trim();
  t = t.replace(/[أإآٱ]/g, 'ا').replace(/ى/g, 'ي');
  t = t.replace(/[\u064B-\u065F\u0670]/g, '');
  return t;
};

const props = defineProps({
  modelValue: [String, Number, null],
  options: { type: Array, required: true, default: () => [] },
  placeholder: { type: String, default: 'Select...' },
  searchPlaceholder: { type: String, default: 'Search...' },
  noResultsText: { type: String, default: 'No results found' },
  id: { type: String, default: () => `searchable-select-${Math.random().toString(36).substr(2, 9)}` },
  disabled: { type: Boolean, default: false },
  locale: { type: String, default: 'en' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const query = ref('');
const highlightedIndex = ref(-1);
const selectContainer = ref(null);
const dropdownMenu = ref(null);
const searchInput = ref(null);
const dropdownStyle = ref({});

const fuse = computed(() => new Fuse(props.options, {
  keys: [{
    name: 'label',
    getFn: (option) => normalizeSearch(option.label),
  }],
  threshold: 0.35,
  ignoreLocation: true,
  minMatchCharLength: 1,
}));

const filteredOptions = computed(() => {
  const q = query.value.trim();
  if (!q) return props.options;
  return fuse.value.search(normalizeSearch(q)).map((result) => result.item);
});

watch(filteredOptions, () => { highlightedIndex.value = -1; });

const highlight = (label) => {
  const text = String(label ?? '');
  const q = query.value.trim();
  if (!q) return escapeHtml(text);

  const normalizedText = normalizeSearch(text);
  const normalizedQuery = normalizeSearch(q);
  const matchIndex = normalizedText.indexOf(normalizedQuery);
  if (matchIndex === -1) return escapeHtml(text);

  const before = text.slice(0, matchIndex);
  const match = text.slice(matchIndex, matchIndex + q.length);
  const after = text.slice(matchIndex + q.length);
  return `${escapeHtml(before)}<mark class="bg-[#B89B6A]/30 text-inherit rounded-sm">${escapeHtml(match)}</mark>${escapeHtml(after)}`;
};

const escapeHtml = (value) => value
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;');

const selectedLabel = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return '';
  const opt = props.options.find(o => String(o.value) === String(props.modelValue));
  return opt ? opt.label : '';
});

const selectFirst = () => {
  const index = highlightedIndex.value >= 0 ? highlightedIndex.value : 0;
  const option = filteredOptions.value[index];
  if (option) selectOption(option.value);
};

const moveHighlight = (direction) => {
  if (!filteredOptions.value.length) return;
  const next = highlightedIndex.value + direction;
  highlightedIndex.value = Math.max(0, Math.min(filteredOptions.value.length - 1, next));
};

const updatePosition = () => {
  if (!selectContainer.value || !isOpen.value) return;
  const rect = selectContainer.value.getBoundingClientRect();
  dropdownStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
    zIndex: 9999,
  };
};

const toggleOpen = async () => {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    query.value = '';
    highlightedIndex.value = -1;
    await nextTick();
    updatePosition();
    searchInput.value?.focus();
  }
};

const close = () => { isOpen.value = false; };

const selectOption = (value) => {
  emit('update:modelValue', value === null ? '' : value);
  emit('change', value === null ? '' : value);
  close();
};

const handleEscape = (e) => { if (e.key === 'Escape' && isOpen.value) close(); };

const handleClickOutside = (e) => {
  if (!isOpen.value) return;
  if (selectContainer.value && !selectContainer.value.contains(e.target)) {
    if (dropdownMenu.value && !dropdownMenu.value.contains(e.target)) close();
  }
};

const handleScroll = () => { if (isOpen.value) updatePosition(); };

watch(isOpen, (val) => { if (val) nextTick(updatePosition); });

onMounted(() => {
  document.addEventListener('keydown', handleEscape);
  document.addEventListener('click', handleClickOutside, true);
  window.addEventListener('scroll', handleScroll, true);
  window.addEventListener('resize', updatePosition);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape);
  document.removeEventListener('click', handleClickOutside, true);
  window.removeEventListener('scroll', handleScroll, true);
  window.removeEventListener('resize', updatePosition);
});
</script>

<style scoped>
.guest-search-input:not(:placeholder-shown) {
  color: #1E3943 !important;
}
</style>
