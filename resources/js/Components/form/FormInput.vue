<!-- components/FormInput.vue -->
<template>
  <div>
    <label
      :for="id"
      class="block text-sm font-medium text-white mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
    </label>
    <input
      :id="id"
      ref="inputEl"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :type="type"
      :required="required"
      :class="[
        'w-full py-2 px-3 border border-border text-foreground placeholder:text-white/70 focus:border-ring dark:bg-input/30 bg-transparent focus:outline-none rounded-md h-9 mt-1 focus:ring-[3px] focus:ring-ring/50',
        error
          ? 'border-destructive focus:border-destructive focus:ring-destructive/20 dark:focus:ring-destructive/40'
          : '',
      ]"
      v-bind="$attrs"
    />
    <p v-if="error" class="mt-1 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  modelValue: [String, Number],
  label: String,
  type: { type: String, default: "text" },
  required: Boolean,
  error: String,
});

defineEmits(["update:modelValue"]);

const id = computed(() => `input-${Math.random().toString(36).substr(2, 9)}`);
const inputEl = ref(null);

defineExpose({
  focus: () => inputEl.value?.focus(),
});
</script>
