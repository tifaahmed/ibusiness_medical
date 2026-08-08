<template>
  <div class="relative w-full" ref="selectContainer">
    <!-- Trigger button -->
    <button
      type="button"
      :id="id"
      :disabled="disabled"
      @click.stop="toggleOpen"
      :class="[
        'file:text-foreground placeholder:text-gray-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border text-foreground flex h-7 sm:h-8 md:h-9 w-full min-w-0 rounded-md bg-background px-2 sm:px-2.5 md:px-3 py-1 text-xs sm:text-sm shadow-xs transition-all outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-[3px] focus-visible:bg-secondary/10',
        error
          ? 'border-destructive focus-visible:border-destructive focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40'
          : 'border-border focus-visible:border-ring focus-visible:ring-ring/50',
        isOpen ? 'ring-[3px] ring-ring/50' : ''
      ]"
      :aria-expanded="isOpen"
      :aria-haspopup="true"
      :aria-invalid="error ? 'true' : undefined"
      :aria-disabled="disabled ? 'true' : undefined"
    >
      <span :class="['flex-1 text-left truncate', !selectedLabel ? 'text-muted-foreground' : 'text-foreground']">
        {{ selectedLabel || placeholder }}
      </span>
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="lucide lucide-chevron-down h-4 w-4 shrink-0 opacity-50 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
      >
        <path d="m6 9 6 6 6-6"></path>
      </svg>
    </button>

    <!-- Dropdown (teleported to body to escape overflow) -->
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
          class="fixed min-w-[8rem] overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-md membership-card-theme"
          :style="dropdownStyle"
          @click.stop
        >
          <!-- Search input -->
          <div class="p-2 border-b border-border">
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
                class="absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
              >
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
              </svg>
              <input
                ref="searchInput"
                v-model="query"
                type="text"
                placeholder="Search..."
                class="w-full pl-7 pr-2 py-1.5 text-xs sm:text-sm bg-transparent border border-border rounded-md outline-none focus:border-ring focus:ring-[2px] focus:ring-ring/50 text-foreground placeholder:text-muted-foreground"
                @click.stop
                @keydown.escape.stop="close"
                @keydown.enter.prevent="selectFirst"
              />
            </div>
          </div>

          <!-- Options list -->
          <div class="max-h-[240px] overflow-y-auto p-1 select-dropdown-scrollbar">
            <!-- Clear / placeholder option -->
            <div
              v-if="placeholder"
              @click="selectOption(null)"
              :class="[
                'relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-xs sm:text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground',
                !modelValue || modelValue === '' ? 'bg-accent text-accent-foreground' : ''
              ]"
            >
              {{ placeholder }}
            </div>

            <div
              v-for="option in filteredOptions"
              :key="option.value"
              @click="selectOption(option.value)"
              :class="[
                'relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-xs sm:text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground',
                String(modelValue) === String(option.value) ? 'bg-accent text-accent-foreground' : ''
              ]"
            >
              <!-- Highlighted label -->
              <span v-html="highlight(option.label)" class="flex-1 truncate"></span>
              <svg
                v-if="String(modelValue) === String(option.value)"
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="lucide lucide-check ml-auto h-4 w-4 shrink-0"
              >
                <path d="M20 6 9 17l-5-5"></path>
              </svg>
            </div>

            <div v-if="filteredOptions.length === 0" class="px-2 py-3 text-xs text-muted-foreground text-center">
              No results found
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

const props = defineProps({
  modelValue: [String, Number, null],
  options: { type: Array, required: true, default: () => [] },
  placeholder: { type: String, default: 'Select...' },
  id: { type: String, default: () => `searchable-select-${Math.random().toString(36).substr(2, 9)}` },
  error: { type: String, default: null },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const query = ref('');
const selectContainer = ref(null);
const dropdownMenu = ref(null);
const searchInput = ref(null);
const dropdownStyle = ref({});

const fuse = computed(() => new Fuse(props.options, {
  keys: ['label'],
  threshold: 0.4,
  includeMatches: true,
}));

const filteredOptions = computed(() => {
  if (!query.value.trim()) return props.options;
  return fuse.value.search(query.value).map(r => r.item);
});

const selectedLabel = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return '';
  const opt = props.options.find(o => String(o.value) === String(props.modelValue));
  return opt ? opt.label : '';
});

// Highlight matched characters in the label
const highlight = (label) => {
  if (!query.value.trim()) return label;
  const escaped = query.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return label.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-golden-yellow/30 text-black rounded-sm">$1</mark>');
};

const selectFirst = () => {
  if (filteredOptions.value.length > 0) {
    selectOption(filteredOptions.value[0].value);
  }
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
.select-dropdown-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.select-dropdown-scrollbar::-webkit-scrollbar-track {
  background: transparent;
  border-radius: 3px;
}
.select-dropdown-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(177, 154, 113, 0.4);
  border-radius: 3px;
}
.select-dropdown-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(177, 154, 113, 0.65);
}
</style>
