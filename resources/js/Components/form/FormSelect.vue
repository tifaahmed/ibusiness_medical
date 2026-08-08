<!--
  Shared select field used across admin forms.

  Layer-cake:
    FormSelect (label + hint + error + slot) → Select (passthrough) → SearchableSelect (teleported popover)

  Props:
    - modelValue / @update:modelValue : v-model contract
    - label                            : visible label text
    - required                         : adds a red asterisk in the label
    - options                          : [{ value, label }, ...]
    - placeholder                      : text shown when nothing selected (also acts as the "clear" row at the top of the popover)
    - error                            : error string — colors border + shows under the input
    - disabled                         : disables the trigger button entirely
    - description                      : small muted text rendered after the label — for non-error helper copy
    - hint                             : small muted block rendered under the input — for non-error helper copy
  Slots:
    - label-hint                       : richer markup that sits inline with the label (overrides `description` when present)
-->
<template>
  <div>
    <label
      :for="id"
      class="block text-sm font-medium text-white mb-1"
    >
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
      <slot name="label-hint">
        <span v-if="description" class="text-xs text-muted-foreground font-normal ml-1">
          {{ description }}
        </span>
      </slot>
    </label>
    <div class="mt-1">
      <Select
        :id="id"
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        @change="$emit('change', $event)"
        :options="options"
        :placeholder="placeholder || `Select ${label}`"
        :error="error"
        :disabled="disabled"
      />
    </div>
    <p v-if="hint && !error" class="mt-1 text-[11px] text-muted-foreground">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-sm text-destructive">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed } from "vue";
import Select from "@/Components/ui/Select.vue";

const props = defineProps({
  modelValue: [String, Number, null],
  label: String,
  options: {
    type: Array,
    default: () => []
  },
  required: Boolean,
  error: String,
  placeholder: String,
  disabled: { type: Boolean, default: false },
  description: { type: String, default: '' },
  hint: { type: String, default: '' },
});

defineEmits(["update:modelValue", "change"]);

const id = computed(() => `select-${Math.random().toString(36).substr(2, 9)}`);
</script>
