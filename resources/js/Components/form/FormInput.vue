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
    <div v-if="error || counterMax" class="mt-1 flex items-start gap-2">
      <p v-if="error" class="text-sm text-destructive flex-1">{{ error }}</p>
      <p
        v-if="counterMax"
        class="text-xs tabular-nums ms-auto shrink-0 leading-5"
        :class="counterComplete ? 'text-emerald-400' : 'text-white/70'"
      >
        {{ counterLength }}/{{ counterMax }}
      </p>
    </div>
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
  // When set, a "typed/max" counter is rendered under the field.
  counterMax: [String, Number],
});

defineEmits(["update:modelValue"]);

const counterMax = computed(() => Number(props.counterMax) || 0);
const counterLength = computed(() => String(props.modelValue ?? "").length);
const counterComplete = computed(() => counterLength.value === counterMax.value);

const id = computed(() => `input-${Math.random().toString(36).substr(2, 9)}`);
const inputEl = ref(null);

defineExpose({
  focus: () => inputEl.value?.focus(),
});
</script>
